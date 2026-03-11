<?php

namespace Utopia\Tests\Unit\Destinations;

use PHPUnit\Framework\TestCase;
use Utopia\Database\Database as UtopiaDatabase;
use Utopia\Database\Document as UtopiaDocument;
use Utopia\Database\Exception\Duplicate as DuplicateException;
use Utopia\Migration\Cache;
use Utopia\Migration\Destinations\Appwrite;
use Utopia\Migration\Resources\Database\Database;
use Utopia\Migration\Resources\Database\Row;
use Utopia\Migration\Resources\Database\Table;

class AppwriteTest extends TestCase
{
    private function createAppwriteDestination(
        UtopiaDatabase $dbForProject,
        UtopiaDatabase $dbForDatabases,
    ): Appwrite {
        $getDatabasesDB = function () use ($dbForDatabases) {
            return $dbForDatabases;
        };

        $appwrite = new Appwrite(
            'test-project',
            'https://localhost',
            'test-key',
            $dbForProject,
            $getDatabasesDB,
            []
        );

        $cache = new Cache();
        $appwrite->registerCache($cache);

        return $appwrite;
    }

    private function createMockDatabases(): array
    {
        $dbForProject = $this->createMock(UtopiaDatabase::class);

        $dbDoc = new UtopiaDocument([
            '$id' => 'db1',
            '$sequence' => '1',
        ]);
        $tableDoc = new UtopiaDocument([
            '$id' => 'table1',
            '$sequence' => '2',
            'attributes' => [],
        ]);

        $dbForProject->method('getDocument')
            ->willReturnCallback(function (string $collection) use ($dbDoc, $tableDoc) {
                if ($collection === 'databases') {
                    return $dbDoc;
                }
                return $tableDoc;
            });

        $dbForDatabases = $this->createMock(UtopiaDatabase::class);

        $adapter = $this->createMock(\Utopia\Database\Adapter::class);
        $adapter->method('getSupportForAttributes')->willReturn(false);
        $dbForDatabases->method('getAdapter')->willReturn($adapter);

        $dbForDatabases->method('skipRelationshipsExistCheck')
            ->willReturnCallback(function (callable $callback) {
                return $callback();
            });

        return [$dbForProject, $dbForDatabases];
    }

    /**
     * Test that createRecord handles DuplicateException from batch createDocuments
     * by falling back to one-by-one insertion and skipping duplicates.
     *
     * This reproduces the "Document already exists" error from Sentry (CLOUD-3JMT).
     */
    public function testCreateRecordHandlesDuplicateDocuments(): void
    {
        [$dbForProject, $dbForDatabases] = $this->createMockDatabases();

        // Batch createDocuments throws DuplicateException
        $dbForDatabases->method('createDocuments')
            ->willThrowException(new DuplicateException('Document already exists'));

        // Fallback createDocument: first succeeds, second throws duplicate (skipped)
        $createDocumentCallCount = 0;
        $dbForDatabases->method('createDocument')
            ->willReturnCallback(function (string $collection, UtopiaDocument $doc) use (&$createDocumentCallCount) {
                $createDocumentCallCount++;
                if ($createDocumentCallCount === 2) {
                    throw new DuplicateException('Document already exists');
                }
                return $doc;
            });

        $appwrite = $this->createAppwriteDestination($dbForProject, $dbForDatabases);

        $database = new Database('db1', 'Test DB');
        $table = new Table($database, 'Test Table', 'table1');

        $method = new \ReflectionMethod(Appwrite::class, 'createRecord');

        $row1 = new Row('row1', $table, ['field1' => 'value1']);
        $row2 = new Row('row2', $table, ['field1' => 'value2']);

        // Buffer row1 (not last)
        $result1 = $method->invoke($appwrite, $row1, false);
        $this->assertTrue($result1);

        // Buffer row2 and flush (isLast=true) - should NOT throw
        $result2 = $method->invoke($appwrite, $row2, true);
        $this->assertTrue($result2);

        // Verify fallback was used
        $this->assertEquals(2, $createDocumentCallCount);
    }

    /**
     * Test that when batch createDocuments succeeds, no fallback is needed.
     */
    public function testCreateRecordBatchSucceeds(): void
    {
        [$dbForProject, $dbForDatabases] = $this->createMockDatabases();

        // Batch insert succeeds
        $dbForDatabases->method('createDocuments')
            ->willReturn(0);

        // createDocument (singular) should NOT be called
        $dbForDatabases->expects($this->never())->method('createDocument');

        $appwrite = $this->createAppwriteDestination($dbForProject, $dbForDatabases);

        $database = new Database('db1', 'Test DB');
        $table = new Table($database, 'Test Table', 'table1');

        $method = new \ReflectionMethod(Appwrite::class, 'createRecord');

        $row = new Row('row1', $table, ['field1' => 'value1']);
        $result = $method->invoke($appwrite, $row, true);

        $this->assertTrue($result);
    }
}
