<?php

namespace Utopia\Tests\Unit\Sources;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Utopia\Migration\Cache;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Database\Database;
use Utopia\Migration\Resources\Database\Table;
use Utopia\Migration\Sources\Appwrite;
use Utopia\Migration\Sources\Appwrite\Reader;

class AppwriteSourceTest extends TestCase
{
    public function testRecordExportSelectsSequenceForDatabaseSourceCursorPagination(): void
    {
        [$source, $reader] = $this->createSourceWithReader(Appwrite::SOURCE_DATABASE);

        $this->exportRows($source);

        $this->assertContains('$sequence', $reader->selectedColumns[0]);
        $this->assertSame('row-1', $reader->cursorAfterId);
    }

    public function testRecordExportLeavesApiSourceSelectUnchanged(): void
    {
        [$source, $reader] = $this->createSourceWithReader(Appwrite::SOURCE_API);

        $this->exportRows($source);

        $this->assertNotContains('$sequence', $reader->selectedColumns[0]);
        $this->assertSame('row-1', $reader->cursorAfterId);
    }

    private function createSourceWithReader(string $sourceType): array
    {
        $database = (new Database('database', 'Database'))->setSequence('1');
        $table = (new Table($database, 'Table', 'table'))->setSequence('2');
        $reader = new AppwriteSourceTestReader();
        $cache = new Cache();
        $cache->add($table);

        $source = new Appwrite(
            'project',
            'http://localhost/v1',
            'key',
            fn () => throw new \RuntimeException('Unexpected database access')
        );
        $source->registerCache($cache);

        $reflection = new ReflectionClass($source);

        $readerProperty = $reflection->getProperty('reader');
        $readerProperty->setAccessible(true);
        $readerProperty->setValue($source, $reader);

        $sourceProperty = $reflection->getProperty('source');
        $sourceProperty->setAccessible(true);
        $sourceProperty->setValue($source, $sourceType);

        $callbackProperty = $reflection->getParentClass()->getProperty('transferCallback');
        $callbackProperty->setAccessible(true);
        $callbackProperty->setValue($source, function (): void {
        });

        return [$source, $reader];
    }

    private function exportRows(Appwrite $source): void
    {
        $reflection = new ReflectionClass($source);
        $exportRecords = $reflection->getMethod('exportRecords');
        $exportRecords->setAccessible(true);
        $exportRecords->invoke($source, Resource::TYPE_TABLE, Resource::TYPE_COLUMN, 1);
    }
}

class AppwriteSourceTestReader implements Reader
{
    /**
     * @var array<array<string>>
     */
    public array $selectedColumns = [];

    public ?string $cursorAfterId = null;

    private int $listRowsCalls = 0;

    public function report(array $resources, array &$report, array $resourceIds = []): mixed
    {
        return null;
    }

    public function listDatabases(array $queries = []): array
    {
        return [];
    }

    public function listTables(Database $resource, array $queries = []): array
    {
        return [];
    }

    public function listColumns(Table $resource, array $queries = []): array
    {
        return [];
    }

    public function listIndexes(Table $resource, array $queries = []): array
    {
        return [];
    }

    public function listRows(Table $resource, array $queries = []): array
    {
        $this->listRowsCalls++;

        if ($this->listRowsCalls > 1) {
            return [];
        }

        return [[
            '$id' => 'row-1',
            '$sequence' => '10',
            '$permissions' => [],
            '$createdAt' => '',
            '$updatedAt' => '',
            'name' => 'first',
        ]];
    }

    public function getRow(Table $resource, string $rowId, array $queries = []): array
    {
        return [];
    }

    public function querySelect(array $columns): mixed
    {
        $this->selectedColumns[] = $columns;

        return ['select' => $columns];
    }

    public function queryEqual(string $column, array $values): mixed
    {
        return ['equal' => [$column, $values]];
    }

    public function queryCursorAfter(Resource|string $resource): mixed
    {
        $this->cursorAfterId = $resource instanceof Resource ? $resource->getId() : $resource;

        return ['cursorAfter' => $this->cursorAfterId];
    }

    public function queryLimit(int $limit): mixed
    {
        return ['limit' => $limit];
    }

    public function getSupportForAttributes(): bool
    {
        return false;
    }
}
