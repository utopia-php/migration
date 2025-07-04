<?php

namespace Utopia\Migration\Sources;

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
    private string $filePath;

    /**
     * format: `{databaseId:collectionId}`
     */
    private string $resourceId;

    private Device $device;

    private Reader $database;

    private bool $downloaded = false;

    // caching
    private ?array $cachedAttributes = null;

    private ?array $attributeMetadata = null;

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
        $this->loadAndCacheAttributes($batchSize);

        [$databaseId, $collectionId] = explode(":", $this->resourceId);
        $database = new Database($databaseId, "");
        $collection = new Collection($database, "", $collectionId);

        $this->withCsvStream(function ($stream) use ($batchSize, $collection) {
            $headers = fgetcsv($stream);
            if (! is_array($headers) || count($headers) === 0) {
                return;
            }

            $this->validateCSVHeaders($headers, array_column($this->attributeMetadata, 'type'));

            $buffer = [];

            while (($row = fgetcsv($stream)) !== false) {
                if (count($row) !== count($headers)) {
                    throw new \Exception('CSV row does not match the number of header columns.');
                }

                $data = array_combine($headers, $row);
                if ($data === false) {
                    continue;
                }

                $parsedData = $this->parseRow($data);
                $documentId = $parsedData['$id'] ?? 'unique()';

                // `$id`, `$permissions` in the doc can cause issues!
                unset($parsedData['$id'], $parsedData['$permissions']);

                $document = new Document($documentId, $collection, $parsedData);
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
     * @param int $batchSize
     * @return void
     * @throws Exception
     * @throws \Utopia\Database\Exception
     */
    private function loadAndCacheAttributes(int $batchSize): void
    {
        if ($this->cachedAttributes !== null) {
            return;
        }

        [ $databaseId, $collectionId ] = explode(":", $this->resourceId);

        $database = new Database($databaseId, "");
        $collection = new Collection($database, "", $collectionId);

        $attributes = [];
        $lastAttribute = null;

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

        $this->cachedAttributes = $attributes;
        $this->preprocessAttributes();
    }

    /**
     * @return void
     */
    private function preprocessAttributes(): void
    {
        $this->attributeMetadata = [];

        foreach ($this->cachedAttributes as $attribute) {
            $key = $attribute["key"];
            $type = $attribute["type"];
            $isArray = $attribute["array"] ?? false;
            $relationSide = $attribute["side"] ?? "";
            $relationType = $attribute["relationType"] ?? "";

            if (
                $type === Attribute::TYPE_RELATIONSHIP &&
                $relationSide === UtopiaDatabase::RELATION_SIDE_CHILD
            ) {
                continue;
            }

            $this->attributeMetadata[$key] = [
                "type" => $type,
                "isArray" => $isArray && $type !== Attribute::TYPE_RELATIONSHIP,
                "isManyToMany" =>
                    $type === Attribute::TYPE_RELATIONSHIP &&
                    $relationType === "manyToMany" &&
                    $relationSide === "parent",
            ];
        }
    }

    /**
     * @param array $data
     * @return array
     */
    private function parseRow(array $data): array
    {
        $parsedData = [];

        foreach ($data as $key => $value) {
            $parsedValue = trim($value);

            if (!isset($this->attributeMetadata[$key])) {
                continue;
            }

            $metadata = $this->attributeMetadata[$key];
            $type = $metadata["type"];

            if ($metadata["isManyToMany"]) {
                $parsedData[$key] = $parsedValue === ''
                    ? []
                    : array_values(
                        array_filter(
                            array_map(
                                'trim',
                                explode(',', $parsedValue)
                            )
                        )
                    );
                continue;
            }

            // array attributes
            if ($metadata["isArray"]) {
                if ($parsedValue === "") {
                    $parsedData[$key] = [];
                } else {
                    $arrayValues = str_getcsv($parsedValue);
                    $arrayValues = array_map('trim', $arrayValues);
                    $parsedData[$key] = $this->convertScalars($arrayValues, $type, true);
                }
                continue;
            }

            // normal attributes
            if ($parsedValue !== "") {
                $parsedData[$key] = $this->convertScalars($parsedValue, $type);
            } else {
                $parsedData[$key] = $parsedValue;
            }
        }

        return $parsedData;
    }

    private function convertScalars(mixed $value, string $type, bool $isArray = false): mixed
    {
        $values = is_array($value) ? $value : [$value];

        $converted = match ($type) {
            Attribute::TYPE_INTEGER => array_map(
                fn ($v) => ($v = trim($v)) !== '' && is_numeric($v) ? (int) $v : null,
                $values
            ),
            Attribute::TYPE_FLOAT => array_map(
                fn ($v) => ($v = trim($v)) !== '' && is_numeric($v) ? (float) $v : null,
                $values
            ),
            Attribute::TYPE_BOOLEAN => array_map(
                fn ($v) => filter_var(trim($v), FILTER_VALIDATE_BOOLEAN),
                $values
            ),
            default => array_map('trim', $values),
        };

        return $isArray ? $converted : $converted[0];
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
    private function validateCSVHeaders(array $headers, array $attributeTypes): void
    {
        $expectedAttributes = array_keys($attributeTypes);

        // Ignore keys like $id, $permissions, etc.
        $filteredHeaders = array_filter($headers, fn ($key) => ! str_starts_with($key, '$'));

        $extraAttributes = array_diff($filteredHeaders, $expectedAttributes);
        $missingAttributes = array_diff($expectedAttributes, $filteredHeaders);

        if (! empty($missingAttributes) || ! empty($extraAttributes)) {
            $messages = [];

            if (! empty($missingAttributes)) {
                $label = count($missingAttributes) === 1 ? 'Missing attribute' : 'Missing attributes';
                $messages[] = "{$label}: '".implode("', '", $missingAttributes)."'";
            }

            if (! empty($extraAttributes)) {
                $label = count($extraAttributes) === 1 ? 'Unexpected attribute' : 'Unexpected attributes';
                $messages[] = "{$label}: '".implode("', '", $extraAttributes)."'";
            }

            throw new \Exception('CSV header mismatch. '.implode(' | ', $messages));
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
