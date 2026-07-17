<?php

namespace Utopia\Tests\Unit\General;

use Override;
use PHPUnit\Framework\TestCase;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Database\Database;
use Utopia\Migration\Resources\Database\Row;
use Utopia\Migration\Resources\Database\Table;
use Utopia\Migration\Transfer;
use Utopia\Tests\Unit\Adapters\MockDestination;
use Utopia\Tests\Unit\Adapters\MockSource;

class TransferTest extends TestCase
{
    protected Transfer $transfer;
    protected MockSource $source;
    protected MockDestination $destination;

    public function setup(): void
    {
        $this->source = new MockSource();
        $this->destination = new MockDestination();

        $this->transfer = new Transfer(
            $this->source,
            $this->destination
        );
    }

    /**
     * @throws \Exception
     */
    public function testRootResourceId(): void
    {
        /**
         * TEST FOR FAILURE
         * Make sure we can't create a transfer with multiple root resources when supplying a rootResourceId
         */
        try {
            $this->transfer->run([Resource::TYPE_USER, Resource::TYPE_DATABASE], function () {
            }, 'rootResourceId');
            $this->fail('Multiple root resources should not be allowed');
        } catch (\Exception $e) {
            $this->assertSame('Resource type must be set when resource ID is set.', $e->getMessage());
        }

        $this->source->pushMockResource(new Database('test', 'test'));
        $this->source->pushMockResource(new Database('test2', 'test'));

        /**
         * TEST FOR SUCCESS
         */
        $this->transfer->run(
            [Resource::TYPE_DATABASE],
            function () {
            },
            'test',
            Resource::TYPE_DATABASE
        );
        $this->assertCount(1, $this->destination->getResourceTypeData(Transfer::GROUP_DATABASES, Resource::TYPE_DATABASE));

        $database = $this->destination->getResourceById(Transfer::GROUP_DATABASES, Resource::TYPE_DATABASE, 'test');
        /** @var Database $database */
        $this->assertNotNull($database);
        $this->assertSame('test', $database->getDatabaseName());
        $this->assertSame('test', $database->getId());
    }

    public function testLegacyCompoundRootResourceIdScopesDatabaseEntity(): void
    {
        $database = new Database('database', 'Database');
        $first = new Table($database, 'First table', 'first');
        $second = new Table($database, 'Second table', 'second');

        $this->source->pushMockResource($database);
        $this->source->pushMockResource($first);
        $this->source->pushMockResource($second);

        $this->transfer->run(
            [Resource::TYPE_DATABASE, Resource::TYPE_TABLE],
            static function (): void {
            },
            $database->getId() . ':' . $second->getId(),
            Resource::TYPE_DATABASE,
        );

        $tables = $this->destination->getResourceTypeData(Transfer::GROUP_DATABASES, Resource::TYPE_TABLE);

        $this->assertSame(['second'], $tables);
        $this->assertNull($this->destination->getResourceById(Transfer::GROUP_DATABASES, Resource::TYPE_TABLE, 'first'));
        $this->assertSame($second, $this->destination->getResourceById(Transfer::GROUP_DATABASES, Resource::TYPE_TABLE, 'second'));
    }

    public function testLegacySourceSubclassChildSelectorPropertyRemainsCompatible(): void
    {
        $source = new class () extends MockSource {
            protected $rootResourceChildId = ['external'];
        };
        $destination = new MockDestination();
        $transfer = new Transfer($source, $destination);
        $database = new Database('database', 'Database');
        $first = new Table($database, 'First table', 'first');
        $second = new Table($database, 'Second table', 'second');

        $source->pushMockResource($database);
        $source->pushMockResource($first);
        $source->pushMockResource($second);

        $transfer->run(
            [Resource::TYPE_DATABASE, Resource::TYPE_TABLE],
            static function (): void {
            },
            'database:second',
            Resource::TYPE_DATABASE,
        );

        $this->assertSame(
            ['second'],
            $destination->getResourceTypeData(Transfer::GROUP_DATABASES, Resource::TYPE_TABLE),
        );
    }

    public function testExplicitSelectorKeepsColonContainingIdsOpaque(): void
    {
        $database = new Database('database:with:colon', 'Database');
        $first = new Table($database, 'First table', 'first');
        $second = new Table($database, 'Second table', 'table:with:colon');

        $this->source->pushMockResource($database);
        $this->source->pushMockResource($first);
        $this->source->pushMockResource($second);

        $this->transfer->runWithResourceSelector(
            [Resource::TYPE_DATABASE, Resource::TYPE_TABLE],
            static function (): void {
            },
            $database->getId(),
            Resource::TYPE_DATABASE,
            $second->getId(),
        );

        $this->assertSame(
            ['table:with:colon'],
            $this->destination->getResourceTypeData(Transfer::GROUP_DATABASES, Resource::TYPE_TABLE),
        );
        $this->assertSame(
            $database,
            $this->destination->getResourceById(Transfer::GROUP_DATABASES, Resource::TYPE_DATABASE, 'database:with:colon'),
        );
        $this->assertSame(
            $second,
            $this->destination->getResourceById(Transfer::GROUP_DATABASES, Resource::TYPE_TABLE, 'table:with:colon'),
        );
    }

    public function testExplicitSelectorDispatchesLegacyOverridesExactlyOnce(): void
    {
        $source = new class () extends MockSource {
            public int $runCount = 0;

            #[Override]
            public function run(array $resources, callable $callback, string $rootResourceId = '', string $rootResourceType = ''): void
            {
                $this->runCount++;
                parent::run($resources, $callback, $rootResourceId, $rootResourceType);
            }
        };
        $destination = new class () extends MockDestination {
            public int $runCount = 0;

            #[Override]
            public function run(array $resources, callable $callback, string $rootResourceId = '', string $rootResourceType = ''): void
            {
                $this->runCount++;
                parent::run($resources, $callback, $rootResourceId, $rootResourceType);
            }
        };
        $transfer = new class ($source, $destination) extends Transfer {
            public int $runCount = 0;

            #[Override]
            public function run(
                array $resources,
                callable $callback,
                ?string $rootResourceId = null,
                ?string $rootResourceType = null,
            ): void {
                $this->runCount++;
                parent::run($resources, $callback, $rootResourceId, $rootResourceType);
            }
        };

        $database = new Database('database', 'Database');
        $table = new Table($database, 'Table', 'table');
        $source->pushMockResource($database);
        $source->pushMockResource($table);

        $transfer->runWithResourceSelector(
            [Resource::TYPE_DATABASE, Resource::TYPE_TABLE],
            static function (): void {
            },
            $database->getId(),
            Resource::TYPE_DATABASE,
            $table->getId(),
        );

        $this->assertSame(1, $transfer->runCount);
        $this->assertSame(1, $destination->runCount);
        $this->assertSame(1, $source->runCount);
    }

    public function testExplicitSelectorStateIsRestoredAfterSuccess(): void
    {
        $selectedDatabase = new Database('selected', 'Selected');
        $selectedTable = new Table($selectedDatabase, 'Selected table', 'selected-table');
        $legacyDatabase = new Database('legacy', 'Legacy');
        $legacyTable = new Table($legacyDatabase, 'Legacy table', 'legacy-table');

        foreach ([$selectedDatabase, $selectedTable, $legacyDatabase, $legacyTable] as $resource) {
            $this->source->pushMockResource($resource);
        }

        $this->transfer->runWithResourceSelector(
            [Resource::TYPE_DATABASE, Resource::TYPE_TABLE],
            static function (): void {
            },
            $selectedDatabase->getId(),
            Resource::TYPE_DATABASE,
            $selectedTable->getId(),
        );
        $this->transfer->run(
            [Resource::TYPE_DATABASE, Resource::TYPE_TABLE],
            static function (): void {
            },
            'legacy:legacy-table',
            Resource::TYPE_DATABASE,
        );

        $this->assertSame(
            $legacyTable,
            $this->destination->getResourceById(Transfer::GROUP_DATABASES, Resource::TYPE_TABLE, 'legacy-table'),
        );
    }

    public function testExplicitSelectorStateIsRestoredAfterFailure(): void
    {
        $selectedDatabase = new Database('selected', 'Selected');
        $selectedTable = new Table($selectedDatabase, 'Selected table', 'selected-table');
        $legacyDatabase = new Database('legacy', 'Legacy');
        $legacyTable = new Table($legacyDatabase, 'Legacy table', 'legacy-table');

        foreach ([$selectedDatabase, $selectedTable, $legacyDatabase, $legacyTable] as $resource) {
            $this->source->pushMockResource($resource);
        }

        try {
            $this->transfer->runWithResourceSelector(
                [Resource::TYPE_DATABASE, Resource::TYPE_TABLE],
                static function (): void {
                    throw new \RuntimeException('stop');
                },
                $selectedDatabase->getId(),
                Resource::TYPE_DATABASE,
                $selectedTable->getId(),
            );
            $this->fail('The callback exception should escape the transfer.');
        } catch (\RuntimeException $error) {
            $this->assertSame('stop', $error->getMessage());
        }

        $this->transfer->run(
            [Resource::TYPE_DATABASE, Resource::TYPE_TABLE],
            static function (): void {
            },
            'legacy:legacy-table',
            Resource::TYPE_DATABASE,
        );

        $this->assertSame(
            $legacyTable,
            $this->destination->getResourceById(Transfer::GROUP_DATABASES, Resource::TYPE_TABLE, 'legacy-table'),
        );
    }

    /**
     * Row and document counts are aggregated into the cache by status. When such
     * a count exists for a resource type that was not part of the migration
     * request, getStatusCounters() must ignore it, exactly as it already does for
     * non-row resources via the isset() guard. Otherwise it reads an unseeded
     * 'pending' key (triggering an "Undefined array key" warning) and reports a
     * phantom, non-empty counter for a type the caller never asked to migrate.
     */
    public function testStatusCountersIgnoreUnrequestedRowCounts(): void
    {
        // No resource types were requested, so 'row'/'document' are unrequested.
        // A row count still leaks into the cache: the destination tallies row and
        // document counts by status as it imports them.
        $table = new Table(new Database('db', 'db'), 'table', 'table');
        $row = new Row('row-1', $table);
        $row->setStatus(Resource::STATUS_SUCCESS);

        $this->transfer->getCache()->add($row);

        $counters = $this->transfer->getStatusCounters();

        $this->assertArrayNotHasKey(Resource::TYPE_ROW, $counters);
        $this->assertSame([], $counters);
    }
}
