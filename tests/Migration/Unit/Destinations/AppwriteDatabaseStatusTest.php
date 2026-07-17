<?php

namespace Utopia\Tests\Unit\Destinations;

use Override;
use PHPUnit\Framework\TestCase;
use Utopia\Cache\Adapter\Memory as MemoryCache;
use Utopia\Cache\Cache;
use Utopia\Database\Adapter\Memory as MemoryAdapter;
use Utopia\Database\Database as UtopiaDatabase;
use Utopia\Database\Document as UtopiaDocument;
use Utopia\Migration\Destinations\Appwrite as AppwriteDestination;
use Utopia\Migration\Destinations\OnDuplicate;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Database\Database as DatabaseResource;
use Utopia\Migration\Transfer;
use Utopia\Tests\Unit\Adapters\MockSource;

final class AppwriteDatabaseStatusTest extends TestCase
{
    public function testDatabaseCreationOmitsStatusWhenDestinationSchemaDoesNotSupportIt(): void
    {
        $database = $this->createProjectDatabase(withStatus: false);

        $destination = $this->runDatabaseTransfer($database);

        $created = $this->getDatabaseDocument($database);
        $this->assertSame([], $this->errorMessages($destination));
        $this->assertFalse($created->isEmpty());
        $this->assertArrayNotHasKey('status', $created->getArrayCopy());
    }

    public function testDatabaseCreationPreservesLifecycleWhenDestinationSchemaSupportsStatus(): void
    {
        $database = $this->createProjectDatabase(withStatus: true);

        $destination = $this->runDatabaseTransfer($database);

        $created = $this->getDatabaseDocument($database);
        $this->assertSame([], $this->errorMessages($destination));
        $this->assertFalse($created->isEmpty());
        $this->assertSame('ready', $created->getAttribute('status'));
    }

    private function createProjectDatabase(bool $withStatus): UtopiaDatabase
    {
        $database = new UtopiaDatabase(
            new MemoryAdapter(),
            new Cache(new MemoryCache()),
        );
        $database
            ->setDatabase('appwrite')
            ->setNamespace('_project');
        $database->create();

        $attributes = [
            $this->attribute('name', UtopiaDatabase::VAR_STRING, required: true, size: 256),
            $this->attribute('enabled', UtopiaDatabase::VAR_BOOLEAN, default: true),
            $this->attribute('search', UtopiaDatabase::VAR_STRING, size: 16384),
            $this->attribute('originalId', UtopiaDatabase::VAR_STRING, size: UtopiaDatabase::LENGTH_KEY),
            $this->attribute('type', UtopiaDatabase::VAR_STRING, default: 'tablesdb', size: 128),
            $this->attribute('database', UtopiaDatabase::VAR_STRING, size: 2000),
        ];

        if ($withStatus) {
            $attributes[] = $this->attribute('status', UtopiaDatabase::VAR_STRING, size: 16);
        }

        $database->createCollection('databases', $attributes);

        return $database;
    }

    private function attribute(
        string $id,
        string $type,
        bool $required = false,
        mixed $default = null,
        int $size = 0,
    ): UtopiaDocument {
        return new UtopiaDocument([
            '$id' => $id,
            'type' => $type,
            'size' => $size,
            'required' => $required,
            'default' => $default,
            'array' => false,
            'signed' => true,
            'filters' => [],
        ]);
    }

    private function runDatabaseTransfer(UtopiaDatabase $database): AppwriteDestination
    {
        $source = new class () extends MockSource {
            #[Override]
            public function supportsDatabaseStatus(): bool
            {
                return true;
            }
        };
        $source->pushMockResource(new DatabaseResource(
            id: 'database',
            name: 'Database',
            type: 'tablesdb',
            database: 'source-dsn',
            databaseStatus: 'ready',
        ));

        $destination = new AppwriteDestination(
            project: 'destination-project',
            endpoint: 'http://example.test/v1',
            key: 'test-key',
            dbForProject: $database,
            getDatabasesDB: static fn (UtopiaDocument $document): UtopiaDatabase => $database,
            collectionStructure: ['attributes' => [], 'indexes' => []],
            dbForPlatform: $database,
            projectInternalId: '1',
            onDuplicate: OnDuplicate::Fail,
        );

        $transfer = new Transfer($source, $destination);
        $database->getAuthorization()->skip(
            static fn () => $transfer->run(
                [Resource::TYPE_DATABASE],
                static function (): void {
                },
            ),
        );

        return $destination;
    }

    private function getDatabaseDocument(UtopiaDatabase $database): UtopiaDocument
    {
        return $database->getAuthorization()->skip(
            static fn (): UtopiaDocument => $database->getDocument('databases', 'database'),
        );
    }

    /**
     * @return list<string>
     */
    private function errorMessages(AppwriteDestination $destination): array
    {
        return \array_map(
            static fn (\Throwable $error): string => $error->getMessage(),
            $destination->getErrors(),
        );
    }
}
