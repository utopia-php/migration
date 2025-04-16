<?php

namespace Utopia\Migration\Sources;

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

        $this->withCsvStream(function ($stream) use (&$report) {
            $headers = fgetcsv($stream);
            if (! is_array($headers) || count($headers) === 0) {
                return;
            }

            $rowCount = 0;
            while (fgetcsv($stream) !== false) {
                $rowCount++;
            }

            $report[Resource::TYPE_DOCUMENT] = $rowCount;
        });

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
            if (\in_array(Resource::TYPE_DOCUMENT, $resources)) {
                $this->exportDocuments($batchSize);
            }
        } catch (\Throwable $e) {
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

            $buffer = [];

            while (($row = fgetcsv($stream)) !== false) {
                $data = array_combine($headers, $row);
                if ($data === false) {
                    continue;
                }

                $parsedData = $data;

                foreach ($data as $key => $value) {
                    $parsedValue = trim($value);
                    $type = $attributeTypes[$key];

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
}
