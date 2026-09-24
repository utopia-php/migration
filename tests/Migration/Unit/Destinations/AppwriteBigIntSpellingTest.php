<?php

declare(strict_types=1);

namespace Utopia\Tests\Unit\Destinations;

use PHPUnit\Framework\TestCase;
use Utopia\Cache\Adapter\Memory as MemoryCache;
use Utopia\Cache\Cache;
use Utopia\Database\Adapter\Memory as MemoryAdapter;
use Utopia\Database\Attribute as UtopiaAttribute;
use Utopia\Database\Collection;
use Utopia\Database\Database as UtopiaDatabase;
use Utopia\Database\Document as UtopiaDocument;
use Utopia\Database\Query;
use Utopia\Migration\Destinations\Appwrite as AppwriteDestination;
use Utopia\Migration\Destinations\Appwrite\ProvisioningOwner;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Database\Column;
use Utopia\Migration\Resources\Database\Columns\BigInt;
use Utopia\Migration\Resources\Database\Database as DatabaseResource;
use Utopia\Migration\Resources\Database\Table;
use Utopia\Migration\Transfer;
use Utopia\Query\Schema\ColumnType;
use Utopia\Tests\Unit\Adapters\MockSource;

/**
 * A big integer is stored in Appwrite's attribute metadata as `bigint`, the one
 * spelling its API reads back, while the schema itself keeps the
 * {@see ColumnType::BigInteger} case. A migrated column that disagrees with
 * either side can no longer be updated through the API.
 */
final class AppwriteBigIntSpellingTest extends TestCase
{
    public const string FORMAT = 'range';

    protected function setUp(): void
    {
        parent::setUp();
        self::registerSubqueryFilters();
    }

    public function testTheMigratedBigIntColumnCarriesThePersistedSpelling(): void
    {
        [$database, $destination, $column] = $this->transferColumn(
            static fn (Table $table): Column => new BigInt('total', $table, required: true),
        );

        $this->assertSame([], $this->errorMessages($destination));
        $this->assertSame(Resource::STATUS_SUCCESS, $column->getStatus());

        $metadata = $this->attributeDocument($database);
        $this->assertFalse($metadata->isEmpty(), 'The column metadata row must be written');
        $this->assertSame('total', $metadata->getAttribute('key'));
        $this->assertSame(
            'bigint',
            $metadata->getAttribute('type'),
            'The metadata row must hold the spelling the destination reads back when the column is updated.',
        );

        $this->assertSame(
            ColumnType::BigInteger,
            $this->physicalColumn($database, 'total')->type,
            'The table itself must still hold a big integer column.',
        );
    }

    public function testAFormattedBigIntColumnReachesTheFormatDecision(): void
    {
        [, $destination, $column] = $this->transferColumn(
            static fn (Table $table): Column => new class ('total', $table) extends BigInt {
                public function getFormat(): string
                {
                    return AppwriteBigIntSpellingTest::FORMAT;
                }
            },
        );

        $this->assertSame(Resource::STATUS_ERROR, $column->getStatus());

        $messages = $this->errorMessages($destination);
        $this->assertCount(1, $messages);
        $this->assertStringStartsWith(
            'Format '.self::FORMAT.' not available for column type',
            $messages[0],
            'A formatted big integer must reach the format decision instead of failing to resolve its column type.',
        );
    }

    private static function registerSubqueryFilters(): void
    {
        static $registered = false;
        if ($registered) {
            return;
        }
        $registered = true;

        UtopiaDatabase::addFilter(
            'subQueryAttributes',
            static fn (mixed $value) => null,
            static fn (mixed $value, UtopiaDocument $document, UtopiaDatabase $database): array => $database->getAuthorization()->skip(
                static fn (): array => $database->find('attributes', [
                    Query::equal('collectionInternalId', [$document->getSequence()]),
                    Query::equal('databaseInternalId', [$document->getAttribute('databaseInternalId')]),
                ]),
            ),
        );
        UtopiaDatabase::addFilter(
            'subQueryIndexes',
            static fn (mixed $value) => null,
            static fn (mixed $value, UtopiaDocument $document, UtopiaDatabase $database): array => $database->getAuthorization()->skip(
                static fn (): array => $database->find('indexes', [
                    Query::equal('collectionInternalId', [$document->getSequence()]),
                    Query::equal('databaseInternalId', [$document->getAttribute('databaseInternalId')]),
                ]),
            ),
        );
    }

    /**
     * @param callable(Table): Column $makeColumn
     * @return array{UtopiaDatabase, AppwriteDestination, Column}
     */
    private function transferColumn(callable $makeColumn): array
    {
        $database = $this->projectDatabase();

        $source = new MockSource();
        $databaseResource = new DatabaseResource(
            id: 'shop',
            name: 'Shop',
            type: 'tablesdb',
            database: 'source-dsn',
        );
        $table = new Table($databaseResource, 'Products', 'products');
        $column = $makeColumn($table);
        $column->setId('column-'.$column->getKey());

        $source->pushMockResource($databaseResource);
        $source->pushMockResource($table);
        $source->pushMockResource($column);

        $destination = new AppwriteDestination(
            project: 'destination-project',
            endpoint: 'http://example.test/v1',
            key: 'test-key',
            dbForProject: $database,
            getDatabasesDB: static fn (UtopiaDocument $document): UtopiaDatabase => $database,
            collectionStructure: [
                'attributes' => [
                    $this->attributeArray('databaseInternalId', ColumnType::String, size: UtopiaDatabase::LENGTH_KEY),
                    $this->attributeArray('databaseId', ColumnType::String, size: UtopiaDatabase::LENGTH_KEY),
                    $this->attributeArray('name', ColumnType::String, size: 256),
                    $this->attributeArray('enabled', ColumnType::Boolean, default: true),
                    $this->attributeArray('documentSecurity', ColumnType::Boolean, default: false),
                    $this->attributeArray('search', ColumnType::String, size: 16384),
                    $this->attributeArray('attributes', ColumnType::String, size: 16384, filters: ['subQueryAttributes']),
                    $this->attributeArray('indexes', ColumnType::String, size: 16384, filters: ['subQueryIndexes']),
                ],
                'indexes' => [],
            ],
            dbForPlatform: $database,
            projectInternalId: '1',
            owner: new ProvisioningOwner('migration-test', 'attempt-test'),
            getRecoverableOwner: static fn (UtopiaDocument $document): ?ProvisioningOwner => null,
        );

        $transfer = new Transfer($source, $destination);
        $database->getAuthorization()->skip(
            static function () use ($transfer): void {
                $transfer->run(
                    [Resource::TYPE_DATABASE, Resource::TYPE_TABLE, Resource::TYPE_COLUMN],
                    static function (): void {
                    },
                );
            },
        );

        return [$database, $destination, $column];
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

        $database->createCollection(new Collection(
            id: 'databases',
            attributes: [
                $this->attribute('name', ColumnType::String, required: true, size: 256),
                $this->attribute('enabled', ColumnType::Boolean, default: true),
                $this->attribute('search', ColumnType::String, size: 16384),
                $this->attribute('originalId', ColumnType::String, size: UtopiaDatabase::LENGTH_KEY),
                $this->attribute('type', ColumnType::String, default: 'tablesdb', size: 128),
                $this->attribute('database', ColumnType::String, size: 2000),
            ],
        ));

        $database->createCollection(new Collection(
            id: 'attributes',
            attributes: [
                $this->attribute('key', ColumnType::String, size: 256),
                $this->attribute('databaseInternalId', ColumnType::String, size: UtopiaDatabase::LENGTH_KEY),
                $this->attribute('databaseId', ColumnType::String, size: UtopiaDatabase::LENGTH_KEY),
                $this->attribute('collectionInternalId', ColumnType::String, size: UtopiaDatabase::LENGTH_KEY),
                $this->attribute('collectionId', ColumnType::String, size: UtopiaDatabase::LENGTH_KEY),
                $this->attribute('type', ColumnType::String, size: 256),
                $this->attribute('status', ColumnType::String, size: 64),
                $this->attribute('size', ColumnType::Integer),
                $this->attribute('required', ColumnType::Boolean, default: false),
                $this->attribute('signed', ColumnType::Boolean, default: true),
                $this->attribute('default', ColumnType::String, size: 16384),
                $this->attribute('array', ColumnType::Boolean, default: false),
                $this->attribute('format', ColumnType::String, size: 64),
                $this->attribute('formatOptions', ColumnType::String, size: 16384, filters: ['json']),
                $this->attribute('filters', ColumnType::String, size: 64, array: true),
                $this->attribute('options', ColumnType::String, size: 16384, filters: ['json']),
                $this->attribute('error', ColumnType::String, size: 2048),
            ],
        ));

        $database->createCollection(new Collection(
            id: 'indexes',
            attributes: [
                $this->attribute('key', ColumnType::String, size: 256),
                $this->attribute('status', ColumnType::String, size: 64),
                $this->attribute('databaseInternalId', ColumnType::String, size: UtopiaDatabase::LENGTH_KEY),
                $this->attribute('databaseId', ColumnType::String, size: UtopiaDatabase::LENGTH_KEY),
                $this->attribute('collectionInternalId', ColumnType::String, size: UtopiaDatabase::LENGTH_KEY),
                $this->attribute('collectionId', ColumnType::String, size: UtopiaDatabase::LENGTH_KEY),
                $this->attribute('type', ColumnType::String, size: 16),
                $this->attribute('attributes', ColumnType::String, size: 256, array: true),
                $this->attribute('lengths', ColumnType::Integer, array: true),
                $this->attribute('orders', ColumnType::String, size: 4, array: true),
                $this->attribute('error', ColumnType::String, size: 2048),
            ],
        ));

        return $database;
    }

    /**
     * @param array<string> $filters
     * @return array<string, mixed>
     */
    private function attributeArray(
        string $id,
        ColumnType $type,
        bool $required = false,
        mixed $default = null,
        int $size = 0,
        bool $array = false,
        array $filters = [],
    ): array {
        return [
            '$id' => $id,
            'type' => $type->value,
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
        ColumnType $type,
        bool $required = false,
        mixed $default = null,
        int $size = 0,
        bool $array = false,
        array $filters = [],
    ): UtopiaAttribute {
        return new UtopiaAttribute(
            key: $id,
            type: $type,
            size: $size,
            required: $required,
            default: $default,
            array: $array,
            filters: $filters,
        );
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

    private function attributeDocument(UtopiaDatabase $database): UtopiaDocument
    {
        $attributes = $database->getAuthorization()->skip(
            static fn (): array => $database->find('attributes'),
        );

        return $attributes[0] ?? new UtopiaDocument();
    }

    private function physicalColumn(UtopiaDatabase $database, string $key): UtopiaAttribute
    {
        $shop = $this->document($database, 'databases', 'shop');
        $table = $this->document($database, 'database_'.$shop->getSequence(), 'products');
        $collectionId = 'database_'.$shop->getSequence().'_collection_'.$table->getSequence();

        $collection = $database->getAuthorization()->skip(
            static fn (): Collection => $database->getCollection($collectionId),
        );

        foreach ($collection->getAttribute('attributes', []) as $attribute) {
            if ($attribute->getId() === $key) {
                return $attribute;
            }
        }

        $this->fail("Column {$key} was not created on the destination table");
    }

    private function document(UtopiaDatabase $database, string $collection, string $id): UtopiaDocument
    {
        return $database->getAuthorization()->skip(
            static fn (): UtopiaDocument => $database->getDocument($collection, $id),
        );
    }
}
