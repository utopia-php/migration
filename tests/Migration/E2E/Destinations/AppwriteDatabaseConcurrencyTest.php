<?php

namespace Utopia\Tests\E2E\Destinations;

use Closure;
use Override;
use PDO;
use Pdo\Sqlite as SQLiteConnection;
use PHPUnit\Framework\TestCase;
use Utopia\Cache\Adapter\None;
use Utopia\Cache\Cache;
use Utopia\Database\Adapter\SQLite;
use Utopia\Database\Attribute as UtopiaAttribute;
use Utopia\Database\Collection;
use Utopia\Database\Database as UtopiaDatabase;
use Utopia\Database\Document as UtopiaDocument;
use Utopia\Database\Exception as DatabaseException;
use Utopia\Migration\Destinations\Appwrite as AppwriteDestination;
use Utopia\Migration\Destinations\Appwrite\ProvisioningOwner;
use Utopia\Migration\Destinations\OnDuplicate;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Database\Database as DatabaseResource;
use Utopia\Migration\Transfer;
use Utopia\Query\Schema\ColumnType;
use Utopia\Tests\Unit\Adapters\MockSource;

final class RecordingSQLiteProjectDatabase extends UtopiaDatabase
{
    /** @var list<array<string, mixed>> */
    public array $databaseWrites = [];

    public ?Closure $beforeBackingCollectionCreate = null;

    public bool $throwAfterBackingCollectionCallback = false;

    #[Override]
    public function updateDocument(string $collection, string $id, UtopiaDocument $document): UtopiaDocument
    {
        if ($collection === 'databases') {
            $this->databaseWrites[] = $document->getArrayCopy();
        }

        return parent::updateDocument($collection, $id, $document);
    }

    #[Override]
    public function createCollection(Collection $collection): Collection
    {
        if (
            $this->beforeBackingCollectionCreate !== null
            && \str_starts_with($collection->getId(), 'database_')
        ) {
            $callback = $this->beforeBackingCollectionCreate;
            $this->beforeBackingCollectionCreate = null;
            $callback();

            if ($this->throwAfterBackingCollectionCallback) {
                throw new DatabaseException('backing collection creation failed');
            }
        }

        return parent::createCollection($collection);
    }
}

final class AppwriteDatabaseConcurrencyTest extends TestCase
{
    public function testStaleIncompleteClaimLosesAfterAnotherSuccessorCommits(): void
    {
        foreach (['provisioning', 'failed'] as $status) {
            [$second, $third, $path] = $this->createSharedDatabases();

            try {
                $this->seedDatabase($second, $status, 'migration-shared', 'attempt-first');
                $winner = null;
                $loser = $this->createDestination(
                    $second,
                    'migration-shared',
                    'attempt-second',
                    function (UtopiaDocument $snapshot) use ($third, &$winner): ProvisioningOwner {
                        $winner = $this->createDestination(
                            $third,
                            'migration-shared',
                            'attempt-third',
                            static fn (UtopiaDocument $document): ProvisioningOwner => new ProvisioningOwner(
                                'migration-shared',
                                'attempt-first',
                            ),
                        );
                        $this->runTransfer($third, $winner);

                        return new ProvisioningOwner('migration-shared', 'attempt-first');
                    },
                );
                $second->databaseWrites = [];

                $this->runTransfer($second, $loser);

                $database = $this->getDatabaseDocument($third);
                $this->assertInstanceOf(AppwriteDestination::class, $winner);
                $this->assertSame([], $this->errorMessages($winner));
                $this->assertNotSame([], $this->errorMessages($loser));
                $this->assertSame([], $second->databaseWrites);
                $this->assertSame('ready', $database->getAttribute('status'));
                $this->assertSame('migration-shared', $database->getAttribute('migrationId'));
                $this->assertSame('attempt-third', $database->getAttribute('migrationAttemptId'));
            } finally {
                $this->removeSQLiteFiles($path);
            }
        }
    }

    public function testStaleOwnerCannotMarkDatabaseReadyAfterTakeover(): void
    {
        [$second, $third, $path] = $this->createSharedDatabases();

        try {
            $this->seedDatabase($second, 'provisioning', 'migration-shared', 'attempt-first');
            $successor = null;
            $destination = $this->createDestination(
                $second,
                'migration-shared',
                'attempt-second',
                static fn (UtopiaDocument $snapshot): ProvisioningOwner => new ProvisioningOwner(
                    'migration-shared',
                    'attempt-first',
                ),
            );

            $this->runTransfer(
                $second,
                $destination,
                function () use ($third, &$successor): void {
                    $successor = $this->createDestination(
                        $third,
                        'migration-shared',
                        'attempt-third',
                        static fn (UtopiaDocument $snapshot): ProvisioningOwner => new ProvisioningOwner(
                            'migration-shared',
                            'attempt-second',
                        ),
                    );
                    $this->claimWithoutTerminalTransition($third, $successor);
                },
            );

            $database = $this->getDatabaseDocument($third);
            $readyWrites = \array_values(\array_filter(
                $second->databaseWrites,
                static fn (array $document): bool => ($document['status'] ?? null) === 'ready',
            ));
            $this->assertInstanceOf(AppwriteDestination::class, $successor);
            $this->assertSame([], $this->errorMessages($destination));
            $this->assertSame([], $this->errorMessages($successor));
            $this->assertSame([], $readyWrites);
            $this->assertSame('provisioning', $database->getAttribute('status'));
            $this->assertSame('migration-shared', $database->getAttribute('migrationId'));
            $this->assertSame('attempt-third', $database->getAttribute('migrationAttemptId'));
        } finally {
            $this->removeSQLiteFiles($path);
        }
    }

    public function testStaleOwnerCannotMarkDatabaseFailedAfterTakeover(): void
    {
        [$second, $third, $path] = $this->createSharedDatabases();

        try {
            $this->seedDatabase($second, 'failed', 'migration-shared', 'attempt-first');
            $successor = null;
            $second->throwAfterBackingCollectionCallback = true;
            $second->beforeBackingCollectionCreate = function () use ($third, &$successor): void {
                $successor = $this->createDestination(
                    $third,
                    'migration-shared',
                    'attempt-third',
                    static fn (UtopiaDocument $snapshot): ProvisioningOwner => new ProvisioningOwner(
                        'migration-shared',
                        'attempt-second',
                    ),
                );
                $this->claimWithoutTerminalTransition($third, $successor);
            };
            $destination = $this->createDestination(
                $second,
                'migration-shared',
                'attempt-second',
                static fn (UtopiaDocument $snapshot): ProvisioningOwner => new ProvisioningOwner(
                    'migration-shared',
                    'attempt-first',
                ),
            );

            $this->runTransfer($second, $destination);

            $database = $this->getDatabaseDocument($third);
            $failedWrites = \array_values(\array_filter(
                $second->databaseWrites,
                static fn (array $document): bool => ($document['status'] ?? null) === 'failed',
            ));
            $this->assertInstanceOf(AppwriteDestination::class, $successor);
            $this->assertNotSame([], $this->errorMessages($destination));
            $this->assertSame([], $this->errorMessages($successor));
            $this->assertSame([], $failedWrites);
            $this->assertSame('provisioning', $database->getAttribute('status'));
            $this->assertSame('migration-shared', $database->getAttribute('migrationId'));
            $this->assertSame('attempt-third', $database->getAttribute('migrationAttemptId'));
        } finally {
            $this->removeSQLiteFiles($path);
        }
    }

    private function createDestination(
        UtopiaDatabase $database,
        string $migrationId,
        string $migrationAttemptId,
        callable $getRecoverableOwner,
    ): AppwriteDestination {
        return new AppwriteDestination(
            project: 'destination-project',
            endpoint: 'http://example.test/v1',
            key: 'test-key',
            dbForProject: $database,
            getDatabasesDB: static fn (UtopiaDocument $document): UtopiaDatabase => $database,
            collectionStructure: ['attributes' => [], 'indexes' => []],
            dbForPlatform: $database,
            projectInternalId: '1',
            owner: new ProvisioningOwner($migrationId, $migrationAttemptId),
            getRecoverableOwner: $getRecoverableOwner,
            onDuplicate: OnDuplicate::Fail,
        );
    }

    private function runTransfer(
        UtopiaDatabase $database,
        AppwriteDestination $destination,
        ?callable $callback = null,
    ): void {
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

        $transfer = new Transfer($source, $destination);
        $database->getAuthorization()->skip(
            static fn () => $transfer->run(
                [Resource::TYPE_DATABASE],
                $callback ?? static function (): void {
                },
            ),
        );
    }

    private function claimWithoutTerminalTransition(
        UtopiaDatabase $database,
        AppwriteDestination $destination,
    ): void {
        try {
            $this->runTransfer(
                $database,
                $destination,
                static function (): void {
                    throw new \RuntimeException('stop after provisioning claim');
                },
            );
            $this->fail('Expected transfer callback to stop before the terminal transition');
        } catch (\RuntimeException $error) {
            $this->assertSame('stop after provisioning claim', $error->getMessage());
        }
    }

    /** @return array{RecordingSQLiteProjectDatabase, RecordingSQLiteProjectDatabase, string} */
    private function createSharedDatabases(): array
    {
        $path = \tempnam(\sys_get_temp_dir(), 'migration-owner-');
        if ($path === false) {
            throw new \RuntimeException('Failed to create SQLite test database');
        }

        $attributes = SQLite::getPDOAttributes();
        $attributes[PDO::ATTR_PERSISTENT] = false;
        $secondConnection = new SQLiteConnection('sqlite:'.$path, null, null, $attributes);
        $thirdConnection = new SQLiteConnection('sqlite:'.$path, null, null, $attributes);
        $secondConnection->exec('PRAGMA journal_mode = WAL');
        $secondConnection->exec('PRAGMA busy_timeout = 1000');
        $thirdConnection->exec('PRAGMA busy_timeout = 1000');

        $second = new RecordingSQLiteProjectDatabase(
            new SQLite($secondConnection),
            new Cache(new None()),
        );
        $third = new RecordingSQLiteProjectDatabase(
            new SQLite($thirdConnection),
            new Cache(new None()),
        );
        $namespace = 'migration_owner_'.\uniqid();
        foreach ([$second, $third] as $database) {
            $database
                ->setDatabase('appwrite')
                ->setNamespace($namespace);
        }

        $second->create();
        $second->createCollection(new Collection(
            id: 'databases',
            attributes: [
                $this->attribute('name', ColumnType::String, required: true, size: 256),
                $this->attribute('enabled', ColumnType::Boolean, default: true),
                $this->attribute('search', ColumnType::String, size: 16384),
                $this->attribute('originalId', ColumnType::String, size: UtopiaDatabase::LENGTH_KEY),
                $this->attribute('type', ColumnType::String, default: 'tablesdb', size: 128),
                $this->attribute('database', ColumnType::String, size: 2000),
                $this->attribute('status', ColumnType::String, size: 16),
                $this->attribute('migrationId', ColumnType::String, size: UtopiaDatabase::LENGTH_KEY),
                $this->attribute('migrationAttemptId', ColumnType::String, size: UtopiaDatabase::LENGTH_KEY),
            ],
        ));

        return [$second, $third, $path];
    }

    private function seedDatabase(
        UtopiaDatabase $database,
        string $status,
        string $migrationId,
        string $migrationAttemptId,
    ): UtopiaDocument {
        return $database->getAuthorization()->skip(
            static fn (): UtopiaDocument => $database->createDocument('databases', new UtopiaDocument([
                '$id' => 'database',
                'name' => 'Database',
                'enabled' => true,
                'search' => 'database Database',
                'originalId' => null,
                'type' => 'tablesdb',
                'database' => '',
                'status' => $status,
                'migrationId' => $migrationId,
                'migrationAttemptId' => $migrationAttemptId,
            ])),
        );
    }

    private function attribute(
        string $id,
        ColumnType $type,
        bool $required = false,
        mixed $default = null,
        int $size = 0,
    ): UtopiaAttribute {
        return new UtopiaAttribute(
            key: $id,
            type: $type,
            size: $size,
            required: $required,
            default: $default,
        );
    }

    private function getDatabaseDocument(UtopiaDatabase $database): UtopiaDocument
    {
        return $database->getAuthorization()->skip(
            static fn (): UtopiaDocument => $database->getDocument('databases', 'database'),
        );
    }

    /** @return list<string> */
    private function errorMessages(AppwriteDestination $destination): array
    {
        return \array_map(
            static fn (\Throwable $error): string => $error->getMessage(),
            $destination->getErrors(),
        );
    }

    private function removeSQLiteFiles(string $path): void
    {
        foreach ([$path, $path.'-wal', $path.'-shm'] as $file) {
            if (\is_file($file)) {
                \unlink($file);
            }
        }
    }
}
