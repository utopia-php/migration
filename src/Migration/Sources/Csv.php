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

    private Device $deviceForCsvImports;

    private Reader $database;

    public function __construct(
        string $resourceId,
        string $filePath,
        Device $deviceForCsvImports,
        ?UtopiaDatabase $dbForProject
    ) {
        $this->filePath = $filePath;
        $this->resourceId = $resourceId;
        $this->deviceForCsvImports = $deviceForCsvImports;
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
            $this->deviceForCsvImports->delete($this->filePath);
        }
    }

    /**
     * @throws Exception|\Utopia\Database\Exception
     */
    private function exportDocuments(int $batchSize): void
    {
        if (! $this->deviceForCsvImports->exists($this->filePath)) {
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

        $allAttributes = [];
        $lastAttribute = null;

        [$databaseId, $collectionId] = explode(':', $this->resourceId);
        // TODO: @itznotabug, @jake - do we need to check for permissions here or db handles it?
        $collection = new Collection(new Database($databaseId, ''), '', $collectionId);

        while (true) {
            // paginate over the attributes
            $queries = [$this->database->queryLimit($batchSize)];
            if ($lastAttribute) {
                $queries[] = $this->database->queryCursorAfter($lastAttribute);
            }

            $fetched = $this->database->listAttributes($collection, $queries);
            if (empty($fetched)) {
                break;
            }

            $allAttributes = array_merge($allAttributes, $fetched);
            $lastAttribute = $fetched[count($fetched) - 1];

            if (count($fetched) < $batchSize) {
                break;
            }
        }

        $attributeTypes = [];
        $manyToManyKeys = [];

        foreach ($allAttributes as $attribute) {
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
                    $parsedData[$key] = null;

                    continue;
                }

                // TODO: @itznotabug, @jake - should we support Relationships like these?
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

            $docId = $parsedData['$id'] ?? 'unique()';
            $document = new Document($docId, $collection, $parsedData);

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

    protected function exportGroupStorage(int $batchSize, array $resources): void
    {
        throw new \Exception('Not Implemented');
    }

    protected function exportBuckets(int $batchSize): void
    {
        throw new \Exception('Not Implemented');
    }

    private function exportFiles(int $batchSize): void
    {
        throw new \Exception('Not Implemented');
    }

    private function exportFile(File $file): void
    {
        throw new \Exception('Not Implemented');
    }

    protected function exportGroupFunctions(int $batchSize, array $resources): void
    {
        throw new \Exception('Not Implemented');
    }
}
