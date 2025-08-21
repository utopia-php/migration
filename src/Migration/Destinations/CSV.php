<?php

namespace Utopia\Migration\Destinations;

use Utopia\CLI\Console;
use Utopia\Database\Exception\Authorization;
use Utopia\Database\Exception\Conflict;
use Utopia\Database\Exception\Structure;
use Utopia\Migration\Destination;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Database\Row;
use Utopia\Migration\Transfer;
use Utopia\Storage\Device;
use Utopia\Storage\Device\Local;

class CSV extends Destination
{
    protected Device $deviceForMigrations;
    protected string $resourceId;
    protected Local $local;

    protected array $allowedAttributes = [];

    /**
     * @throws Authorization
     * @throws Structure
     * @throws Conflict
     * @throws \Exception
     */
    public function __construct(
        Device $deviceForExports,
        string $resourceId,
        array $allowedAttributes = []
    ) {
        $this->deviceForMigrations = $deviceForExports;
        $this->resourceId = $resourceId;
        $this->local = new Local(\sys_get_temp_dir() . '/csv_export_' . uniqid());
        $this->local->setTransferChunkSize(Transfer::STORAGE_MAX_CHUNK_SIZE);
        $this->createDirectory($this->local->getRoot());

        foreach ($allowedAttributes as $attribute) {
            $this->allowedAttributes[$attribute] = true;
        }
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

    public function report(array $resources = []): array
    {
        return [];
    }

    /**
     * @param array<Row> $resources
     * @throws \JsonException
     * @throws \Exception
     */
    protected function import(array $resources, callable $callback): void
    {
        $handle = null; // file handle
        $buffer = ['lines' => [], 'size' => 0];  // Buffer for batching writes
        $bufferBytes = 1024 * 1024; // 1MB
        $log = $this->local->getRoot() . '/' . $this->resourceId . '.csv';

        $flushBuffer = function () use ($log, &$handle, &$buffer) {
            if (empty($buffer['lines'])) {
                return;
            }
            try {
                if (!isset($handle)) {
                    $handle = \fopen($log, 'a');
                    if ($handle === false) {
                        throw new \Exception("Failed to open file for writing: $log");
                    }
                }

                $content = \implode('', $buffer['lines']);
                if (\fwrite($handle, $content) === false) {
                    throw new \Exception("Failed to write to file: $log");
                }

                $buffer = [
                    'lines' => [],
                    'size' => 0
                ];
            } catch (\Exception $e) {
                // Close handle on error
                if (isset($handle)) {
                    \fclose($handle);
                    unset($handle);
                }
                throw $e;
            }
        };

        try {
            foreach ($resources as $resource) {
                $csvData = $this->resourceToCSVData($resource);

                // Write headers if this is the first row of the file
                if (!isset($csvHeader)) {
                    $headers = $this->toCSV(\array_keys($csvData));
                    $buffer['lines'][] = $headers;
                    $buffer['size'] += \strlen($headers);
                    $csvHeader = true;
                }

                $dataLine = $this->toCSV(\array_values($csvData));
                $buffer['lines'][] = $dataLine;
                $buffer['size'] += \strlen($dataLine);

                if ($buffer['size'] >= $bufferBytes) {
                    $flushBuffer();
                }

                $resource->setStatus(Resource::STATUS_SUCCESS);
                if (isset($this->cache)) {
                    $this->cache->update($resource);
                }
            }

            // Flush any remaining buffered lines
            if (!empty($buffer['lines'])) {
                $flushBuffer();
            }
        } finally {
            if (\is_resource($handle)) {
                \fclose($handle);
            }
        }

        $callback($resources);
    }

    /**
     * @throws \Exception
     */
    public function shutdown(): void
    {
        if (!\file_exists($this->local->getRoot())) {
            throw new \Exception('Nothing to upload');
        }

        $filename = $this->resourceId . '.csv';

        try {
            // Transfer expects relative paths within each device
            $result = $this->local->transfer(
                $filename,
                $filename,
                $this->deviceForMigrations
            );

            if ($result === false) {
                throw new \Exception('Error uploading to ' . $this->deviceForMigrations->getRoot() . '/' . $filename);
            }

            if (!$this->deviceForMigrations->exists($filename)) {
                throw new \Exception('File not found on destination: ' . $filename);
            }
        } finally {
            // Clean up the temporary directory
            if (!$this->local->deletePath('') || \file_exists($this->local->getRoot())) {
                Console::error('Error deleting: ' . $this->local->getRoot());
                throw new \Exception('Error deleting: ' . $this->local->getRoot());
            }
        }
    }

    /**
     * Helper to ensure a directory exists.
     * @throws \Exception
     */
    protected function createDirectory(string $path): void
    {
        if (!\file_exists($path)) {
            if (!\mkdir($path, 0755, true)) {
                throw new \Exception('Error creating directory: ' . $path);
            }
        }
    }

    /**
     * Convert a resource to CSV-compatible data
     */
    protected function resourceToCSVData(Row $resource): array
    {
        $data = [
            '$id' => $resource->getId(),
            '$permissions' => $resource->getPermissions(),
        ];
        
        // Add all attributes if no filter specified, otherwise only allowed ones
        if (empty($this->allowedAttributes)) {
            $data = \array_merge($data, $resource->getData());
        } else {
            foreach ($resource->getData() as $key => $value) {
                if (isset($this->allowedAttributes[$key])) {
                    $data[$key] = $value;
                }
            }
        }

        foreach ($data as $key => $value) {
            $data[$key] = $this->convertValueToCSV($value);
        }

        return $data;
    }

    /**
     * Convert a single value to CSV-compatible format
     */
    protected function convertValueToCSV(mixed $value): string
    {
        if (\is_null($value)) {
            return 'null';
        }
        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (\is_array($value)) {
            return $this->convertArrayToCSV($value);
        }
        if (\is_object($value)) {
            return $this->convertObjectToCSV($value);
        }
        return (string)$value;
    }

    /**
     * Convert array to CSV format
     */
    protected function convertArrayToCSV(array $value): string
    {
        if (empty($value)) {
            return '';
        }
        if (isset($value['$id'])) {
            return $value['$id'];
        }
        return \json_encode($value);
    }

    /**
     * Convert object to CSV format
     */
    protected function convertObjectToCSV($value): string
    {
        if ($value instanceof Row) {
            return $value->getId();
        }
        return \json_encode($value);
    }

    /**
     * Convert array to CSV line with proper escaping
     * Uses standard CSV format with double-quote escaping
     */
    protected function toCSV(array $array): string
    {
        $output = [];
        foreach ($array as $value) {
            $output[] = $this->escapeForCSV($value);
        }
        return \implode(',', $output) . "\n";
    }

    /**
     * Escape a single value for CSV format
     */
    protected function escapeForCSV(string $value): string
    {
        if (\strpbrk($value, ",\n\r\"") !== false) {
            // Escape quotes by doubling them (CSV standard)
            $escaped = \str_replace('"', '""', $value);
            return '"' . $escaped . '"';
        }
        return $value;
    }
}
