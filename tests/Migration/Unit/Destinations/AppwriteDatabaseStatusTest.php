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

class CountingAppwriteDestination extends AppwriteDestination
{
    public int $runCount = 0;

    #[Override]
    public function run(
        array $resources,
        callable $callback,
        string $rootResourceId = '',
        string $rootResourceType = '',
    ): void {
        $this->runCount++;
        parent::run($resources, $callback, $rootResourceId, $rootResourceType);
    }
}

final class AppwriteDatabaseStatusTest extends TestCase
{
    public function testDatabaseCreationOmitsStatusThroughLegacyAndExplicitEntrypoints(): void
    {
        foreach ([false, true] as $explicit) {
            $database = $this->createProjectDatabase(withStatus: false);

            $destination = $this->runDatabaseTransfer($database, $explicit);

            $created = $this->getDatabaseDocument($database);
            $this->assertSame([], $this->errorMessages($destination));
            $this->assertSame(1, $destination->runCount);
            $this->assertFalse($created->isEmpty());
            $this->assertArrayNotHasKey('status', $created->getArrayCopy());
        }
    }

    public function testDatabaseCreationPreservesLifecycleThroughLegacyAndExplicitEntrypoints(): void
    {
        foreach ([false, true] as $explicit) {
            $database = $this->createProjectDatabase(withStatus: true);

            $destination = $this->runDatabaseTransfer($database, $explicit);

            $created = $this->getDatabaseDocument($database);
            $this->assertSame([], $this->errorMessages($destination));
            $this->assertSame(1, $destination->runCount);
            $this->assertFalse($created->isEmpty());
            $this->assertSame('ready', $created->getAttribute('status'));
        }
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

    private function runDatabaseTransfer(UtopiaDatabase $database, bool $explicit): CountingAppwriteDestination
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

        $destination = new CountingAppwriteDestination(
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
            static function () use ($explicit, $transfer): void {
                if ($explicit) {
                    $transfer->runWithResourceSelector(
                        [Resource::TYPE_DATABASE],
                        static function (): void {
                        },
                        resourceId: 'database',
                        resourceInternalId: '1',
                        resourceType: Resource::TYPE_DATABASE,
                        parentResourceId: '',
                        parentResourceInternalId: '',
                        parentResourceType: '',
                    );

                    return;
                }

                $transfer->run(
                    [Resource::TYPE_DATABASE],
                    static function (): void {
                    },
                );
            },
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
