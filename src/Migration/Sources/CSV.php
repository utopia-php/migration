<?php

namespace Utopia\Migration\Sources;

use Utopia\Database\Database as UtopiaDatabase;
use Utopia\Migration\Exception;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Database\Column;
use Utopia\Migration\Resources\Database\Database;
use Utopia\Migration\Resources\Database\Row;
use Utopia\Migration\Resources\Database\Table;
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
            Resource::TYPE_ROW,
        ];
    }

    // called before the `exportGroupDatabases`.
    public function report(array $resources = []): array
    {
        $report = [];

        if (! $this->device->exists($this->filePath)) {
            return $report;
        }

        $file = new \SplFileObject($this->filePath, 'r');
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);

        $file->seek(PHP_INT_MAX);
        $rowCount = max(0, $file->key());

        $report[Resource::TYPE_ROW] = $rowCount;

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
            if (\in_array(Resource::TYPE_ROW, $resources)) {
                $this->exportRows($batchSize);
            }
        } catch (\Throwable $e) {
            $this->addError(
                new Exception(
                    Resource::TYPE_ROW,
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
    private function exportRows(int $batchSize): void
    {

        $columns = [];
        $lastColumn = null;

        [$databaseId, $tableId] = explode(':', $this->resourceId);
        $database = new Database($databaseId, '');
        $table = new Table($database, '', $tableId);

        while (true) {
            $queries = [$this->database->queryLimit($batchSize)];
            if ($lastColumn) {
                $queries[] = $this->database->queryCursorAfter($lastColumn);
            }

            $fetched = $this->database->listColumns($table, $queries);
            if (empty($fetched)) {
                break;
            }

            array_push($columns, ...$fetched);
            $lastColumn = $fetched[count($fetched) - 1];

            if (count($fetched) < $batchSize) {
                break;
            }
        }

        $columnTypes = [];
        $manyToManyKeys = [];

        foreach ($columns as $column) {
            $key = $column['key'];

            if (
                $column['type'] === Column::TYPE_RELATIONSHIP &&
                ($column['side'] ?? '') === UtopiaDatabase::RELATION_SIDE_CHILD
            ) {
                continue;
            }

            $columnTypes[$key] = $column['type'];

            if (
                $column['type'] === Column::TYPE_RELATIONSHIP &&
                ($column['relationType'] ?? '') === 'manyToMany' &&
                ($column['side'] ?? '') === 'parent'
            ) {
                $manyToManyKeys[] = $key;
            }
        }

        $this->withCSVStream(function ($stream) use ($columnTypes, $manyToManyKeys, $table, $batchSize) {
            $headers = fgetcsv($stream);
            if (! is_array($headers) || count($headers) === 0) {
                return;
            }

            $this->validateCSVHeaders($headers, $columnTypes);

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
                    $type = $columnTypes[$key] ?? null;

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
                        Column::TYPE_INTEGER => is_numeric($parsedValue) ? (int) $parsedValue : null,
                        Column::TYPE_FLOAT => is_numeric($parsedValue) ? (float) $parsedValue : null,
                        Column::TYPE_BOOLEAN => filter_var($parsedValue, FILTER_VALIDATE_BOOLEAN),
                        default => $parsedValue,
                    };
                }

                $documentId = $parsedData['$id'] ?? 'unique()';

                // `$id`, `$permissions` in the doc can cause issues!
                unset($parsedData['$id'], $parsedData['$permissions']);

                $document = new Row($documentId, $table, $parsedData);
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
                $label = count($missingAttributes) === 1 ? 'Missing column' : 'Missing columns';
                $messages[] = "{$label}: '".implode("', '", $missingAttributes)."'";
            }

            if (! empty($extraAttributes)) {
                $label = count($extraAttributes) === 1 ? 'Unexpected column' : 'Unexpected columns';
                $messages[] = "{$label}: '".implode("', '", $extraAttributes)."'";
            }

            throw new \Exception('CSV header mismatch. '.implode(' | ', $messages));
        }
    }
}
