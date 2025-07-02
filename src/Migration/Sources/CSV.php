<?php

namespace Utopia\Migration\Sources;

use Utopia\CLI\Console;
use Utopia\Database\Database as UtopiaDatabase;
use Utopia\Migration\Exception;
use Utopia\Migration\Resource;
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

class CSV extends Source
{
    private string $filePath;

    /**
     * format: `{databaseId:collectionId}`
     */
    private string $resourceId;

    private Device $device;

    private Reader $database;

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
            Resource::TYPE_DOCUMENT,
        ];
    }

    // called before the `exportGroupDatabases`.
    public function report(array $resources = []): array
    {
        $report = [];

        if (! $this->device->exists($this->filePath)) {
            Console::log(json_encode([
                'stage' => 'csv#report',
                'status' => "exiting, file doesn't exist." . ", path: " . $this->filePath
            ], JSON_PRETTY_PRINT));

            return $report;
        }

        $file = new \SplFileObject($this->filePath, 'r');
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);

        $file->seek(PHP_INT_MAX);
        $rowCount = max(0, $file->key());

        $report[Resource::TYPE_DOCUMENT] = $rowCount;

        Console::log(json_encode(['stage' => 'csv#report', 'report' => $report], JSON_PRETTY_PRINT));

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
        Console::log(json_encode([
            'stage' => 'csv#exportGroupDatabases',
            'resources' => $resources,
            'hasDocumentInScope' => in_array(Resource::TYPE_DOCUMENT, $resources)
        ], JSON_PRETTY_PRINT));

        try {
            if (\in_array(Resource::TYPE_DOCUMENT, $resources)) {
                $this->exportDocuments($batchSize);
            }
        } catch (\Throwable $e) {
            Console::log(json_encode([
                'stage' => 'csv#exportGroupDatabases',
                'status' => 'error',
                'trace' => $e->getTraceAsString(),
            ], JSON_PRETTY_PRINT));

            $this->addError(
                new Exception(
                    Resource::TYPE_DOCUMENT,
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

        Console::log(json_encode([
            'stage' => 'csv#exportDocuments',
            'databaseId' => $databaseId,
            'collectionId' => $collectionId,
        ], JSON_PRETTY_PRINT));

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

        $attributeTypes = [];
        $manyToManyKeys = [];

        Console::log(json_encode([
            'stage' => 'csv#exportDocuments',
            'attributes' => $attributes,
        ], JSON_PRETTY_PRINT));

        foreach ($attributes as $attribute) {
            $key = $attribute['key'];

            if (
                $attribute['type'] === Attribute::TYPE_RELATIONSHIP &&
                ($attribute['side'] ?? '') === UtopiaDatabase::RELATION_SIDE_CHILD
            ) {
                continue;
            }

            $attributeTypes[$key] = $attribute['type'];

            if (
                $attribute['type'] === Attribute::TYPE_RELATIONSHIP &&
                ($attribute['relationType'] ?? '') === 'manyToMany' &&
                ($attribute['side'] ?? '') === 'parent'
            ) {
                $manyToManyKeys[] = $key;
            }
        }

        $this->withCSVStream(function ($stream) use ($attributeTypes, $manyToManyKeys, $collection, $batchSize) {
            $headers = fgetcsv($stream);
            if (! is_array($headers) || count($headers) === 0) {
                return;
            }

            $this->validateCSVHeaders($headers, $attributeTypes);

            $buffer = [];

            while (($row = fgetcsv($stream)) !== false) {
                if (count($row) !== count($headers)) {
                    throw new \Exception('CSV row does not match the number of header columns.');
                }

                $data = array_combine($headers, $row);
                if ($data === false) {
                    continue;
                }

                $parsedData = $data;

                foreach ($data as $key => $value) {
                    $parsedValue = trim($value);
                    $type = $attributeTypes[$key] ?? null;

                    if (! isset($type) || $parsedValue === '') {
                        continue;
                    }

                    if (in_array($key, $manyToManyKeys, true)) {
                        $parsedData[$key] = str_contains($parsedValue, ',')
                            ? array_map('trim', explode(',', $parsedValue))
                            : [$parsedValue];

                        continue;
                    }

                    $parsedData[$key] = match ($type) {
                        Attribute::TYPE_INTEGER => is_numeric($parsedValue) ? (int) $parsedValue : null,
                        Attribute::TYPE_FLOAT => is_numeric($parsedValue) ? (float) $parsedValue : null,
                        Attribute::TYPE_BOOLEAN => filter_var($parsedValue, FILTER_VALIDATE_BOOLEAN),
                        default => $parsedValue,
                    };
                }

                $documentId = $parsedData['$id'] ?? 'unique()';

                // `$id`, `$permissions` in the doc can cause issues!
                unset($parsedData['$id'], $parsedData['$permissions']);

                $document = new Document($documentId, $collection, $parsedData);
                $buffer[] = $document;

                if (count($buffer) === $batchSize) {
                    $this->callback($buffer);

                    Console::log(json_encode([
                        'stage' => 'csv#withCSVStream#callback',
                        'buffer' => $buffer,
                    ], JSON_PRETTY_PRINT));

                    $buffer = [];
                }
            }

            if (! empty($buffer)) {
                $this->callback($buffer);

                Console::log(json_encode([
                    'stage' => 'csv#withCSVStream#callback#2',
                    'buffer' => $buffer,
                ], JSON_PRETTY_PRINT));
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

    private function withCsvStream(callable $fn): void
    {
        if (! $this->device->exists($this->filePath)) {
            return;
        }

        $stream = fopen($this->filePath, 'r');
        if (! $stream) {
            return;
        }

        try {
            $fn($stream);
        } finally {
            fclose($stream);
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

        Console::log(json_encode([
            'stage' => 'csv#validateCSVHeaders',
            'missingAttributes' => $missingAttributes,
            'extraAttributes' => $extraAttributes,
        ], JSON_PRETTY_PRINT));

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
}
