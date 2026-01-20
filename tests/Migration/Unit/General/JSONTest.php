<?php

namespace Utopia\Tests\Unit\General;

use PHPUnit\Framework\TestCase;
use Utopia\Migration\Destinations\JSON as DestinationJSON;
use Utopia\Migration\Resource as UtopiaResource;
use Utopia\Migration\Resources\Database\Database;
use Utopia\Migration\Resources\Database\Row;
use Utopia\Migration\Resources\Database\Table;
use Utopia\Migration\Transfer;
use Utopia\Storage\Device\Local;
use Utopia\Tests\Unit\Adapters\MockSource;

class JSONTest extends TestCase
{
    public function testJSONExportBasic()
    {
        $tempDir = sys_get_temp_dir() . '/json_test_' . uniqid();
        mkdir($tempDir, 0755, true);

        $jsonDestination = $this->createDestination($tempDir);

        $database = new Database('test_db');
        $table = new Table($database, 'test_table', 'test_table_id');

        $row1 = new Row('row1', $table, [
            'name' => 'John Doe',
            'age' => 30,
            'email' => 'john@example.com'
        ]);
        $row1->setPermissions(['read("user:123")']);

        $row2 = new Row('row2', $table, [
            'name' => 'Jane Smith',
            'age' => 25,
            'email' => 'jane@example.com'
        ]);
        $row2->setPermissions(['read("user:456")']);

        $this->runExport([$row1, $row2], $jsonDestination);

        $data = $this->readJsonFile($tempDir);

        $this->assertIsArray($data);
        $this->assertCount(2, $data);
        $this->assertSame('row1', $data[0]['$id']);
        $this->assertSame('John Doe', $data[0]['name']);
        $this->assertSame(30, $data[0]['age']);
        $this->assertSame('john@example.com', $data[0]['email']);
        $this->assertIsArray($data[0]['$permissions']);

        if (is_dir($tempDir)) {
            $this->recursiveDelete($tempDir);
        }
    }

    public function testJSONExportWithSpecialCharacters()
    {
        $tempDir = sys_get_temp_dir() . '/json_test_special_' . uniqid();
        mkdir($tempDir, 0755, true);

        $jsonDestination = $this->createDestination($tempDir);

        $database = new Database('test_db');
        $table = new Table($database, 'test_table', 'test_table_id');

        $row = new Row('special_row', $table, [
            'quote_field' => 'Text with "quotes"',
            'comma_field' => 'Text, with, commas',
            'newline_field' => "Text with\nnewlines",
            'mixed_field' => 'Text with "quotes", commas, and\nnewlines'
        ]);

        $this->runExport([$row], $jsonDestination);

        $data = $this->readJsonFile($tempDir);

        $this->assertSame('Text with "quotes"', $data[0]['quote_field']);
        $this->assertSame('Text, with, commas', $data[0]['comma_field']);
        $this->assertSame("Text with\nnewlines", $data[0]['newline_field']);
        $this->assertSame('Text with "quotes", commas, and\nnewlines', $data[0]['mixed_field']);

        if (is_dir($tempDir)) {
            $this->recursiveDelete($tempDir);
        }
    }

    public function testJSONExportWithArrays()
    {
        $tempDir = sys_get_temp_dir() . '/json_test_arrays_' . uniqid();
        mkdir($tempDir, 0755, true);

        $jsonDestination = $this->createDestination($tempDir);

        $database = new Database('test_db');
        $table = new Table($database, 'test_table', 'test_table_id');

        $row = new Row('array_row', $table, [
            'tags' => ['php', 'json', 'export'],
            'metadata' => ['key1' => 'value1', 'key2' => 'value2'],
            'empty_array' => [],
            'nested' => [['id' => 1], ['id' => 2]]
        ]);

        $this->runExport([$row], $jsonDestination);

        $data = $this->readJsonFile($tempDir);

        $this->assertSame(['php', 'json', 'export'], $data[0]['tags']);
        $this->assertSame(['key1' => 'value1', 'key2' => 'value2'], $data[0]['metadata']);
        $this->assertSame([], $data[0]['empty_array']);
        $this->assertSame([['id' => 1], ['id' => 2]], $data[0]['nested']);

        if (is_dir($tempDir)) {
            $this->recursiveDelete($tempDir);
        }
    }

    public function testJSONExportPreservesNestedObjectsWithIdKeys()
    {
        $tempDir = sys_get_temp_dir() . '/json_test_nested_ids_' . uniqid();
        mkdir($tempDir, 0755, true);

        $jsonDestination = $this->createDestination($tempDir);

        $database = new Database('test_db');
        $table = new Table($database, 'test_table', 'test_table_id');

        $payload = [
            'items' => [
                [
                    '$id' => 'nested1',
                    'value' => 'keep-me',
                ],
                [
                    '$id' => 'nested2',
                    'value' => ['deep' => true],
                ],
            ],
            'object' => [
                '$id' => 'nested-object',
                'meta' => ['foo' => 'bar'],
            ],
        ];

        $row = new Row('nested_row', $table, $payload);

        $this->runExport([$row], $jsonDestination);

        $data = $this->readJsonFile($tempDir);

        $this->assertSame($payload['items'], $data[0]['items']);
        $this->assertSame($payload['object'], $data[0]['object']);

        if (is_dir($tempDir)) {
            $this->recursiveDelete($tempDir);
        }
    }

    public function testJSONExportWithNullValues()
    {
        $tempDir = sys_get_temp_dir() . '/json_test_nulls_' . uniqid();
        mkdir($tempDir, 0755, true);

        $jsonDestination = $this->createDestination($tempDir);

        $database = new Database('test_db');
        $table = new Table($database, 'test_table', 'test_table_id');

        $row = new Row('null_row', $table, [
            'name' => 'Test',
            'null_field' => null,
            'empty_string' => '',
            'zero' => 0,
            'false_bool' => false
        ]);

        $this->runExport([$row], $jsonDestination);

        $data = $this->readJsonFile($tempDir);

        $this->assertSame('Test', $data[0]['name']);
        $this->assertNull($data[0]['null_field']);
        $this->assertSame('', $data[0]['empty_string']);
        $this->assertSame(0, $data[0]['zero']);
        $this->assertFalse($data[0]['false_bool']);

        if (is_dir($tempDir)) {
            $this->recursiveDelete($tempDir);
        }
    }

    public function testJSONExportWithAllowedAttributes()
    {
        $tempDir = sys_get_temp_dir() . '/json_test_filtered_' . uniqid();
        mkdir($tempDir, 0755, true);

        $jsonDestination = new DestinationJSON(
            new Local($tempDir),
            'test_db:test_table_id',
            '',
            'test_db_test_table_id',
            ['name', 'email']
        );

        $database = new Database('test_db');
        $table = new Table($database, 'test_table', 'test_table_id');

        $row = new Row('filtered_row', $table, [
            'name' => 'John Doe',
            'age' => 30,
            'email' => 'john@example.com',
            'secret' => 'should_not_appear'
        ]);

        $this->runExport([$row], $jsonDestination);

        $data = $this->readJsonFile($tempDir);

        $this->assertArrayHasKey('$id', $data[0]);
        $this->assertArrayHasKey('$permissions', $data[0]);
        $this->assertArrayHasKey('$createdAt', $data[0]);
        $this->assertArrayHasKey('$updatedAt', $data[0]);
        $this->assertArrayHasKey('name', $data[0]);
        $this->assertArrayHasKey('email', $data[0]);
        $this->assertArrayNotHasKey('age', $data[0]);
        $this->assertArrayNotHasKey('secret', $data[0]);

        if (is_dir($tempDir)) {
            $this->recursiveDelete($tempDir);
        }
    }

    private function createDestination(string $tempDir): DestinationJSON
    {
        return new DestinationJSON(
            new Local($tempDir),
            'test_db:test_table_id',
            '',
            'test_db_test_table_id'
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readJsonFile(string $tempDir): array
    {
        $jsonFile = $tempDir . '/test_db_test_table_id.json';
        $data = json_decode(file_get_contents($jsonFile), true);
        return is_array($data) ? $data : [];
    }

    /**
     * @param array<int, Row> $rows
     */
    private function runExport(array $rows, DestinationJSON $destination): void
    {
        $source = new MockSource();
        foreach ($rows as $row) {
            $source->pushMockResource($row);
        }

        $transfer = new Transfer($source, $destination);
        $transfer->run([UtopiaResource::TYPE_ROW], function () {
            return;
        });

        $destination->shutdown();
    }

    private function recursiveDelete(string $dir): void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            if ($objects !== false) {
                foreach ($objects as $object) {
                    if ($object != "." && $object != "..") {
                        if (is_dir($dir."/".$object)) {
                            $this->recursiveDelete($dir."/".$object);
                        } else {
                            unlink($dir."/".$object);
                        }
                    }
                }
            }
            rmdir($dir);
        }
    }
}
