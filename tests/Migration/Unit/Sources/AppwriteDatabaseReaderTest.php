<?php

namespace Utopia\Tests\Unit\Sources;

use PHPUnit\Framework\TestCase;
use Utopia\Database\Database as UtopiaDatabase;
use Utopia\Database\Document as UtopiaDocument;
use Utopia\Database\Exception\NotFound as DatabaseNotFoundException;
use Utopia\Database\Query;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Database\Database as DatabaseResource;
use Utopia\Migration\Resources\Database\Table as TableResource;
use Utopia\Migration\Sources\Appwrite\Reader\Database;

class AppwriteDatabaseReaderTest extends TestCase
{
    public function testReportReadsDocumentsDbMetadataFromDatabaseConnection(): void
    {
        $database = new UtopiaDocument([
            '$id' => 'documents',
            '$sequence' => '2',
            'name' => 'Documents',
            '$createdAt' => '2026-01-01T00:00:00.000+00:00',
            '$updatedAt' => '2026-01-01T00:00:00.000+00:00',
            'enabled' => true,
            'type' => Resource::TYPE_DATABASE_DOCUMENTSDB,
            'database' => 'appwrite://database_selfhosted_fra1_2?database=appwrite&namespace=_1',
        ]);

        $collection = new UtopiaDocument([
            '$id' => 'articles',
            '$sequence' => '4',
            'name' => 'Articles',
            '$createdAt' => '2026-01-01T00:00:00.000+00:00',
            '$updatedAt' => '2026-01-01T00:00:00.000+00:00',
            'enabled' => true,
        ]);

        $dbForProject = $this->getMockBuilder(UtopiaDatabase::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['find', 'getDocument', 'count'])
            ->getMock();

        $dbForDatabase = $this->getMockBuilder(UtopiaDatabase::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['find', 'count'])
            ->getMock();

        $dbForProject
            ->expects($this->once())
            ->method('find')
            ->with(
                'databases',
                $this->callback(fn (array $queries): bool => $this->hasEqualQuery($queries, '$id', ['documents']))
            )
            ->willReturn([$database]);

        $dbForProject
            ->expects($this->once())
            ->method('getDocument')
            ->with('databases', 'documents')
            ->willReturn($database);

        $dbForProject
            ->expects($this->never())
            ->method('count');

        $dbForDatabase
            ->expects($this->once())
            ->method('find')
            ->with('database_2', [])
            ->willReturn([$collection]);

        $dbForDatabase
            ->expects($this->exactly(3))
            ->method('count')
            ->willReturnCallback(function (string $collection, array $queries): int {
                return match ($collection) {
                    'database_2_collection_4' => 7,
                    'attributes' => $this->hasEqualQuery($queries, 'databaseInternalId', ['2'])
                        && $this->hasEqualQuery($queries, 'collectionInternalId', ['4'])
                            ? 2
                            : 0,
                    'indexes' => $this->hasEqualQuery($queries, 'databaseInternalId', ['2'])
                        && $this->hasEqualQuery($queries, 'collectionInternalId', ['4'])
                            ? 1
                            : 0,
                    default => 0,
                };
            });

        $reader = new Database(
            $dbForProject,
            fn (UtopiaDocument $database): UtopiaDatabase => $dbForDatabase,
        );

        $report = [];
        $reader->report(
            [
                Resource::TYPE_DATABASE_DOCUMENTSDB,
                Resource::TYPE_COLLECTION,
                Resource::TYPE_DOCUMENT,
                Resource::TYPE_ATTRIBUTE,
                Resource::TYPE_INDEX,
            ],
            $report,
            [Resource::TYPE_DATABASE_DOCUMENTSDB => ['documents']]
        );

        $this->assertSame(1, $report[Resource::TYPE_DATABASE_DOCUMENTSDB]);
        $this->assertSame(1, $report[Resource::TYPE_COLLECTION]);
        $this->assertSame(7, $report[Resource::TYPE_DOCUMENT]);
        $this->assertSame(2, $report[Resource::TYPE_ATTRIBUTE]);
        $this->assertSame(1, $report[Resource::TYPE_INDEX]);
    }

    public function testReportTreatsMissingDocumentsDbCollectionTableAsEmpty(): void
    {
        $database = new UtopiaDocument([
            '$id' => 'documents',
            '$sequence' => '2',
            'name' => 'Documents',
            '$createdAt' => '2026-01-01T00:00:00.000+00:00',
            '$updatedAt' => '2026-01-01T00:00:00.000+00:00',
            'enabled' => true,
            'type' => Resource::TYPE_DATABASE_DOCUMENTSDB,
            'database' => 'appwrite://database_selfhosted_fra1_2?database=appwrite&namespace=_1',
        ]);

        $dbForProject = $this->getMockBuilder(UtopiaDatabase::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['find', 'getDocument'])
            ->getMock();

        $dbForDatabase = $this->getMockBuilder(UtopiaDatabase::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['find', 'count'])
            ->getMock();

        $dbForProject
            ->expects($this->once())
            ->method('find')
            ->with(
                'databases',
                $this->callback(fn (array $queries): bool => $this->hasEqualQuery($queries, '$id', ['documents']))
            )
            ->willReturn([$database]);

        $dbForProject
            ->expects($this->once())
            ->method('getDocument')
            ->with('databases', 'documents')
            ->willReturn($database);

        $dbForDatabase
            ->expects($this->once())
            ->method('find')
            ->with('database_2', [])
            ->willThrowException(new DatabaseNotFoundException('Collection not found'));

        $dbForDatabase
            ->expects($this->never())
            ->method('count');

        $reader = new Database(
            $dbForProject,
            fn (UtopiaDocument $database): UtopiaDatabase => $dbForDatabase,
        );

        $report = [];
        $reader->report(
            [
                Resource::TYPE_DATABASE_DOCUMENTSDB,
                Resource::TYPE_COLLECTION,
                Resource::TYPE_DOCUMENT,
                Resource::TYPE_INDEX,
            ],
            $report,
            [Resource::TYPE_DATABASE_DOCUMENTSDB => ['documents']]
        );

        $this->assertSame(1, $report[Resource::TYPE_DATABASE_DOCUMENTSDB]);
        $this->assertSame(0, $report[Resource::TYPE_COLLECTION]);
        $this->assertSame(0, $report[Resource::TYPE_DOCUMENT]);
        $this->assertSame(0, $report[Resource::TYPE_INDEX]);
    }

    public function testReportTreatsMissingDocumentsDbIndexesTableAsEmpty(): void
    {
        $database = new UtopiaDocument([
            '$id' => 'documents',
            '$sequence' => '2',
            'name' => 'Documents',
            '$createdAt' => '2026-01-01T00:00:00.000+00:00',
            '$updatedAt' => '2026-01-01T00:00:00.000+00:00',
            'enabled' => true,
            'type' => Resource::TYPE_DATABASE_DOCUMENTSDB,
            'database' => 'appwrite://database_selfhosted_fra1_2?database=appwrite&namespace=_1',
        ]);

        $collection = new UtopiaDocument([
            '$id' => 'articles',
            '$sequence' => '4',
            'name' => 'Articles',
            '$createdAt' => '2026-01-01T00:00:00.000+00:00',
            '$updatedAt' => '2026-01-01T00:00:00.000+00:00',
            'enabled' => true,
        ]);

        $dbForProject = $this->getMockBuilder(UtopiaDatabase::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['find', 'getDocument'])
            ->getMock();

        $dbForDatabase = $this->getMockBuilder(UtopiaDatabase::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['find', 'count'])
            ->getMock();

        $dbForProject
            ->expects($this->once())
            ->method('find')
            ->with(
                'databases',
                $this->callback(fn (array $queries): bool => $this->hasEqualQuery($queries, '$id', ['documents']))
            )
            ->willReturn([$database]);

        $dbForProject
            ->expects($this->once())
            ->method('getDocument')
            ->with('databases', 'documents')
            ->willReturn($database);

        $dbForDatabase
            ->expects($this->once())
            ->method('find')
            ->with('database_2', [])
            ->willReturn([$collection]);

        $dbForDatabase
            ->expects($this->exactly(2))
            ->method('count')
            ->willReturnCallback(function (string $collection, array $queries): int {
                return match ($collection) {
                    'database_2_collection_4' => 7,
                    'indexes' => throw new DatabaseNotFoundException('collection not found'),
                    default => 0,
                };
            });

        $reader = new Database(
            $dbForProject,
            fn (UtopiaDocument $database): UtopiaDatabase => $dbForDatabase,
        );

        $report = [];
        $reader->report(
            [
                Resource::TYPE_DATABASE_DOCUMENTSDB,
                Resource::TYPE_COLLECTION,
                Resource::TYPE_DOCUMENT,
                Resource::TYPE_INDEX,
            ],
            $report,
            [Resource::TYPE_DATABASE_DOCUMENTSDB => ['documents']]
        );

        $this->assertSame(1, $report[Resource::TYPE_DATABASE_DOCUMENTSDB]);
        $this->assertSame(1, $report[Resource::TYPE_COLLECTION]);
        $this->assertSame(7, $report[Resource::TYPE_DOCUMENT]);
        $this->assertSame(0, $report[Resource::TYPE_INDEX]);
    }

    public function testListIndexesTreatsMissingIndexesTableAsEmpty(): void
    {
        $database = new UtopiaDocument([
            '$id' => 'documents',
            '$sequence' => '2',
            'name' => 'Documents',
            '$createdAt' => '2026-01-01T00:00:00.000+00:00',
            '$updatedAt' => '2026-01-01T00:00:00.000+00:00',
            'enabled' => true,
            'type' => Resource::TYPE_DATABASE_DOCUMENTSDB,
            'database' => 'appwrite://database_selfhosted_fra1_2?database=appwrite&namespace=_1',
        ]);

        $collection = new UtopiaDocument([
            '$id' => 'articles',
            '$sequence' => '4',
            'name' => 'Articles',
            '$createdAt' => '2026-01-01T00:00:00.000+00:00',
            '$updatedAt' => '2026-01-01T00:00:00.000+00:00',
            'enabled' => true,
        ]);

        $dbForProject = $this->getMockBuilder(UtopiaDatabase::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDocument'])
            ->getMock();

        $dbForDatabase = $this->getMockBuilder(UtopiaDatabase::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDocument', 'find'])
            ->getMock();

        $dbForProject
            ->expects($this->once())
            ->method('getDocument')
            ->with('databases', 'documents')
            ->willReturn($database);

        $dbForDatabase
            ->expects($this->once())
            ->method('getDocument')
            ->with('database_2', 'articles')
            ->willReturn($collection);

        $dbForDatabase
            ->expects($this->once())
            ->method('find')
            ->with(
                'indexes',
                $this->callback(fn (array $queries): bool => $this->hasEqualQuery($queries, 'databaseInternalId', ['2'])
                    && $this->hasEqualQuery($queries, 'collectionInternalId', ['4']))
            )
            ->willThrowException(new DatabaseNotFoundException('Collection not found'));

        $reader = new Database(
            $dbForProject,
            fn (UtopiaDocument $database): UtopiaDatabase => $dbForDatabase,
        );

        $table = new TableResource(
            new DatabaseResource('documents', 'Documents', type: Resource::TYPE_DATABASE_DOCUMENTSDB),
            'Articles',
            'articles'
        );

        $this->assertSame([], $reader->listIndexes($table));
    }

    public function testListRowsTreatsMissingRowTableAsEmpty(): void
    {
        $database = new UtopiaDocument([
            '$id' => 'documents',
            '$sequence' => '2',
            'name' => 'Documents',
            '$createdAt' => '2026-01-01T00:00:00.000+00:00',
            '$updatedAt' => '2026-01-01T00:00:00.000+00:00',
            'enabled' => true,
            'type' => Resource::TYPE_DATABASE_DOCUMENTSDB,
            'database' => 'appwrite://database_selfhosted_fra1_2?database=appwrite&namespace=_1',
        ]);

        $collection = new UtopiaDocument([
            '$id' => 'articles',
            '$sequence' => '4',
            'name' => 'Articles',
            '$createdAt' => '2026-01-01T00:00:00.000+00:00',
            '$updatedAt' => '2026-01-01T00:00:00.000+00:00',
            'enabled' => true,
        ]);

        $dbForProject = $this->getMockBuilder(UtopiaDatabase::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDocument'])
            ->getMock();

        $dbForDatabase = $this->getMockBuilder(UtopiaDatabase::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDocument', 'find'])
            ->getMock();

        $dbForProject
            ->expects($this->once())
            ->method('getDocument')
            ->with('databases', 'documents')
            ->willReturn($database);

        $dbForDatabase
            ->expects($this->once())
            ->method('getDocument')
            ->with('database_2', 'articles')
            ->willReturn($collection);

        $dbForDatabase
            ->expects($this->once())
            ->method('find')
            ->with('database_2_collection_4', [])
            ->willThrowException(new DatabaseNotFoundException('Collection not found'));

        $reader = new Database(
            $dbForProject,
            fn (UtopiaDocument $database): UtopiaDatabase => $dbForDatabase,
        );

        $table = new TableResource(
            new DatabaseResource('documents', 'Documents', type: Resource::TYPE_DATABASE_DOCUMENTSDB),
            'Articles',
            'articles'
        );

        $this->assertSame([], $reader->listRows($table));
    }

    /**
     * @param array<Query> $queries
     * @param array<string> $values
     */
    private function hasEqualQuery(array $queries, string $attribute, array $values): bool
    {
        foreach ($queries as $query) {
            if (
                $query instanceof Query &&
                $query->getMethod() === Query::TYPE_EQUAL &&
                $query->getAttribute() === $attribute &&
                $query->getValues() === $values
            ) {
                return true;
            }
        }

        return false;
    }
}
