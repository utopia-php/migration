<?php

namespace Utopia\Tests\Unit\Destinations;

use Override;
use PHPUnit\Framework\TestCase;
use Utopia\Cache\Adapter\Memory as MemoryCache;
use Utopia\Cache\Cache;
use Utopia\Database\Adapter\Memory as MemoryAdapter;
use Utopia\Database\Attribute as UtopiaAttribute;
use Utopia\Database\Capability;
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

class ReplicaMemoryAdapter extends MemoryAdapter
{
    /** @return array<Capability> */
    #[Override]
    public function capabilities(): array
    {
        return [...parent::capabilities(), Capability::TransactionRetries];
    }
}

final class StandaloneMemoryAdapter extends ReplicaMemoryAdapter
{
    #[Override]
    public function withTransaction(callable $callback): mixed
    {
        return $callback();
    }
}

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

final class ReloadFailingProjectDatabase extends UtopiaDatabase
{
    public bool $failNextDatabasesRead = false;

    #[Override]
    public function getDocument(string $collection, string $id, array $queries = [], bool $forUpdate = false): UtopiaDocument
    {
        $document = parent::getDocument($collection, $id, $queries, $forUpdate);
        if ($this->failNextDatabasesRead && $collection === 'databases' && !$document->isEmpty()) {
            $this->failNextDatabasesRead = false;

            return new UtopiaDocument();
        }

        return $document;
    }
}

/**
 * Fails the reload and every status write while the metadata store is unavailable,
 * which is the only way production reaches a document stranded in `provisioning`:
 * markDatabaseFailed() swallows its own error so it cannot mask the original throw.
 */
class StrandedProvisioningProjectDatabase extends UtopiaDatabase
{
    public bool $failNextDatabasesRead = false;

    public bool $failDatabasesWrites = false;

    #[Override]
    public function getDocument(string $collection, string $id, array $queries = [], bool $forUpdate = false): UtopiaDocument
    {
        $document = parent::getDocument($collection, $id, $queries, $forUpdate);
        if ($this->failNextDatabasesRead && $collection === 'databases' && !$document->isEmpty()) {
            $this->failNextDatabasesRead = false;

            return new UtopiaDocument();
        }

        return $document;
    }

    #[Override]
    public function updateDocument(string $collection, string $id, UtopiaDocument $document): UtopiaDocument
    {
        if ($this->failDatabasesWrites && $collection === 'databases') {
            throw new DatabaseException('metadata store unavailable');
        }

        return parent::updateDocument($collection, $id, $document);
    }
}

final class InterleavingProjectDatabase extends UtopiaDatabase
{
    public bool $interceptNextDatabasesReload = false;

    /** @var (callable(): void)|null */
    public $onDatabasesReload = null;

    #[Override]
    public function getDocument(string $collection, string $id, array $queries = [], bool $forUpdate = false): UtopiaDocument
    {
        $document = parent::getDocument($collection, $id, $queries, $forUpdate);
        if (
            $this->interceptNextDatabasesReload
            && $collection === 'databases'
            && ! $document->isEmpty()
        ) {
            $this->interceptNextDatabasesReload = false;
            ($this->onDatabasesReload)();
        }

        return $document;
    }
}

class RecordingProjectDatabase extends UtopiaDatabase
{
    /** @var list<array{operation: string, document: array<string, mixed>}> */
    public array $databaseWrites = [];

    public bool $failReadyWrite = false;

    #[Override]
    public function createDocument(string $collection, UtopiaDocument $document): UtopiaDocument
    {
        if ($collection === 'databases') {
            $this->databaseWrites[] = [
                'operation' => 'create',
                'document' => $document->getArrayCopy(),
            ];
        }

        return parent::createDocument($collection, $document);
    }

    #[Override]
    public function updateDocument(string $collection, string $id, UtopiaDocument $document): UtopiaDocument
    {
        if ($collection === 'databases') {
            $this->databaseWrites[] = [
                'operation' => 'update',
                'document' => $document->getArrayCopy(),
            ];
        }

        if (
            $this->failReadyWrite
            && $collection === 'databases'
            && $document->getAttribute('status') === 'ready'
        ) {
            throw new DatabaseException('ready status unavailable');
        }

        return parent::updateDocument($collection, $id, $document);
    }
}

final class FinalizerInterleavingProjectDatabase extends RecordingProjectDatabase
{
    public ?\Closure $beforeFinalizerRead = null;

    #[Override]
    public function getDocument(string $collection, string $id, array $queries = [], bool $forUpdate = false): UtopiaDocument
    {
        $document = parent::getDocument($collection, $id, $queries, $forUpdate);
        if ($forUpdate && $collection === 'databases' && $this->beforeFinalizerRead !== null) {
            $callback = $this->beforeFinalizerRead;
            $this->beforeFinalizerRead = null;
            $callback();
        }

        return $document;
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
            $this->assertFalse(
                $database->getCollection('database_'.$created->getSequence())->isEmpty(),
                'Metadata collection must use the persisted database sequence',
            );
        }
    }

    public function testDatabaseCreationPreservesLifecycleThroughLegacyAndExplicitEntrypoints(): void
    {
        foreach ([false, true] as $explicit) {
            $database = new UtopiaDatabase(
                new StandaloneMemoryAdapter(),
                new Cache(new MemoryCache()),
            );
            $this->createProjectDatabase(withStatus: true, database: $database);

            $destination = $this->runDatabaseTransfer($database, $explicit);

            $created = $this->getDatabaseDocument($database);
            $this->assertSame([], $this->errorMessages($destination));
            $this->assertSame(1, $destination->runCount);
            $this->assertFalse($created->isEmpty());
            $this->assertSame('ready', $created->getAttribute('status'));
            $this->assertFalse(
                $database->getCollection('database_'.$created->getSequence())->isEmpty(),
                'Metadata collection must use the persisted database sequence',
            );
        }
    }

    public function testCreatePersistsProvisioningAndOwnerAtomically(): void
    {
        $database = new RecordingProjectDatabase(new MemoryAdapter(), new Cache(new MemoryCache()));
        $this->createProjectDatabase(withStatus: true, database: $database);

        $destination = $this->runDatabaseTransfer(
            $database,
            explicit: false,
            migrationId: 'migration-create',
            migrationAttemptId: 'attempt-create',
        );

        $this->assertSame([], $this->errorMessages($destination));
        $this->assertSame('create', $database->databaseWrites[0]['operation']);
        $this->assertSame('provisioning', $database->databaseWrites[0]['document']['status']);
        $this->assertSame('migration-create', $database->databaseWrites[0]['document']['migrationId']);
        $this->assertSame('attempt-create', $database->databaseWrites[0]['document']['migrationAttemptId']);
    }

    public function testStandaloneUniqueCreateCannotFinalizeAfterOwnerMismatch(): void
    {
        $database = new FinalizerInterleavingProjectDatabase(
            new StandaloneMemoryAdapter(),
            new Cache(new MemoryCache()),
        );
        $this->createProjectDatabase(withStatus: true, database: $database);
        $database->beforeFinalizerRead = static function () use ($database): void {
            $database->updateDocument(
                'databases',
                'database',
                new UtopiaDocument([
                    'status' => 'provisioning',
                    'migrationId' => 'migration-successor',
                    'migrationAttemptId' => 'attempt-successor',
                ]),
            );
        };

        $destination = $this->runDatabaseTransfer(
            $database,
            explicit: false,
            migrationId: 'migration-create',
            migrationAttemptId: 'attempt-create',
        );

        $terminalWrites = \array_values(\array_filter(
            $database->databaseWrites,
            static fn (array $write): bool => \in_array(
                $write['document']['status'] ?? null,
                ['ready', 'failed'],
                true,
            ),
        ));
        $created = $this->getDatabaseDocument($database);
        $this->assertSame([], $this->errorMessages($destination));
        $this->assertSame([], $terminalWrites);
        $this->assertSame('provisioning', $created->getAttribute('status'));
        $this->assertSame('migration-successor', $created->getAttribute('migrationId'));
        $this->assertSame('attempt-successor', $created->getAttribute('migrationAttemptId'));
    }

    public function testAuthorizedOverwritePersistsProvisioningAndNewOwnerAtomically(): void
    {
        $database = new RecordingProjectDatabase(new ReplicaMemoryAdapter(), new Cache(new MemoryCache()));
        $this->createProjectDatabase(withStatus: true, database: $database);
        $this->seedDatabase(
            $database,
            status: 'provisioning',
            migrationId: 'migration-old',
            migrationAttemptId: 'attempt-old',
        );
        $database->databaseWrites = [];

        $destination = $this->runDatabaseTransfer(
            $database,
            explicit: false,
            migrationId: 'migration-new',
            migrationAttemptId: 'attempt-new',
            getRecoverableOwner: static fn (UtopiaDocument $database): ProvisioningOwner => new ProvisioningOwner(
                'migration-old',
                'attempt-old',
            ),
        );

        $this->assertSame([], $this->errorMessages($destination));
        $write = $database->databaseWrites[0] ?? null;
        $this->assertNotNull($write);
        $this->assertSame('update', $write['operation']);
        $this->assertSame('provisioning', $write['document']['status']);
        $this->assertSame('migration-new', $write['document']['migrationId']);
        $this->assertSame('attempt-new', $write['document']['migrationAttemptId']);
    }

    public function testActiveProvisioningRefusalRetainsExistingOwner(): void
    {
        $database = new RecordingProjectDatabase(new StandaloneMemoryAdapter(), new Cache(new MemoryCache()));
        $this->createProjectDatabase(withStatus: true, database: $database);
        $this->seedDatabase(
            $database,
            status: 'provisioning',
            migrationId: 'migration-active',
            migrationAttemptId: 'attempt-active',
        );
        $database->databaseWrites = [];

        $destination = $this->runDatabaseTransfer(
            $database,
            explicit: false,
            migrationId: 'migration-colliding',
            migrationAttemptId: 'attempt-colliding',
            getRecoverableOwner: static fn (UtopiaDocument $database): ?ProvisioningOwner => null,
        );

        $existing = $this->getDatabaseDocument($database);
        $this->assertNotSame([], $this->errorMessages($destination));
        $this->assertSame([], $database->databaseWrites);
        $this->assertSame('provisioning', $existing->getAttribute('status'));
        $this->assertSame('migration-active', $existing->getAttribute('migrationId'));
        $this->assertSame('attempt-active', $existing->getAttribute('migrationAttemptId'));
    }

    public function testMissingOrMismatchedOwnerRefusesRecovery(): void
    {
        foreach ([null, 'migration-existing'] as $owner) {
            $database = new RecordingProjectDatabase(new StandaloneMemoryAdapter(), new Cache(new MemoryCache()));
            $this->createProjectDatabase(withStatus: true, database: $database);
            $this->seedDatabase(
                $database,
                status: 'provisioning',
                migrationId: $owner,
                migrationAttemptId: 'attempt-existing',
            );
            $database->databaseWrites = [];

            $destination = $this->runDatabaseTransfer(
                $database,
                explicit: false,
                migrationId: 'migration-new',
                migrationAttemptId: 'attempt-new',
                getRecoverableOwner: static fn (UtopiaDocument $database): ProvisioningOwner => new ProvisioningOwner(
                    'migration-terminal',
                    'attempt-existing',
                ),
            );

            $existing = $this->getDatabaseDocument($database);
            $this->assertNotSame([], $this->errorMessages($destination));
            $this->assertSame([], $database->databaseWrites);
            $this->assertSame('provisioning', $existing->getAttribute('status'));
            $this->assertSame($owner, $existing->getAttribute('migrationId'));
            $this->assertSame('attempt-existing', $existing->getAttribute('migrationAttemptId'));
        }
    }

    public function testMissingOrMismatchedAttemptRefusesRecovery(): void
    {
        foreach ([null, 'attempt-existing'] as $attemptId) {
            $database = new RecordingProjectDatabase(new StandaloneMemoryAdapter(), new Cache(new MemoryCache()));
            $this->createProjectDatabase(withStatus: true, database: $database);
            $this->seedDatabase(
                $database,
                status: 'provisioning',
                migrationId: 'migration-existing',
                migrationAttemptId: $attemptId,
            );
            $database->databaseWrites = [];

            $destination = $this->runDatabaseTransfer(
                $database,
                explicit: false,
                migrationId: 'migration-new',
                migrationAttemptId: 'attempt-new',
                getRecoverableOwner: static fn (UtopiaDocument $database): ProvisioningOwner => new ProvisioningOwner(
                    'migration-existing',
                    'attempt-terminal',
                ),
            );

            $existing = $this->getDatabaseDocument($database);
            $this->assertNotSame([], $this->errorMessages($destination));
            $this->assertSame([], $database->databaseWrites);
            $this->assertSame('provisioning', $existing->getAttribute('status'));
            $this->assertSame('migration-existing', $existing->getAttribute('migrationId'));
            $this->assertSame($attemptId, $existing->getAttribute('migrationAttemptId'));
        }
    }

    public function testRecoveryRefusesReusingThePriorAttempt(): void
    {
        $database = new RecordingProjectDatabase(new StandaloneMemoryAdapter(), new Cache(new MemoryCache()));
        $this->createProjectDatabase(withStatus: true, database: $database);
        $this->seedDatabase(
            $database,
            status: 'failed',
            migrationId: 'migration-shared',
            migrationAttemptId: 'attempt-shared',
        );
        $database->databaseWrites = [];

        $destination = $this->runDatabaseTransfer(
            $database,
            explicit: false,
            migrationId: 'migration-shared',
            migrationAttemptId: 'attempt-shared',
            getRecoverableOwner: static fn (UtopiaDocument $database): ProvisioningOwner => new ProvisioningOwner(
                'migration-shared',
                'attempt-shared',
            ),
        );

        $existing = $this->getDatabaseDocument($database);
        $this->assertNotSame([], $this->errorMessages($destination));
        $this->assertSame([], $database->databaseWrites);
        $this->assertSame('failed', $existing->getAttribute('status'));
        $this->assertSame('migration-shared', $existing->getAttribute('migrationId'));
        $this->assertSame('attempt-shared', $existing->getAttribute('migrationAttemptId'));
    }

    public function testPreExistingRecoveryRequiresAtomicMutationCapability(): void
    {
        foreach ([new MemoryAdapter(), new StandaloneMemoryAdapter()] as $adapter) {
            $database = new RecordingProjectDatabase($adapter, new Cache(new MemoryCache()));
            $this->createProjectDatabase(withStatus: true, database: $database);
            $this->seedDatabase(
                $database,
                status: 'failed',
                migrationId: 'migration-old',
                migrationAttemptId: 'attempt-old',
            );
            $database->databaseWrites = [];

            $destination = $this->runDatabaseTransfer(
                $database,
                explicit: false,
                migrationId: 'migration-new',
                migrationAttemptId: 'attempt-new',
                getRecoverableOwner: static fn (UtopiaDocument $database): ProvisioningOwner => new ProvisioningOwner(
                    'migration-old',
                    'attempt-old',
                ),
            );

            $existing = $this->getDatabaseDocument($database);
            $this->assertNotSame([], $this->errorMessages($destination));
            $this->assertSame([], $database->databaseWrites);
            $this->assertSame('failed', $existing->getAttribute('status'));
            $this->assertSame('migration-old', $existing->getAttribute('migrationId'));
            $this->assertSame('attempt-old', $existing->getAttribute('migrationAttemptId'));
        }
    }

    public function testHealthyOverwriteRequiresAtomicMutationCapability(): void
    {
        foreach ([new MemoryAdapter(), new StandaloneMemoryAdapter()] as $adapter) {
            $database = new RecordingProjectDatabase($adapter, new Cache(new MemoryCache()));
            $this->createProjectDatabase(withStatus: true, database: $database);
            $this->seedDatabase(
                $database,
                status: 'ready',
                migrationId: 'migration-old',
                migrationAttemptId: 'attempt-old',
            );
            $database->getAuthorization()->skip(
                static fn (): UtopiaDocument => $database->updateDocument(
                    'databases',
                    'database',
                    new UtopiaDocument(['name' => 'Old database']),
                ),
            );
            $database->databaseWrites = [];

            $destination = $this->runDatabaseTransfer(
                $database,
                explicit: false,
                migrationId: 'migration-new',
                migrationAttemptId: 'attempt-new',
                onDuplicate: OnDuplicate::Overwrite,
                resourceUpdatedAt: '2099-01-01T00:00:00.000+00:00',
            );

            $existing = $this->getDatabaseDocument($database);
            $this->assertNotSame([], $this->errorMessages($destination));
            $this->assertSame([], $database->databaseWrites);
            $this->assertSame('ready', $existing->getAttribute('status'));
            $this->assertSame('migration-old', $existing->getAttribute('migrationId'));
            $this->assertSame('attempt-old', $existing->getAttribute('migrationAttemptId'));
        }
    }

    public function testFailedDatabaseRecoveryRequiresExactTerminalOwner(): void
    {
        foreach (
            [
                'no attestation' => ['migration-terminal', null],
                'missing owner' => [null, 'migration-terminal'],
                'mismatched owner' => ['migration-existing', 'migration-terminal'],
                'unknown terminal owner' => ['migration-existing', null],
            ] as [$owner, $recoverableMigrationId]
        ) {
            $database = new RecordingProjectDatabase(new StandaloneMemoryAdapter(), new Cache(new MemoryCache()));
            $this->createProjectDatabase(withStatus: true, database: $database);
            $this->seedDatabase(
                $database,
                status: 'failed',
                migrationId: $owner,
                migrationAttemptId: 'attempt-existing',
            );
            $database->databaseWrites = [];

            $destination = $this->runDatabaseTransfer(
                $database,
                explicit: false,
                migrationId: 'migration-new',
                migrationAttemptId: 'attempt-new',
                getRecoverableOwner: static fn (UtopiaDocument $database): ?ProvisioningOwner => $recoverableMigrationId === null
                    ? null
                    : new ProvisioningOwner($recoverableMigrationId, 'attempt-existing'),
            );

            $existing = $this->getDatabaseDocument($database);
            $this->assertNotSame([], $this->errorMessages($destination));
            $this->assertSame([], $database->databaseWrites);
            $this->assertSame('failed', $existing->getAttribute('status'));
            $this->assertSame($owner, $existing->getAttribute('migrationId'));
            $this->assertSame('attempt-existing', $existing->getAttribute('migrationAttemptId'));
        }
    }

    public function testReadyWriteFailureRetainsProvisioningOwner(): void
    {
        $database = new RecordingProjectDatabase(new MemoryAdapter(), new Cache(new MemoryCache()));
        $this->createProjectDatabase(withStatus: true, database: $database);
        $database->failReadyWrite = true;

        $destination = $this->runDatabaseTransfer(
            $database,
            explicit: false,
            migrationId: 'migration-ready-failure',
            migrationAttemptId: 'attempt-ready-failure',
        );

        $created = $this->getDatabaseDocument($database);
        $this->assertSame([], $this->errorMessages($destination));
        $this->assertSame('provisioning', $created->getAttribute('status'));
        $this->assertSame('migration-ready-failure', $created->getAttribute('migrationId'));
        $this->assertSame('attempt-ready-failure', $created->getAttribute('migrationAttemptId'));
    }

    public function testReloadFailureMarksTheDatabaseFailed(): void
    {
        $database = new ReloadFailingProjectDatabase(
            new StandaloneMemoryAdapter(),
            new Cache(new MemoryCache()),
        );
        $this->createProjectDatabase(withStatus: true, database: $database);
        $database->failNextDatabasesRead = true;

        $destination = $this->runDatabaseTransfer($database, explicit: false);

        $created = $this->getDatabaseDocument($database);
        $this->assertNotSame([], $this->errorMessages($destination));
        $this->assertStringContainsString('Failed to reload created database', $this->errorMessages($destination)[0]);
        $this->assertSame('failed', $created->getAttribute('status'));
        $this->assertSame('migration-current', $created->getAttribute('migrationId'));
        $this->assertSame('attempt-current', $created->getAttribute('migrationAttemptId'));
        $this->assertTrue(
            $database->getCollection('database_'.$created->getSequence())->isEmpty(),
            'A reload failure must not leave a backing collection behind the metadata document',
        );
    }

    public function testFailedDatabaseRetrySucceedsUnderOnDuplicateFail(): void
    {
        $database = new ReloadFailingProjectDatabase(
            new ReplicaMemoryAdapter(),
            new Cache(new MemoryCache()),
        );
        $this->createProjectDatabase(withStatus: true, database: $database);
        $database->failNextDatabasesRead = true;

        $this->runDatabaseTransfer($database, explicit: false);

        $failed = $this->getDatabaseDocument($database);
        $this->assertSame('failed', $failed->getAttribute('status'));
        $this->assertTrue(
            $database->getCollection('database_'.$failed->getSequence())->isEmpty(),
        );

        $destination = $this->runDatabaseTransfer(
            $database,
            explicit: false,
            migrationId: 'migration-recovery',
            migrationAttemptId: 'attempt-recovery',
            getRecoverableOwner: static fn (UtopiaDocument $existing): ProvisioningOwner => new ProvisioningOwner(
                'migration-current',
                'attempt-current',
            ),
        );

        $recovered = $this->getDatabaseDocument($database);
        $this->assertSame([], $this->errorMessages($destination));
        $this->assertSame('ready', $recovered->getAttribute('status'));
        $this->assertSame($failed->getSequence(), $recovered->getSequence());
        $this->assertSame('migration-recovery', $recovered->getAttribute('migrationId'));
        $this->assertSame('attempt-recovery', $recovered->getAttribute('migrationAttemptId'));
        $this->assertFalse(
            $database->getCollection('database_'.$recovered->getSequence())->isEmpty(),
            'A Fail retry must recreate the backing collection for a previously failed database',
        );
    }

    public function testProvisioningDatabaseRetrySucceedsUnderOnDuplicateFail(): void
    {
        $database = new StrandedProvisioningProjectDatabase(
            new ReplicaMemoryAdapter(),
            new Cache(new MemoryCache()),
        );
        $this->createProjectDatabase(withStatus: true, database: $database);
        $database->failNextDatabasesRead = true;
        $database->failDatabasesWrites = true;

        $this->runDatabaseTransfer($database, explicit: false);

        $stranded = $this->getDatabaseDocument($database);
        $this->assertSame(
            'provisioning',
            $stranded->getAttribute('status'),
            'The reload threw and the write that would record the failure threw too, so the document is stranded mid-provision',
        );
        $this->assertTrue(
            $database->getCollection('database_'.$stranded->getSequence())->isEmpty(),
            'The backing collection was never created, so the database is unusable',
        );
        $database->failDatabasesWrites = false;

        $destination = $this->runDatabaseTransfer(
            $database,
            explicit: false,
            migrationId: 'migration-recovery',
            migrationAttemptId: 'attempt-recovery',
            getRecoverableOwner: static fn (UtopiaDocument $existing): ProvisioningOwner => new ProvisioningOwner(
                'migration-current',
                'attempt-current',
            ),
        );

        $recovered = $this->getDatabaseDocument($database);
        $this->assertSame([], $this->errorMessages($destination));
        $this->assertSame('ready', $recovered->getAttribute('status'));
        $this->assertSame($stranded->getSequence(), $recovered->getSequence());
        $this->assertSame('migration-recovery', $recovered->getAttribute('migrationId'));
        $this->assertSame('attempt-recovery', $recovered->getAttribute('migrationAttemptId'));
        $this->assertFalse(
            $database->getCollection('database_'.$recovered->getSequence())->isEmpty(),
            'A Fail retry must recover a database stranded in provisioning, not keep colliding with its metadata id',
        );
    }

    public function testConcurrentMigrationDoesNotRecoverAnActivelyProvisioningDatabase(): void
    {
        $database = new InterleavingProjectDatabase(
            new MemoryAdapter(),
            new Cache(new MemoryCache()),
        );
        $this->createProjectDatabase(withStatus: true, database: $database);

        $second = null;
        $statusDuringOverlap = null;
        $collectionExistsDuringOverlap = null;
        $database->onDatabasesReload = function () use (
            $database,
            &$second,
            &$statusDuringOverlap,
            &$collectionExistsDuringOverlap,
        ): void {
            $second = $this->runDatabaseTransfer(
                $database,
                explicit: false,
                migrationId: 'migration-second',
                migrationAttemptId: 'attempt-second',
            );
            $provisioning = $this->getDatabaseDocument($database);
            $statusDuringOverlap = $provisioning->getAttribute('status');
            $collectionExistsDuringOverlap = ! $database
                ->getCollection('database_'.$provisioning->getSequence())
                ->isEmpty();
        };
        $database->interceptNextDatabasesReload = true;

        $first = $this->runDatabaseTransfer(
            $database,
            explicit: false,
            migrationId: 'migration-first',
            migrationAttemptId: 'attempt-first',
        );

        $created = $this->getDatabaseDocument($database);
        $this->assertSame([], $this->errorMessages($first));
        $this->assertInstanceOf(CountingAppwriteDestination::class, $second);
        $this->assertNotSame([], $this->errorMessages($second));
        $this->assertStringContainsString('recovery owner', $this->errorMessages($second)[0]);
        $this->assertSame('provisioning', $statusDuringOverlap);
        $this->assertFalse($collectionExistsDuringOverlap);
        $this->assertSame('ready', $created->getAttribute('status'));
        $this->assertSame('migration-first', $created->getAttribute('migrationId'));
        $this->assertSame('attempt-first', $created->getAttribute('migrationAttemptId'));
        $this->assertFalse($database->getCollection('database_'.$created->getSequence())->isEmpty());
    }

    public function testAuthorizedOverwriteReplacesTerminalOwner(): void
    {
        $database = new RecordingProjectDatabase(new ReplicaMemoryAdapter(), new Cache(new MemoryCache()));
        $this->createProjectDatabase(withStatus: true, database: $database);
        $this->seedDatabase(
            $database,
            status: 'provisioning',
            migrationId: 'migration-terminal',
            migrationAttemptId: 'attempt-terminal',
        );

        $destination = $this->runDatabaseTransfer(
            $database,
            explicit: false,
            migrationId: 'migration-successor',
            migrationAttemptId: 'attempt-successor',
            getRecoverableOwner: static fn (UtopiaDocument $database): ProvisioningOwner => new ProvisioningOwner(
                'migration-terminal',
                'attempt-terminal',
            ),
        );

        $recovered = $this->getDatabaseDocument($database);
        $this->assertSame([], $this->errorMessages($destination));
        $this->assertSame('ready', $recovered->getAttribute('status'));
        $this->assertSame('migration-successor', $recovered->getAttribute('migrationId'));
        $this->assertSame('attempt-successor', $recovered->getAttribute('migrationAttemptId'));
    }

    private function createProjectDatabase(bool $withStatus, ?UtopiaDatabase $database = null): UtopiaDatabase
    {
        $database ??= new UtopiaDatabase(
            new MemoryAdapter(),
            new Cache(new MemoryCache()),
        );
        $database
            ->setDatabase('appwrite')
            ->setNamespace('_project');
        $database->create();

        $attributes = [
            $this->attribute('name', ColumnType::String, required: true, size: 256),
            $this->attribute('enabled', ColumnType::Boolean, default: true),
            $this->attribute('search', ColumnType::String, size: 16384),
            $this->attribute('originalId', ColumnType::String, size: UtopiaDatabase::LENGTH_KEY),
            $this->attribute('type', ColumnType::String, default: 'tablesdb', size: 128),
            $this->attribute('database', ColumnType::String, size: 2000),
        ];

        if ($withStatus) {
            $attributes[] = $this->attribute('status', ColumnType::String, size: 16);
            $attributes[] = $this->attribute('migrationId', ColumnType::String, size: UtopiaDatabase::LENGTH_KEY);
            $attributes[] = $this->attribute('migrationAttemptId', ColumnType::String, size: UtopiaDatabase::LENGTH_KEY);
        }

        $database->createCollection(new Collection(
            id: 'databases',
            attributes: $attributes,
        ));

        return $database;
    }

    private function seedDatabase(
        UtopiaDatabase $database,
        string $status,
        ?string $migrationId,
        ?string $migrationAttemptId,
    ): UtopiaDocument {
        $document = [
            '$id' => 'database',
            'name' => 'Database',
            'enabled' => true,
            'search' => 'database Database',
            'originalId' => null,
            'type' => 'tablesdb',
            'database' => '',
            'status' => $status,
        ];
        if ($migrationId !== null) {
            $document['migrationId'] = $migrationId;
        }
        if ($migrationAttemptId !== null) {
            $document['migrationAttemptId'] = $migrationAttemptId;
        }

        return $database->getAuthorization()->skip(
            static fn (): UtopiaDocument => $database->createDocument('databases', new UtopiaDocument($document)),
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

    private function runDatabaseTransfer(
        UtopiaDatabase $database,
        bool $explicit,
        string $migrationId = 'migration-current',
        string $migrationAttemptId = 'attempt-current',
        ?callable $getRecoverableOwner = null,
        OnDuplicate $onDuplicate = OnDuplicate::Fail,
        string $resourceUpdatedAt = '',
    ): CountingAppwriteDestination {
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
            updatedAt: $resourceUpdatedAt,
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
            owner: new ProvisioningOwner($migrationId, $migrationAttemptId),
            getRecoverableOwner: $getRecoverableOwner
                ?? static fn (UtopiaDocument $document): ?ProvisioningOwner => null,
            onDuplicate: $onDuplicate,
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
