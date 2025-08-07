<?php

namespace Utopia\Migration\Sources;

use Utopia\CLI\Console;
use Utopia\Database\Database as UtopiaDatabase;
use Utopia\Migration\Exception;
use Utopia\Migration\Resource as UtopiaResource;
use Utopia\Migration\Resources\Database\Attribute;
use Utopia\Migration\Resources\Database\Collection;
use Utopia\Migration\Resources\Database\Database;
use Utopia\Migration\Resources\Database\Document;
use Utopia\Migration\Resources\Storage\File;
use Utopia\Migration\Source;
use Utopia\Migration\Sources\Appwrite\Reader;
use Utopia\Migration\Sources\Appwrite\Reader\Database as DatabaseReader;
use Utopia\Migration\Transfer;
use Utopia\Storage\Device;
use Utopia\Storage\Storage;

class CSV extends Source
{
    private const ALLOWED_INTERNALS = [
        '$id' => true,
        '$permissions' => true,
        '$createdAt' => true,
        '$updatedAt' => true,
    ];

    private string $filePath;

    /**
     * format: `{databaseId:collectionId}`
     */
    private string $resourceId;

    private Device $device;

    private Reader $database;

    private bool $downloaded = false;

    public function __construct(
        string $resourceId,
        string $filePath,
        Device $device,
        ?UtopiaDatabase $dbForProject
    ) {
        $this->device = $device;
        $this->filePath = $filePath;
        $this->resourceId = $resourceId;
        $this->database = new DatabaseReader($dbForProject);
    }

    public static function getName(): string
    {
        return 'CSV';
    }

    public static function getSupportedResources(): array
    {
        return [
            UtopiaResource::TYPE_DOCUMENT,
        ];
    }

    // called before the `exportGroupDatabases`.

    /**
     * @throws \Exception
     */
    public function report(array $resources = []): array
    {
        $report = [];

        if (!$this->device->exists($this->filePath)) {
            return $report;
        }

        $this->downloadToLocal(
            $this->device,
            $this->filePath,
        );

        $file = new \SplFileObject($this->filePath, 'r');
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);

        $file->seek(PHP_INT_MAX);
        $rowCount = max(0, $file->key());

        $report[UtopiaResource::TYPE_DOCUMENT] = $rowCount;

        return $report;
    }

    /**
     * @throws \Exception
     */
    protected function exportGroupAuth(int $batchSize, array $resources): void
    {
        throw new \Exception('Not Implemented');
    }

    protected function exportGroupDatabases(int $batchSize, array $resources): void
    {
        try {
            if (\in_array(UtopiaResource::TYPE_DOCUMENT, $resources)) {
                $this->exportDocuments($batchSize);
            }
        } catch (\Throwable $e) {
            $this->addError(
                new Exception(
                    UtopiaResource::TYPE_DOCUMENT,
                    Transfer::GROUP_DATABASES,
                    message: $e->getMessage(),
                    code: $e->getCode(),
                    previous: $e
                )
            );
        } finally {
            // delete the temporary file!
            $this->device->delete($this->filePath);
        }
    }

    /**
     * @throws \Exception
     */
    private function exportDocuments(int $batchSize): void
    {
        $attributes = [];
        $lastAttribute = null;

        [$databaseId, $collectionId] = explode(':', $this->resourceId);
        $database = new Database($databaseId, '');
        $collection = new Collection($database, '', $collectionId);

        while (true) {
            $queries = [$this->database->queryLimit($batchSize)];
            if ($lastAttribute) {
                $queries[] = $this->database->queryCursorAfter($lastAttribute);
            }

            $fetched = $this->database->listAttributes($collection, $queries);
            if (empty($fetched)) {
                break;
            }

            array_push($attributes, ...$fetched);
            $lastAttribute = $fetched[count($fetched) - 1];

            if (count($fetched) < $batchSize) {
                break;
            }
        }

        $arrayKeys = [
            '$permissions' => true,
        ];
        $attributeTypes = [];
        $requiredAttributes = [];
        $manyToManyKeys = [];

        foreach ($attributes as $attribute) {
            $key = $attribute['key'];
            $type = $attribute['type'];
            $isArray = $attribute['array'] ?? false;
            $isRequired = $attribute['required'] ?? false;
            $relationSide = $attribute['side'] ?? '';
            $relationType = $attribute['relationType'] ?? '';

            if (
                $type === Attribute::TYPE_RELATIONSHIP &&
                $relationSide === UtopiaDatabase::RELATION_SIDE_CHILD
            ) {
                continue;
            }

            $attributeTypes[$key] = $type;
            if ($isRequired) {
                $requiredAttributes[$key] = true;
            }

            if (
                $type === Attribute::TYPE_RELATIONSHIP &&
                $relationType === 'manyToMany' &&
                $relationSide === 'parent'
            ) {
                $manyToManyKeys[$key] = true;
            }

            if ($isArray && $type !== Attribute::TYPE_RELATIONSHIP) {
                $arrayKeys[$key] = true;
            }
        }

        $this->withCSVStream(function ($stream, $delimiter) use ($attributeTypes, $requiredAttributes, $manyToManyKeys, $arrayKeys, $collection, $batchSize) {
            $headers = fgetcsv($stream);
            if (!is_array($headers) || count($headers) === 0) {
                return;
            }

            $this->validateCSVHeaders($headers, $attributeTypes, $requiredAttributes);

            $buffer = [];

            while (($csvDocumentItem = fgetcsv(stream: $stream, separator: $delimiter)) !== false) {
                if (count($csvDocumentItem) !== count($headers)) {
                    throw new \Exception('CSV document does not match the number of header attributes.');
                }

                $data = \array_combine($headers, $csvDocumentItem);
                if ($data === false) {
                    continue;
                }

                $parsedData = $data;

                foreach ($data as $key => $value) {
                    $parsedValue = trim($value);
                    $type = $attributeTypes[$key] ?? null;

                    if (!isset($type) && $key !== '$permissions') {
                        if (isset(self::ALLOWED_INTERNALS[$key])) {
                            continue;
                        }
                        // Skip unknown attributes instead of throwing an error
                        // (they were already reported in header validation)
                        continue;
                    }

                    if (isset($manyToManyKeys[$key])) {
                        $parsedData[$key] = $parsedValue === ''
                            ? []
                            : array_values(
                                array_filter(
                                    array_map(
                                        trim(...),
                                        explode(',', $parsedValue)
                                    )
                                )
                            );
                        continue;
                    }

                    if (isset($arrayKeys[$key])) {
                        if ($parsedValue === '') {
                            $parsedData[$key] = [];
                        } else {
                            // Try to decode as JSON first (Excel/Google Sheets format)
                            $arrayValues = json_decode($parsedValue, true);

                            // If JSON decode fails, fall back to comma-separated parsing for backward compatibility
                            if (json_last_error() !== JSON_ERROR_NONE) {
                                $arrayValues = str_getcsv($parsedValue);
                                $arrayValues = array_map(trim(...), $arrayValues);
                                // Remove empty strings from comma-separated parsing
                                $arrayValues = array_filter($arrayValues, fn ($item) => $item !== '');
                                $arrayValues = array_values($arrayValues); // Re-index array
                            }

                            if (!is_array($arrayValues)) {
                                throw new \Exception("Invalid array format for attribute '$key': $parsedValue");
                            }

                            $parsedData[$key] = array_map(function ($item) use ($type) {
                                return match ($type) {
                                    Attribute::TYPE_INTEGER => is_numeric($item) ? (int) $item : null,
                                    Attribute::TYPE_FLOAT => is_numeric($item) ? (float) $item : null,
                                    Attribute::TYPE_BOOLEAN => filter_var($item, FILTER_VALIDATE_BOOLEAN),
                                    default => $item,
                                };
                            }, $arrayValues);
                        }
                        continue;
                    }

                    // Handle empty values vs missing values:
                    // Empty string in CSV ("") = empty string result
                    if ($parsedValue === '') {
                        $parsedData[$key] = match ($type) {
                            Attribute::TYPE_INTEGER, Attribute::TYPE_FLOAT => null,
                            Attribute::TYPE_BOOLEAN => null,
                            Attribute::TYPE_DATETIME => null,
                            Attribute::TYPE_RELATIONSHIP => null,
                            default => '', // Text fields: empty string from CSV becomes empty string
                        };
                    } else {
                        $parsedData[$key] = match ($type) {
                            Attribute::TYPE_INTEGER => \is_numeric($parsedValue) ? (int) $parsedValue : null,
                            Attribute::TYPE_FLOAT => \is_numeric($parsedValue) ? (float) $parsedValue : null,
                            Attribute::TYPE_BOOLEAN => \filter_var($parsedValue, FILTER_VALIDATE_BOOLEAN),
                            default => $parsedValue,
                        };
                    }
                }

                $documentId = $parsedData['$id'] ?? 'unique()';
                $permissions = $parsedData['$permissions'] ?? [];

                unset($parsedData['$id'], $parsedData['$permissions']);

                $document = new Document(
                    $documentId,
                    $collection,
                    $parsedData,
                    $permissions,
                );

                $buffer[] = $document;

                if (count($buffer) === $batchSize) {
                    $this->callback($buffer);
                    $buffer = [];
                }
            }

            if (! empty($buffer)) {
                $this->callback($buffer);
            }
        });
    }

    /**
     * @throws \Exception
     */
    protected function exportGroupStorage(int $batchSize, array $resources): void
    {
        throw new \Exception('Not Implemented');
    }

    /**
     * @throws \Exception
     */
    protected function exportBuckets(int $batchSize): void
    {
        throw new \Exception('Not Implemented');
    }

    /**
     * @throws \Exception
     */
    private function exportFiles(int $batchSize): void
    {
        throw new \Exception('Not Implemented');
    }

    /**
     * @throws \Exception
     */
    private function exportFile(File $file): void
    {
        throw new \Exception('Not Implemented');
    }

    /**
     * @throws \Exception
     */
    protected function exportGroupFunctions(int $batchSize, array $resources): void
    {
        throw new \Exception('Not Implemented');
    }

    /**
     * @param callable(resource $stream): void $callback
     * @return void
     * @throws \Exception
     */
    private function withCsvStream(callable $callback): void
    {
        if (!$this->device->exists($this->filePath)) {
            return;
        }

        if (!$this->downloaded) {
            $this->downloadToLocal(
                $this->device,
                $this->filePath,
            );
        }

        $stream = \fopen($this->filePath, 'r');
        if (!$stream) {
            return;
        }

        try {
            $callback($stream);
        } finally {
            \fclose($stream);
        }
    }

    /**
     * @throws \Exception
     */
    private function validateCSVHeaders(array $headers, array $attributeTypes, array $requiredAttributes): void
    {
        $internalAttributes = ['$id', '$permissions', '$createdAt', '$updatedAt'];
        $allKnownAttributes = \array_keys($attributeTypes);

        // Only validate that required attributes are present
        $missingRequiredAttributes = [];
        foreach (\array_keys($requiredAttributes) as $requiredAttribute) {
            if (!\in_array($requiredAttribute, $headers)) {
                $missingRequiredAttributes[] = $requiredAttribute;
            }
        }

        // Check for completely unknown attributes (not in schema and not internal)
        $unknownAttributes = \array_diff($headers, $allKnownAttributes, $internalAttributes);

        $messages = [];

        if (!empty($missingRequiredAttributes)) {
            $label = \count($missingRequiredAttributes) === 1 ? 'Missing required attribute' : 'Missing required attributes';
            $messages[] = "$label: '".\implode("', '", $missingRequiredAttributes)."'";
        }
        if (!empty($unknownAttributes)) {
            $label = \count($unknownAttributes) === 1 ? 'Unknown attribute' : 'Unknown attributes';
            $messages[] = "$label: '".\implode("', '", $unknownAttributes)."' (will be ignored)";
        }
        if (!empty($missingRequiredAttributes)) {
            throw new \Exception('CSV header validation failed: '. \implode(', ', $messages));
        }

        // If there are unknown attributes but no missing required attributes, just log a warning
        if (!empty($unknownAttributes)) {
            Console::warning(\implode(', ', $messages));
        }
    }

    /**
     * @throws \Exception
     */
    private function downloadToLocal(
        Device $device,
        string $filePath
    ): void {
        if ($this->downloaded
            || $device->getType() === Storage::DEVICE_LOCAL
        ) {
            return;
        }

        try {
            $success = $device->transfer(
                $filePath,
                $filePath,
                new Device\Local('/'),
            );
        } catch (\Exception $e) {
            $success = false;
        }

        if (!$success) {
            throw new \Exception('Failed to transfer CSV file from device to local storage.', previous: $e ?? null);
        }

        $this->downloaded = true;
    }
}
