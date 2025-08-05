<?php

namespace Utopia\Migration\Destinations;

use Utopia\CLI\Console;
use Utopia\Database\Exception\Authorization;
use Utopia\Database\Exception\Conflict;
use Utopia\Database\Exception\Structure;
use Utopia\Migration\Destination;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Database\Document;
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
        $this->local = new Local('/' . $resourceId . '.csv');
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
            Resource::TYPE_DOCUMENT,
        ];
    }

    public function report(array $resources = []): array
    {
        return [];
    }

    public function shutdown(): void
    {
        if (!\file_exists($this->local->getRoot())) {
            throw new \Exception('Nothing to upload');
        }

        $filename = $this->resourceId . '.csv';

        try {
            $destination = $this->deviceForMigrations->getRoot() . '/' . $filename;
            $result = $this->local->transfer($filename, $destination, $this->deviceForMigrations);

            if ($result === false) {
                throw new \Exception('Error uploading to ' . $destination);
            }

            if (!$this->deviceForMigrations->exists($destination)) {
                throw new \Exception('File not found on destination: ' . $destination);
            }
        } finally {
            if (!$this->local->deletePath('') || \file_exists($this->local->getRoot())) {
                Console::error('Error deleting: ' . $this->local->getRoot());
                throw new \Exception('Error deleting: ' . $this->local->getRoot());
            }
        }
    }

    /**
     * @param array<Document> $resources
     * @throws \JsonException
     * @throws \Exception
     */
    protected function import(array $resources, callable $callback): void
    {
        $handles = []; // file path => file handle
        $buffers = []; // file path => ['lines' => array, 'size' => int]
        $bufferBytes = 1024 * 1024; // 1MB
        $csvHeaders = []; // file path => bool (to track if headers written)
        $csvDataCache = []; // Cache for CSV data to avoid repeated processing

        $flushBuffer = function (string $file) use (&$handles, &$buffers) {
            if (empty($buffers[$file]['lines'])) {
                return;
            }

            try {
                if (!isset($handles[$file])) {
                    $handles[$file] = \fopen($file, 'a');
                    if ($handles[$file] === false) {
                        throw new \Exception("Failed to open file for writing: $file");
                    }
                }

                $content = \implode('', $buffers[$file]['lines']);
                if (\fwrite($handles[$file], $content) === false) {
                    throw new \Exception("Failed to write to file: $file");
                }

                $buffers[$file] = [
                    'lines' => [],
                    'size' => 0
                ];
            } catch (\Exception $e) {
                // Close handle on error
                if (isset($handles[$file])) {
                    \fclose($handles[$file]);
                    unset($handles[$file]);
                }
                throw $e;
            }
        };

        try {
            foreach ($resources as $resource) {
                $log = $this->local->getRoot() . '/' . $resource->getGroup() . '-' . $resource->getName() . '.csv';

                if (!isset($buffers[$log])) {
                    $buffers[$log] = ['lines' => [], 'size' => 0];
                }

                // Write headers if this is the first record for this file
                if (!isset($csvHeaders[$log])) {
                    $csvData = $this->resourceToCSVData($resource);
                    $csvDataCache[$resource->getId()] = $csvData;

                    $headerLine = $this->toCSV(array_keys($csvData));
                    $buffers[$log]['lines'][] = $headerLine;
                    $buffers[$log]['size'] += strlen($headerLine);
                    $csvHeaders[$log] = true;
                } else {
                    // Use cached data if available, otherwise process
                    if (!isset($csvDataCache[$resource->getId()])) {
                        $csvData = $this->resourceToCSVData($resource);
                        $csvDataCache[$resource->getId()] = $csvData;
                    } else {
                        $csvData = $csvDataCache[$resource->getId()];
                    }
                }

                $dataLine = $this->toCSV(array_values($csvData));
                $buffers[$log]['lines'][] = $dataLine;
                $buffers[$log]['size'] += strlen($dataLine);

                if ($buffers[$log]['size'] >= $bufferBytes) {
                    $flushBuffer($log);
                }

                $resource->setStatus(Resource::STATUS_SUCCESS);
                if (isset($this->cache)) {
                    $this->cache->update($resource);
                }
            }

            // Flush remaining buffers
            foreach ($buffers as $file => $bufferData) {
                if (!empty($bufferData['lines'])) {
                    $flushBuffer($file);
                }
            }

        } finally {
            // Ensure all handles are closed
            foreach ($handles as $handle) {
                if (is_resource($handle)) {
                    \fclose($handle);
                }
            }
        }

        $callback($resources);
    }

    /**
     * Helper to ensure a directory exists.
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
    protected function resourceToCSVData(Document $resource): array
    {
        $data = [
            '$id' => $resource->getId(),
            '$permissions' => $resource->getPermissions(),
            ...\array_filter($resource->getData(), function ($key) {
                return isset($this->allowedAttributes[$key]);
            }, ARRAY_FILTER_USE_KEY)
        ];

        $results = [];

        foreach ($data as $key => $value) {
            $results[$key] = $this->convertValueToCSV($value);
        }

        return $results;
    }

    /**
     * Convert a single value to CSV-compatible format
     */
    protected function convertValueToCSV(mixed $value): string
    {
        if (\is_array($value)) {
            return $this->convertMapToCSV($value);
        }
        if (\is_object($value)) {
            return $this->convertObjectToCSV($value);
        }
        return $this->escape($value);
    }

    /**
     * Convert array to CSV format
     */
    protected function convertMapToCSV(array $value): string
    {
        if (empty($value)) {
            return '""';
        }
        if (isset($value['$id'])) {
            return $this->escape($value['$id']);
        }
        if (!\array_is_list($value)) {
            return $this->escape(\json_encode($value));
        }
        return $this->convertListToCSV($value);
    }

    /**
     * Convert indexed array to CSV format
     */
    protected function convertListToCSV(array $value): string
    {
        $count = \count($value);
        if ($count === 0) {
            return '""';
        }

        $processed = [];
        for ($i = 0; $i < $count; $i++) {
            if (\is_array($value[$i]) && isset($value['$id'])) {
                $processed[] = $value['$id'];
                continue;
            }
            $processed[] = $this->escape($value[$i]);
        }

        return '"' . implode(',', $processed) . '"';
    }

    /**
     * Convert object to CSV format
     */
    protected function convertObjectToCSV($value): string
    {
        if ($value instanceof Document) {
            return $this->escape($value->getId());
        }
        return $this->escape(\json_encode($value));
    }

    /**
     * Convert array to CSV line with proper escaping
     */
    protected function toCSV(array $array): string
    {
        if (empty($array)) {
            return "\n";
        }

        $line = $this->escape($array[0]);
        $count = \count($array);

        for ($i = 1; $i < $count; $i++) {
            $line .= ',' . $this->escape($array[$i]);
        }

        return $line . "\n";
    }

    /**
     * Safely escape a value for CSV (backslash-escape style)
     * - null/empty -> empty string
     * - bool -> true/false
     * - numeric -> raw
     * - strings with special chars -> quoted with backslash escapes
     */
    protected function escape($value): string
    {
        if (\is_null($value) || $value === '') {
            return '';
        }
        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (\is_numeric($value)) {
            return (string)$value;
        }

        $stringValue = (string)$value;

        // Escape backslashes first, then quotes
        $escaped = \str_replace(['\\', '"'], ['\\\\', '\\"'], $stringValue);

        // Needs quoting if it contains commas, line breaks, quotes, or backslashes
        if (\strpbrk($stringValue, ",\n\r\"\\") !== false) {
            return '"' . $escaped . '"';
        }

        return $escaped;
    }
}
