<?php

namespace Utopia\Migration\Sources;

use Utopia\Database\Database as UtopiaDatabase;
use Utopia\Migration\Exception;
use Utopia\Migration\Resource as UtopiaResource;
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
            UtopiaResource::TYPE_ROW,
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

        $report[UtopiaResource::TYPE_ROW] = $rowCount;

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
            if (UtopiaResource::isSupported(UtopiaResource::TYPE_ROW, $resources)) {
                $this->exportRows($batchSize);
            }
        } catch (\Throwable $e) {
            $this->addError(
                new Exception(
                    UtopiaResource::TYPE_ROW,
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

        $arrayKeys = [];
        $columnTypes = [];
        $manyToManyKeys = [];

        foreach ($columns as $column) {
            $key = $column['key'];
            $type = $column['type'];
            $isArray = $column['array'] ?? false;
            $relationSide = $column['side'] ?? '';
            $relationType = $column['relationType'] ?? '';

            if (
                $type === Column::TYPE_RELATIONSHIP &&
                $relationSide === UtopiaDatabase::RELATION_SIDE_CHILD
            ) {
                continue;
            }

            $columnTypes[$key] = $type;

            if (
                $type === Column::TYPE_RELATIONSHIP &&
                $relationType === 'manyToMany' &&
                $relationSide === 'parent'
            ) {
                $manyToManyKeys[] = $key;
            }

            if ($isArray && $type !== Column::TYPE_RELATIONSHIP) {
                $arrayKeys[] = $key;
            }
        }

        $this->withCSVStream(function ($stream, $delimiter) use ($columnTypes, $manyToManyKeys, $arrayKeys, $table, $batchSize) {
            $headers = fgetcsv($stream);
            if (! is_array($headers) || count($headers) === 0) {
                return;
            }

            $this->validateCSVHeaders($headers, $columnTypes);

            $buffer = [];

            while (($csvRowItem = fgetcsv(stream: $stream, separator: $delimiter)) !== false) {
                if (count($csvRowItem) !== count($headers)) {
                    throw new \Exception('CSV row does not match the number of header columns.');
                }

                $data = array_combine($headers, $csvRowItem);
                if ($data === false) {
                    continue;
                }

                $parsedData = $data;

                foreach ($data as $key => $value) {
                    $parsedValue = trim($value);
                    $type = $columnTypes[$key] ?? null;

                    if (! isset($type)) {
                        continue;
                    }

                    if (in_array($key, $manyToManyKeys, true)) {
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

                    if (in_array($key, $arrayKeys, true)) {
                        if ($parsedValue === '') {
                            $parsedData[$key] = [];
                        } else {
                            $arrayValues = str_getcsv($parsedValue);
                            $arrayValues = array_map('trim', $arrayValues);

                            $parsedData[$key] = array_map(function ($item) use ($type) {
                                return match ($type) {
                                    Column::TYPE_INTEGER => is_numeric($item) ? (int) $item : null,
                                    Column::TYPE_FLOAT => is_numeric($item) ? (float) $item : null,
                                    Column::TYPE_BOOLEAN => filter_var($item, FILTER_VALIDATE_BOOLEAN),
                                    default => $item,
                                };
                            }, $arrayValues);
                        }
                        continue;
                    }

                    if ($parsedValue !== '') {
                        $parsedData[$key] = match ($type) {
                            Column::TYPE_INTEGER => is_numeric($parsedValue) ? (int) $parsedValue : null,
                            Column::TYPE_FLOAT => is_numeric($parsedValue) ? (float) $parsedValue : null,
                            Column::TYPE_BOOLEAN => filter_var($parsedValue, FILTER_VALIDATE_BOOLEAN),
                            default => $parsedValue,
                        };
                    }
                }

                $rowId = $parsedData['$id'] ?? 'unique()';

                // `$id`, `$permissions` in the doc can cause issues!
                unset($parsedData['$id'], $parsedData['$permissions']);

                $row = new Row($rowId, $table, $parsedData);
                $buffer[] = $row;

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
     * @param callable(resource $stream, string $delimiter): void $callback
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

        $delimiter = $this->delimiter($stream);

        try {
            $callback($stream, $delimiter);
        } finally {
            \fclose($stream);
        }
    }

    /**
     * @throws \Exception
     */
    private function validateCSVHeaders(array $headers, array $columnTypes): void
    {
        $expectedColumns = array_keys($columnTypes);

        // Ignore keys like $id, $permissions, etc.
        $filteredHeaders = array_filter($headers, fn ($key) => ! str_starts_with($key, '$'));

        $extraColumns = array_diff($filteredHeaders, $expectedColumns);
        $missingColumns = array_diff($expectedColumns, $filteredHeaders);

        if (! empty($missingColumns) || ! empty($extraColumns)) {
            $messages = [];

            if (! empty($missingColumns)) {
                $label = count($missingColumns) === 1 ? 'Missing column' : 'Missing columns';
                $messages[] = "{$label}: '".implode("', '", $missingColumns)."'";
            }

            if (! empty($extraColumns)) {
                $label = count($extraColumns) === 1 ? 'Unexpected column' : 'Unexpected columns';
                $messages[] = "{$label}: '".implode("', '", $extraColumns)."'";
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

    /**
     * @param resource $stream
     * @return string
     */
    private function delimiter($stream): string
    {
        /**
         * widely used options, from here -
         *
         * https://stackoverflow.com/a/15946087/6819340
         */
        $delimiters = [',', ';', "\t", '|'];

        $sampleLines = [];

        for ($i = 0; $i < 5 && !feof($stream); $i++) {
            $line = fgets($stream);
            if ($line === false) {
                break;
            }

            $line = trim($line);

            // empty line, skip for sampling
            if (empty($line)) {
                $i--;
                continue;
            }

            $sampleLines[] = $line;
        }

        /**
         * reset to top again because we need to process
         * the same file later again if everything goes OK here!
         */
        rewind($stream);

        if (empty($sampleLines)) {
            return ',';
        }

        $delimiterScores = [];

        foreach ($delimiters as $delimiter) {
            $columnCounts = [];
            $totalFields = 0;
            $usableFields = 0;

            foreach ($sampleLines as $line) {
                // delimiter doesn't exist
                if (!str_contains($line, $delimiter)) {
                    $fields = [$line];
                } else {
                    $fields = str_getcsv($line, $delimiter);
                }

                $fieldCount = count($fields);
                $columnCounts[] = $fieldCount;
                $totalFields += $fieldCount;

                // Count fields that make some sense i.e.
                // longer than 1 char or single alphanumeric
                foreach ($fields as $field) {
                    $trimmed = trim($field);
                    if (strlen($trimmed) > 1) {
                        $usableFields++;
                    }
                }
            }

            $sampleCount = count($columnCounts);
            $avgColumns = $totalFields / $sampleCount;

            // short-circuit
            // if the delimiter doesn't split anything
            if ($avgColumns <= 1) {
                $delimiterScores[$delimiter] = 0;
                continue;
            }

            // check consistency
            if ($sampleCount <= 1) {
                $consistencyScore = 1.0;
            } else {
                $variance = 0;
                foreach ($columnCounts as $count) {
                    $variance += pow($count - $avgColumns, 2);
                }

                // oof, math!
                $stddev = sqrt($variance / $sampleCount);
                $coefficientOfVariation = $stddev / $avgColumns;

                // lower variance = higher score
                $consistencyScore = 1.0 / (1.0 + $coefficientOfVariation * 2);
            }

            $qualityScore = $totalFields > 0 ? $usableFields / $totalFields : 0.0;

            $delimiterScores[$delimiter] = $consistencyScore * $qualityScore;
        }

        // sort as per score
        arsort($delimiterScores);

        // get the first
        $bestDelimiter = key($delimiterScores);

        return ($bestDelimiter && $delimiterScores[$bestDelimiter] > 0) ? $bestDelimiter : ',';
    }
}
