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

class Csv extends Source
{
    private string $filePath;

    /**
     * format: `{databaseId:collectionId}`
     */
    private string $resourceId;

    private Device $deviceForImports;

    private Reader $database;

    public function __construct(
        string $resourceId,
        string $filePath,
        Device $deviceForImports,
        ?UtopiaDatabase $dbForProject
    ) {
        $this->filePath = $filePath;
        $this->resourceId = $resourceId;
        $this->deviceForImports = $deviceForImports;
        $this->database = new DatabaseReader($dbForProject);
    }

    public static function getName(): string
    {
        return 'Csv';
    }

    public static function getSupportedResources(): array
    {
        return [
            Resource::TYPE_DOCUMENT,
        ];
    }

    public function report(array $resources = []): array
    {
        return [];
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
            $this->deviceForImports->delete($this->filePath);
        }
    }

    /**
     * @throws \Exception
     */
    private function exportDocuments(int $batchSize): void
    {
        if (! $this->deviceForImports->exists($this->filePath)) {
            return;
        }

        $stream = fopen($this->filePath, 'r');
        if (! $stream) {
            return;
        }

        $headers = fgetcsv($stream);
        if (! is_array($headers) || count($headers) === 0) {
            fclose($stream);
            return;
        }

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
                // Skip child-side relationships entirely
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

        $buffer = [];

        while (($row = fgetcsv($stream)) !== false) {
            $data = array_combine($headers, $row);
            if ($data === false) {
                continue;
            }

            $parsedData = $data;

            foreach ($data as $key => $value) {
                if (! isset($attributeTypes[$key])) {
                    continue;
                }

                $type = $attributeTypes[$key];
                $parsedValue = trim($value);

                if ($parsedValue === '') {
                    continue;
                }

                if (in_array($key, $manyToManyKeys, true)) {
                    $parsedData[$key] = str_contains($parsedValue, ',')
                        ? array_map('trim', explode(',', $parsedValue))
                        : [$parsedValue];

                    continue;
                }

                switch ($type) {
                    case Attribute::TYPE_INTEGER:
                        $parsedData[$key] = is_numeric($parsedValue) ? (int) $parsedValue : null;
                        break;

                    case Attribute::TYPE_FLOAT:
                        $parsedData[$key] = is_numeric($parsedValue) ? (float) $parsedValue : null;
                        break;

                    case Attribute::TYPE_BOOLEAN:
                        $parsedData[$key] = filter_var($parsedValue, FILTER_VALIDATE_BOOLEAN);
                        break;

                    default:
                        break;
                }
            }

            $permissions = [];
            $documentId = $parsedData['$id'] ?? 'unique()';

            if (isset($parsedData['$permissions'])) {
                $permissions = $this->parsePermissions($parsedData['$permissions']);
            }

            unset($parsedData['$id']);
            unset($parsedData['$permissions']);

            $document = new Document($documentId, $collection, $parsedData, $permissions);
            $buffer[] = $document;

            if (count($buffer) === $batchSize) {
                $this->callback($buffer);
                $buffer = [];
            }
        }

        fclose($stream);

        if (! empty($buffer)) {
            $this->callback($buffer);
        }
    }

    /**
     * Parses a stringified permission array into a string[].
     *
     * Example:
     * ```
     * "[read(\"user:user1234\"),read(\"user:user4321\")]"
     * ```
     * Into:
     * ```
     * [
     *   "read(\"user:user1234\")",
     *   "read(\"user:user4321\")"
     * ]
     * ```
     *
     * @param  string  $raw
     * @return string[]
     */
    private function parsePermissions(string $raw): array
    {
        $raw = trim($raw, ' "[]');

        if (empty($raw)) {
            return [];
        }

        $parts = preg_split('/,(?![^(]*\))/', $raw);

        return array_map(function ($item) {
            $item = trim($item);

            return str_replace('\"', '"', $item);
        }, $parts);
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
}
