<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

// ============================================================================
// CONFIGURATION
// ============================================================================

const BENCHMARK_DIR = __DIR__ . "/tmp-bench";
const ROW_COUNTS = [5, 10, 25, 50, 100, 1000, 10_000, 50_000];

// ============================================================================
// MOCK CLASSES - Simulate framework components
// ============================================================================

class BenchmarkAttribute
{
    public const TYPE_STRING = "string";
    public const TYPE_INTEGER = "integer";
    public const TYPE_FLOAT = "float";
    public const TYPE_BOOLEAN = "boolean";
}

class BenchmarkDocument
{
    public string $id;
    public array $data;

    public function __construct(string $id, array $data)
    {
        $this->id = $id;
        $this->data = $data;
    }
}

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

function createBenchmarkDirectory(): void
{
    if (!is_dir(BENCHMARK_DIR)) {
        mkdir(BENCHMARK_DIR, 0755, true);
    }
}

function cleanupBenchmarkDirectory(): void
{
    if (!is_dir(BENCHMARK_DIR)) {
        return;
    }

    $files = glob(BENCHMARK_DIR . "/*");
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    rmdir(BENCHMARK_DIR);
}

function generateFakeData(string $type, bool $isArray = false): string
{
    if ($isArray) {
        $items = [];
        $count = rand(2, 4);
        for ($i = 0; $i < $count; $i++) {
            $items[] = generateFakeData($type, false);
        }
        return '"' . implode(",", $items) . '"';
    }

    switch ($type) {
        case BenchmarkAttribute::TYPE_STRING:
            $names = ["John", "Jane", "Bob", "Alice", "Charlie"];
            return $names[array_rand($names)] . "_" . rand(1000, 9999);
        case BenchmarkAttribute::TYPE_INTEGER:
            return (string) rand(1, 10000);
        case BenchmarkAttribute::TYPE_FLOAT:
            return number_format(rand(100, 99999) / 100, 2);
        case BenchmarkAttribute::TYPE_BOOLEAN:
            return rand(0, 1) ? "true" : "false";
        default:
            return "default_value";
    }
}

function generateTestCSV(int $rowCount): string
{
    $filename = BENCHMARK_DIR . "/test_{$rowCount}.csv";
    $handle = fopen($filename, "w");

    // 15 columns with mixed types
    $columns = [
        ["name" => "id", "type" => BenchmarkAttribute::TYPE_STRING, "array" => false],
        ["name" => "name", "type" => BenchmarkAttribute::TYPE_STRING, "array" => false],
        ["name" => "email", "type" => BenchmarkAttribute::TYPE_STRING, "array" => false],
        ["name" => "age", "type" => BenchmarkAttribute::TYPE_INTEGER, "array" => false],
        ["name" => "salary", "type" => BenchmarkAttribute::TYPE_FLOAT, "array" => false],
        ["name" => "active", "type" => BenchmarkAttribute::TYPE_BOOLEAN, "array" => false],
        ["name" => "score1", "type" => BenchmarkAttribute::TYPE_INTEGER, "array" => false],
        ["name" => "rating", "type" => BenchmarkAttribute::TYPE_FLOAT, "array" => false],
        ["name" => "verified", "type" => BenchmarkAttribute::TYPE_BOOLEAN, "array" => false],
        ["name" => "tags", "type" => BenchmarkAttribute::TYPE_STRING, "array" => true],
        ["name" => "skills", "type" => BenchmarkAttribute::TYPE_STRING, "array" => true],
        ["name" => "scores", "type" => BenchmarkAttribute::TYPE_INTEGER, "array" => true],
        ["name" => "grades", "type" => BenchmarkAttribute::TYPE_FLOAT, "array" => true],
        ["name" => "department", "type" => BenchmarkAttribute::TYPE_STRING, "array" => false],
        ["name" => "notes", "type" => BenchmarkAttribute::TYPE_STRING, "array" => false],
    ];

    // Write header
    $headers = array_column($columns, "name");
    fputcsv($handle, $headers);

    // Write data rows
    for ($i = 1; $i <= $rowCount; $i++) {
        $row = [];
        foreach ($columns as $column) {
            $row[] = generateFakeData($column["type"], $column["array"]);
        }
        fputcsv($handle, $row);
    }

    fclose($handle);
    return $filename;
}

// ============================================================================
// OLD IMPLEMENTATION - With realistic bottlenecks
// ============================================================================

function processOldImplementation(string $csvPath): int
{
    $handle = fopen($csvPath, "r");
    if (!$handle) {
        return 0;
    }

    $headers = fgetcsv($handle);
    if (!$headers) {
        fclose($handle);
        return 0;
    }

    // BOTTLENECK 1: Arrays for O(n) lookups
    $arrayKeys = ["tags", "skills", "scores", "grades"];
    $attributeTypes = [
        "id" => BenchmarkAttribute::TYPE_STRING,
        "name" => BenchmarkAttribute::TYPE_STRING,
        "email" => BenchmarkAttribute::TYPE_STRING,
        "age" => BenchmarkAttribute::TYPE_INTEGER,
        "salary" => BenchmarkAttribute::TYPE_FLOAT,
        "active" => BenchmarkAttribute::TYPE_BOOLEAN,
        "score1" => BenchmarkAttribute::TYPE_INTEGER,
        "rating" => BenchmarkAttribute::TYPE_FLOAT,
        "verified" => BenchmarkAttribute::TYPE_BOOLEAN,
        "tags" => BenchmarkAttribute::TYPE_STRING,
        "skills" => BenchmarkAttribute::TYPE_STRING,
        "scores" => BenchmarkAttribute::TYPE_INTEGER,
        "grades" => BenchmarkAttribute::TYPE_FLOAT,
        "department" => BenchmarkAttribute::TYPE_STRING,
        "notes" => BenchmarkAttribute::TYPE_STRING,
    ];

    $processedCount = 0;

    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) !== count($headers)) {
            continue;
        }

        $data = array_combine($headers, $row);
        $processedData = [];

        foreach ($data as $key => $value) {
            $trimmedValue = trim($value);
            $type = $attributeTypes[$key] ?? BenchmarkAttribute::TYPE_STRING;

            // BOTTLENECK 2: O(n) in_array lookup for EVERY field
            if (in_array($key, $arrayKeys, true)) {
                // BOTTLENECK 3: Inefficient array processing
                if ($trimmedValue === "" || $trimmedValue === '""') {
                    $processedData[$key] = [];
                } else {
                    $cleanValue = trim($trimmedValue, '"');
                    $items = explode(",", $cleanValue);
                    $processedArray = [];

                    // BOTTLENECK 4: Multiple function calls per array item
                    foreach ($items as $item) {
                        $cleanItem = trim($item);
                        if ($type === BenchmarkAttribute::TYPE_INTEGER) {
                            $processedArray[] = is_numeric($cleanItem) ? (int) $cleanItem : null;
                        } elseif ($type === BenchmarkAttribute::TYPE_FLOAT) {
                            $processedArray[] = is_numeric($cleanItem) ? (float) $cleanItem : null;
                        } elseif ($type === BenchmarkAttribute::TYPE_BOOLEAN) {
                            $processedArray[] = filter_var($cleanItem, FILTER_VALIDATE_BOOLEAN);
                        } else {
                            $processedArray[] = $cleanItem;
                        }
                    }
                    $processedData[$key] = $processedArray;
                }
            } else {
                // BOTTLENECK 5: Inefficient scalar conversion
                if ($trimmedValue !== "") {
                    if ($type === BenchmarkAttribute::TYPE_INTEGER) {
                        $processedData[$key] = is_numeric($trimmedValue) ? (int) $trimmedValue : null;
                    } elseif ($type === BenchmarkAttribute::TYPE_FLOAT) {
                        $processedData[$key] = is_numeric($trimmedValue) ? (float) $trimmedValue : null;
                    } elseif ($type === BenchmarkAttribute::TYPE_BOOLEAN) {
                        $processedData[$key] = filter_var($trimmedValue, FILTER_VALIDATE_BOOLEAN);
                    } else {
                        $processedData[$key] = $trimmedValue;
                    }
                } else {
                    $processedData[$key] = $trimmedValue;
                }
            }
        }

        // BOTTLENECK 6: Object creation with string operations
        $documentId = "doc_" . ($processedCount + 1) . "_" . substr(md5($processedData["id"] ?? ""), 0, 8);
        $document = new BenchmarkDocument($documentId, $processedData);

        $processedCount++;

        // Simulate some processing work
        unset($document, $processedData);
    }

    fclose($handle);
    return $processedCount;
}

// ============================================================================
// OPTIMIZED IMPLEMENTATION - With performance improvements
// ============================================================================

function processOptimizedImplementation(string $csvPath): int
{
    $handle = fopen($csvPath, "r");
    if (!$handle) {
        return 0;
    }

    $headers = fgetcsv($handle);
    if (!$headers) {
        fclose($handle);
        return 0;
    }

    // OPTIMIZATION 1: Pre-build lookup maps for O(1) access
    $arrayKeysMap = [
        "tags" => true,
        "skills" => true,
        "scores" => true,
        "grades" => true,
    ];

    $typeMap = [
        "id" => BenchmarkAttribute::TYPE_STRING,
        "name" => BenchmarkAttribute::TYPE_STRING,
        "email" => BenchmarkAttribute::TYPE_STRING,
        "age" => BenchmarkAttribute::TYPE_INTEGER,
        "salary" => BenchmarkAttribute::TYPE_FLOAT,
        "active" => BenchmarkAttribute::TYPE_BOOLEAN,
        "score1" => BenchmarkAttribute::TYPE_INTEGER,
        "rating" => BenchmarkAttribute::TYPE_FLOAT,
        "verified" => BenchmarkAttribute::TYPE_BOOLEAN,
        "tags" => BenchmarkAttribute::TYPE_STRING,
        "skills" => BenchmarkAttribute::TYPE_STRING,
        "scores" => BenchmarkAttribute::TYPE_INTEGER,
        "grades" => BenchmarkAttribute::TYPE_FLOAT,
        "department" => BenchmarkAttribute::TYPE_STRING,
        "notes" => BenchmarkAttribute::TYPE_STRING,
    ];

    $processedCount = 0;

    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) !== count($headers)) {
            continue;
        }

        $data = array_combine($headers, $row);
        $processedData = [];

        foreach ($data as $key => $value) {
            $trimmedValue = trim($value);

            if (!isset($typeMap[$key])) {
                $processedData[$key] = $trimmedValue;
                continue;
            }

            $type = $typeMap[$key];

            // OPTIMIZATION 3: O(1) lookup instead of O(n) in_array
            if (isset($arrayKeysMap[$key])) {
                // OPTIMIZATION 4: Streamlined array processing
                if ($trimmedValue === "" || $trimmedValue === '""') {
                    $processedData[$key] = [];
                } else {
                    $cleanValue = trim($trimmedValue, '"');
                    $items = explode(",", $cleanValue);
                    $processedArray = [];

                    // OPTIMIZATION 5: Direct type conversion without repeated conditionals
                    foreach ($items as $item) {
                        $cleanItem = trim($item);
                        $processedArray[] = match ($type) {
                            BenchmarkAttribute::TYPE_INTEGER => is_numeric($cleanItem) ? (int) $cleanItem : null,
                            BenchmarkAttribute::TYPE_FLOAT => is_numeric($cleanItem) ? (float) $cleanItem : null,
                            BenchmarkAttribute::TYPE_BOOLEAN => filter_var($cleanItem, FILTER_VALIDATE_BOOLEAN),
                            default => $cleanItem,
                        };
                    }
                    $processedData[$key] = $processedArray;
                }
            } else {
                // OPTIMIZATION 6: Efficient scalar conversion with match
                if ($trimmedValue !== "") {
                    $processedData[$key] = match ($type) {
                        BenchmarkAttribute::TYPE_INTEGER => is_numeric($trimmedValue) ? (int) $trimmedValue : null,
                        BenchmarkAttribute::TYPE_FLOAT => is_numeric($trimmedValue) ? (float) $trimmedValue : null,
                        BenchmarkAttribute::TYPE_BOOLEAN => filter_var($trimmedValue, FILTER_VALIDATE_BOOLEAN),
                        default => $trimmedValue,
                    };
                } else {
                    $processedData[$key] = $trimmedValue;
                }
            }
        }

        // OPTIMIZATION 7: Simpler document ID generation
        $documentId = "doc_" . ($processedCount + 1);
        $document = new BenchmarkDocument($documentId, $processedData);

        $processedCount++;

        // Clean up efficiently
        unset($document, $processedData);
    }

    fclose($handle);
    return $processedCount;
}

// ============================================================================
// BENCHMARKING FUNCTIONS
// ============================================================================

function runSingleBenchmark(int $rowCount): void
{
    echo "=== Testing {$rowCount} rows ===\n";

    // Generate test CSV
    $csvPath = generateTestCSV($rowCount);
    $fileSize = filesize($csvPath);

    echo "File size: " . round($fileSize / 1024 / 1024, 2) . " MB\n";

    // Reset memory and force garbage collection
    gc_collect_cycles();

    // Benchmark OLD implementation
    $startTime = microtime(true);
    $startMemory = memory_get_usage(true);

    $oldCount = processOldImplementation($csvPath);

    $oldTime = microtime(true) - $startTime;
    $oldMemory = memory_get_peak_usage(true);

    echo "OLD Implementation:\n";
    echo "  Time: " . number_format($oldTime, 6) . " seconds\n";
    echo "  Memory: " . round($oldMemory / 1024 / 1024, 1) . " MB\n";
    echo "  Documents: {$oldCount}\n";

    // Clean memory between tests
    gc_collect_cycles();

    // Benchmark OPTIMIZED implementation
    $startTime = microtime(true);
    $startMemory = memory_get_usage(true);

    $optCount = processOptimizedImplementation($csvPath);

    $optTime = microtime(true) - $startTime;
    $optMemory = memory_get_peak_usage(true);

    echo "OPTIMIZED Implementation:\n";
    echo "  Time: " . number_format($optTime, 6) . " seconds\n";
    echo "  Memory: " . round($optMemory / 1024 / 1024, 1) . " MB\n";
    echo "  Documents: {$optCount}\n";

    // Calculate improvements
    if ($oldTime > 0) {
        $timeImprovement = (($oldTime - $optTime) / $oldTime) * 100;
        $speedup = $timeImprovement > 0 ? "✅ " : "❌ ";
        echo "Performance: {$speedup}" .
            number_format(abs($timeImprovement), 1) .
            "% " .
            ($timeImprovement > 0 ? "faster" : "slower") .
            "\n";
    }

    if ($oldMemory > 0) {
        $memoryImprovement = (($oldMemory - $optMemory) / $oldMemory) * 100;
        if (abs($memoryImprovement) > 0.1) {
            $memIcon = $memoryImprovement > 0 ? "✅ " : "❌ ";
            echo "Memory: {$memIcon}" .
                number_format(abs($memoryImprovement), 1) .
                "% " .
                ($memoryImprovement > 0 ? "less" : "more") .
                "\n";
        }
    }

    // Clean up CSV file
    unlink($csvPath);

    echo "\n";
}

// ============================================================================
// MAIN EXECUTION
// ============================================================================

function runBenchmark(): void
{
    echo "CSV Performance Benchmark\n";
    echo "=========================\n\n";
    echo "Each test runs independently to avoid memory contamination.\n\n";

    createBenchmarkDirectory();

    foreach (ROW_COUNTS as $rowCount) {
        runSingleBenchmark($rowCount);
    }

    cleanupBenchmarkDirectory();

    echo "Benchmark completed successfully!\n";
}

// Run the benchmark
if (php_sapi_name() === "cli") {
    try {
        runBenchmark();
    } catch (Exception $e) {
        echo "Error during benchmark: " . $e->getMessage() . "\n";
        cleanupBenchmarkDirectory();
        exit(1);
    }
} else {
    echo "This script must be run from the command line.\n";
    echo "Usage: php benchmarking.php\n";
}
