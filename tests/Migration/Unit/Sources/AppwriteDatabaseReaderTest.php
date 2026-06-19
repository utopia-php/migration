<?php

namespace Utopia\Tests\Unit\Sources;

use PHPUnit\Framework\TestCase;
use Utopia\Database\Database as UtopiaDatabase;
use Utopia\Database\Document as UtopiaDocument;
use Utopia\Database\Query;
use Utopia\Migration\Resource;
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
