<?php

declare(strict_types=1);

namespace Utopia\Tests\Unit\Destinations;

use PHPUnit\Framework\TestCase;
use Utopia\Cache\Adapter\Memory as MemoryCache;
use Utopia\Cache\Cache;
use Utopia\Database\Adapter\Memory as MemoryAdapter;
use Utopia\Database\Database as UtopiaDatabase;
use Utopia\Database\Document as UtopiaDocument;
use Utopia\Migration\Destinations\Appwrite as AppwriteDestination;
use Utopia\Migration\Destinations\OnDuplicate;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Database\Columns\Text;
use Utopia\Migration\Resources\Database\Database as DatabaseResource;
use Utopia\Migration\Resources\Database\Index;
use Utopia\Migration\Resources\Database\Table;
use Utopia\Migration\Transfer;
use Utopia\Tests\Unit\Adapters\MockSource;

/**
 * A restored index must carry the source's own prefix lengths. The source
 * recorded them because the index only fits under the adapter's byte limit
 * with them; deriving lengths from the destination columns instead recreates
 * the index full-width and a backup of a healthy database fails to restore
 * its own indexes with "Index length is longer than the maximum" (DAT-2113,
 * nine consecutive customer restore failures).
 */
final class AppwriteIndexLengthsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::registerSubqueryFilters();
    }

    private static function registerSubqueryFilters(): void
    {
        static $registered = false;
        if ($registered) {
            return;
        }
        $registered = true;

        // The per-database meta collection exposes its tables' columns and
        // indexes through the same virtual subquery attributes Appwrite
        // registers, so the destination reads the table exactly as it does in
        // production.
        UtopiaDatabase::addFilter(
            'subQueryAttributes',
            static fn (mixed $value) => null,
            static fn (mixed $value, UtopiaDocument $document, UtopiaDatabase $database): array => $database->getAuthorization()->skip(
                static fn (): array => $database->find('attributes', [
                    \Utopia\Database\Query::equal('collectionInternalId', [$document->getSequence()]),
                    \Utopia\Database\Query::equal('databaseInternalId', [$document->getAttribute('databaseInternalId')]),
                ]),
            ),
        );
        UtopiaDatabase::addFilter(
            'subQueryIndexes',
            static fn (mixed $value) => null,
            static fn (mixed $value, UtopiaDocument $document, UtopiaDatabase $database): array => $database->getAuthorization()->skip(
                static fn (): array => $database->find('indexes', [
                    \Utopia\Database\Query::equal('collectionInternalId', [$document->getSequence()]),
                    \Utopia\Database\Query::equal('databaseInternalId', [$document->getAttribute('databaseInternalId')]),
                ]),
            ),
        );
    }

    public function testRestoredIndexCarriesTheSourcePrefixLengths(): void
    {
        [$destination, $database] = $this->transferIndex(lengths: [100, 20]);

        $this->assertSame([], $this->errorMessages($destination));

        $created = $this->indexDocument($database);
        $this->assertFalse($created->isEmpty(), 'The index must be created');
        $this->assertSame([100, 20], $created->getAttribute('lengths'));
    }

    public function testRestoredIndexWithoutSourceLengthsFailsTheAdapterLimit(): void
    {
        // The two 600-char columns exceed the Memory adapter's 1024-byte index
        // cap without prefixes, exactly like the production 767-byte MySQL cap:
        // an index that NEEDS its source lengths cannot be recreated without
        // them, which is the customer-facing failure this suite pins.
        [$destination, $database] = $this->transferIndex(lengths: []);

        $messages = $this->errorMessages($destination);
        $this->assertNotSame([], $messages, 'Expected the full-width index to exceed the adapter limit');
        $this->assertStringContainsString('Index length is longer than the maximum', $messages[0]);
        $this->assertTrue($this->indexDocument($database)->isEmpty(), 'The failed index must not be recorded');
    }

    public function testZeroSourceLengthMeansNoPrefix(): void
    {
        // Zero is how a source records "no prefix" for a position (a short
        // column needs none), and it must stay no-prefix rather than becoming a
        // zero-length one. The metadata collection types `lengths` as an integer
        // array, so no-prefix reads back as 0 — the shape a live production row
        // for exactly this case carries ([100, 0]).
        [$destination, $database] = $this->transferIndex(lengths: [100, 0], columnSizes: [600, 30]);

        $this->assertSame([], $this->errorMessages($destination));
        $this->assertSame([100, 0], $this->indexDocument($database)->getAttribute('lengths'));
    }

    /**
     * @param array<int> $lengths
     * @param array<int> $columnSizes
     * @return array{AppwriteDestination, UtopiaDatabase}
     */
    private function transferIndex(array $lengths, array $columnSizes = [600, 600]): array
    {
        $database = $this->projectDatabase();

        $source = new MockSource();
        $databaseResource = new DatabaseResource(
            id: 'shop',
            name: 'Shop',
            type: 'tablesdb',
            database: 'source-dsn',
        );
        $table = new Table($databaseResource, 'Orders', 'orders');
        $source->pushMockResource($databaseResource);
        $source->pushMockResource($table);
        // Distinct ids: MockSource keys its map by resource id, and a Column
        // carries none by default, so two columns would collide onto one.
        $source->pushMockResource((new Text('reference', $table, size: $columnSizes[0]))->setId('reference'));
        $source->pushMockResource((new Text('channel', $table, size: $columnSizes[1]))->setId('channel'));
        $source->pushMockResource(new Index(
            id: 'idx_reference_channel',
            key: 'idx_reference_channel',
            table: $table,
            type: 'key',
            columns: ['reference', 'channel'],
            lengths: $lengths,
            orders: ['ASC', 'ASC'],
        ));

        $destination = new AppwriteDestination(
            project: 'destination-project',
            endpoint: 'http://example.test/v1',
            key: 'test-key',
            dbForProject: $database,
            getDatabasesDB: static fn (UtopiaDocument $document): UtopiaDatabase => $database,
            collectionStructure: [
                'attributes' => [
                    $this->attributeArray('databaseInternalId', UtopiaDatabase::VAR_STRING, size: UtopiaDatabase::LENGTH_KEY),
                    $this->attributeArray('databaseId', UtopiaDatabase::VAR_STRING, size: UtopiaDatabase::LENGTH_KEY),
                    $this->attributeArray('name', UtopiaDatabase::VAR_STRING, size: 256),
                    $this->attributeArray('enabled', UtopiaDatabase::VAR_BOOLEAN, default: true),
                    $this->attributeArray('documentSecurity', UtopiaDatabase::VAR_BOOLEAN, default: false),
                    $this->attributeArray('search', UtopiaDatabase::VAR_STRING, size: 16384),
                    $this->attributeArray('attributes', UtopiaDatabase::VAR_STRING, size: 16384, filters: ['subQueryAttributes']),
                    $this->attributeArray('indexes', UtopiaDatabase::VAR_STRING, size: 16384, filters: ['subQueryIndexes']),
                ],
                'indexes' => [],
            ],
            dbForPlatform: $database,
            projectInternalId: '1',
            onDuplicate: OnDuplicate::Fail,
        );

        // Two passes: Transfer::GROUP_DATABASES_RESOURCES replays indexes BEFORE
        // columns, the reverse of what a real source emits, so a single call
        // would offer the index against a table with no columns yet. Selecting
        // the schema first and the index second reproduces the production order
        // this defect lives in.
        $transfer = new Transfer($source, $destination);
        $database->getAuthorization()->skip(
            static function () use ($transfer): void {
                $noop = static function (): void {
                };
                $transfer->run([Resource::TYPE_DATABASE, Resource::TYPE_TABLE, Resource::TYPE_COLUMN], $noop);
                $transfer->run([Resource::TYPE_INDEX], $noop);
            },
        );

        return [$destination, $database];
    }

    private function projectDatabase(): UtopiaDatabase
    {
        $database = new UtopiaDatabase(
            new MemoryAdapter(),
            new Cache(new MemoryCache()),
        );
        $database
            ->setDatabase('appwrite')
            ->setNamespace('_project');
        $database->create();

        $database->createCollection('databases', [
            $this->attribute('name', UtopiaDatabase::VAR_STRING, required: true, size: 256),
            $this->attribute('enabled', UtopiaDatabase::VAR_BOOLEAN, default: true),
            $this->attribute('search', UtopiaDatabase::VAR_STRING, size: 16384),
            $this->attribute('originalId', UtopiaDatabase::VAR_STRING, size: UtopiaDatabase::LENGTH_KEY),
            $this->attribute('type', UtopiaDatabase::VAR_STRING, default: 'tablesdb', size: 128),
            $this->attribute('database', UtopiaDatabase::VAR_STRING, size: 2000),
        ]);

        $database->createCollection('attributes', [
            $this->attribute('key', UtopiaDatabase::VAR_STRING, size: 256),
            $this->attribute('databaseInternalId', UtopiaDatabase::VAR_STRING, size: UtopiaDatabase::LENGTH_KEY),
            $this->attribute('databaseId', UtopiaDatabase::VAR_STRING, size: UtopiaDatabase::LENGTH_KEY),
            $this->attribute('collectionInternalId', UtopiaDatabase::VAR_STRING, size: UtopiaDatabase::LENGTH_KEY),
            $this->attribute('collectionId', UtopiaDatabase::VAR_STRING, size: UtopiaDatabase::LENGTH_KEY),
            $this->attribute('type', UtopiaDatabase::VAR_STRING, size: 256),
            $this->attribute('status', UtopiaDatabase::VAR_STRING, size: 64),
            $this->attribute('size', UtopiaDatabase::VAR_INTEGER),
            $this->attribute('required', UtopiaDatabase::VAR_BOOLEAN, default: false),
            $this->attribute('signed', UtopiaDatabase::VAR_BOOLEAN, default: true),
            $this->attribute('default', UtopiaDatabase::VAR_STRING, size: 16384),
            $this->attribute('array', UtopiaDatabase::VAR_BOOLEAN, default: false),
            $this->attribute('format', UtopiaDatabase::VAR_STRING, size: 64),
            $this->attribute('formatOptions', UtopiaDatabase::VAR_STRING, size: 16384, filters: ['json']),
            $this->attribute('filters', UtopiaDatabase::VAR_STRING, size: 64, array: true),
            $this->attribute('options', UtopiaDatabase::VAR_STRING, size: 16384, filters: ['json']),
            $this->attribute('error', UtopiaDatabase::VAR_STRING, size: 2048),
        ]);

        $database->createCollection('indexes', [
            $this->attribute('key', UtopiaDatabase::VAR_STRING, size: 256),
            $this->attribute('status', UtopiaDatabase::VAR_STRING, size: 64),
            $this->attribute('databaseInternalId', UtopiaDatabase::VAR_STRING, size: UtopiaDatabase::LENGTH_KEY),
            $this->attribute('databaseId', UtopiaDatabase::VAR_STRING, size: UtopiaDatabase::LENGTH_KEY),
            $this->attribute('collectionInternalId', UtopiaDatabase::VAR_STRING, size: UtopiaDatabase::LENGTH_KEY),
            $this->attribute('collectionId', UtopiaDatabase::VAR_STRING, size: UtopiaDatabase::LENGTH_KEY),
            $this->attribute('type', UtopiaDatabase::VAR_STRING, size: 16),
            $this->attribute('attributes', UtopiaDatabase::VAR_STRING, size: 256, array: true),
            $this->attribute('lengths', UtopiaDatabase::VAR_INTEGER, array: true),
            $this->attribute('orders', UtopiaDatabase::VAR_STRING, size: 4, array: true),
            $this->attribute('error', UtopiaDatabase::VAR_STRING, size: 2048),
        ]);

        return $database;
    }

    /**
     * @param array<string> $filters
     * @return array<string, mixed>
     */
    private function attributeArray(
        string $id,
        string $type,
        bool $required = false,
        mixed $default = null,
        int $size = 0,
        bool $array = false,
        array $filters = [],
    ): array {
        return [
            '$id' => $id,
            'type' => $type,
            'size' => $size,
            'required' => $required,
            'default' => $default,
            'array' => $array,
            'signed' => true,
            'filters' => $filters,
        ];
    }

    /**
     * @param array<string> $filters
     */
    private function attribute(
        string $id,
        string $type,
        bool $required = false,
        mixed $default = null,
        int $size = 0,
        bool $array = false,
        array $filters = [],
    ): UtopiaDocument {
        return new UtopiaDocument($this->attributeArray($id, $type, $required, $default, $size, $array, $filters));
    }

    /**
     * @return array<string>
     */
    private function errorMessages(AppwriteDestination $destination): array
    {
        return \array_map(
            static fn ($error): string => $error->getMessage(),
            $destination->getErrors(),
        );
    }

    private function indexDocument(UtopiaDatabase $database): UtopiaDocument
    {
        $indexes = $database->getAuthorization()->skip(
            static fn (): array => $database->find('indexes'),
        );

        return $indexes[0] ?? new UtopiaDocument();
    }
}
