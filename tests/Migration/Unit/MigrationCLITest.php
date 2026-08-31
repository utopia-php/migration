<?php

namespace Utopia\Tests\Unit;

require_once \dirname(__DIR__, 3).'/bin/MigrationCLI.php';

use Override;
use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\TestCase;
use Utopia\Cache\Adapter\Memory as MemoryCache;
use Utopia\Cache\Cache;
use Utopia\Database\Adapter\Memory as MemoryAdapter;
use Utopia\Database\Attribute;
use Utopia\Database\Capability;
use Utopia\Database\Collection;
use Utopia\Database\Database;
use Utopia\Database\Document;
use Utopia\Migration\Destination;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Database\Database as DatabaseResource;
use Utopia\Migration\Transfer;
use Utopia\Query\Schema\ColumnType;
use Utopia\Tests\Unit\Adapters\MockSource;

final class TestMigrationCLI extends \MigrationCLI
{
    /** @param list<string> $arguments */
    public function __construct(array $arguments, private readonly Database $database)
    {
        parent::__construct($arguments);
    }

    #[Override]
    public function getDatabase(string $type): Database
    {
        return $this->database;
    }
}

final class TransactionalMemoryAdapter extends MemoryAdapter
{
    /** @return array<Capability> */
    #[Override]
    public function capabilities(): array
    {
        return [...parent::capabilities(), Capability::TransactionRetries];
    }
}

#[BackupGlobals(true)]
final class MigrationCLITest extends TestCase
{
    public function testHelpExplainsExplicitMigrationRecoveryAttestation(): void
    {
        $this->assertStringContainsString('--recover-migration-id=<prior-migration-id>', \MigrationCLI::getHelp());
        $this->assertStringContainsString('--recover-migration-attempt-id=<prior-attempt-id>', \MigrationCLI::getHelp());
        $this->assertStringNotContainsString('--recover-provisioning', \MigrationCLI::getHelp());
        $this->assertStringContainsString('--migration-id=', \MigrationCLI::getHelp());
        $this->assertStringContainsString('--migration-attempt-id=', \MigrationCLI::getHelp());
        $this->assertStringContainsString('reuse it for retries', \MigrationCLI::getHelp());
        $this->assertStringContainsString('fresh attempt', \MigrationCLI::getHelp());
        $this->assertStringContainsString('prior migration attempt is terminal', \MigrationCLI::getHelp());
        $this->assertStringContainsString('refused by default', \MigrationCLI::getHelp());
    }

    public function testIncompleteDatabaseRecoveryRequiresExactTerminalMigrationIdentifier(): void
    {
        $cases = [
            'absent' => [[], false],
            'bare migration' => [['--recover-migration-id', '--recover-migration-attempt-id=attempt-terminal'], false],
            'bare attempt' => [['--recover-migration-id=migration-terminal', '--recover-migration-attempt-id'], false],
            'empty migration' => [['--recover-migration-id=', '--recover-migration-attempt-id=attempt-terminal'], false],
            'empty attempt' => [['--recover-migration-id=migration-terminal', '--recover-migration-attempt-id='], false],
            'migration only' => [['--recover-migration-id=migration-terminal'], false],
            'attempt only' => [['--recover-migration-attempt-id=attempt-terminal'], false],
            'migration mismatch' => [['--recover-migration-id=migration-other', '--recover-migration-attempt-id=attempt-terminal'], false],
            'attempt mismatch' => [['--recover-migration-id=migration-terminal', '--recover-migration-attempt-id=attempt-other'], false],
            'retired unsafe option' => [['--recover-provisioning'], false],
            'exact' => [[
                '--recover-migration-id=migration-terminal',
                '--recover-migration-attempt-id=attempt-terminal',
            ], true],
        ];

        foreach (['provisioning', 'failed'] as $status) {
            foreach ($cases as [$recoveryArguments, $recover]) {
                $database = $this->createProjectDatabase($status);
                $arguments = [
                    'MigrationCLI.php',
                    '--migration-id=migration-current',
                    '--migration-attempt-id=attempt-current',
                    ...$recoveryArguments,
                ];
                $cli = new TestMigrationCLI($arguments, $database);

                $destination = $cli->getDestination();
                $this->runTransfer($database, $destination);

                $created = $database->getAuthorization()->skip(
                    static fn (): Document => $database->getDocument('databases', 'database'),
                );

                if (! $recover) {
                    $this->assertNotSame([], $destination->getErrors());
                    $this->assertSame($status, $created->getAttribute('status'));
                    $this->assertSame('migration-terminal', $created->getAttribute('migrationId'));
                    $this->assertSame('attempt-terminal', $created->getAttribute('migrationAttemptId'));
                    $this->assertTrue($database->getCollection('database_'.$created->getSequence())->isEmpty());
                    continue;
                }

                $this->assertSame([], $destination->getErrors());
                $this->assertSame('ready', $created->getAttribute('status'));
                $this->assertSame('migration-current', $created->getAttribute('migrationId'));
                $this->assertSame('attempt-current', $created->getAttribute('migrationAttemptId'));
                $this->assertFalse($database->getCollection('database_'.$created->getSequence())->isEmpty());
            }
        }
    }

    public function testAppwriteDestinationRequiresMigrationIdentifier(): void
    {
        $cli = new TestMigrationCLI(['MigrationCLI.php'], $this->createProjectDatabase());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('--migration-id is required for an Appwrite destination');

        $cli->getDestination();
    }

    public function testAppwriteDestinationRequiresMigrationAttemptIdentifier(): void
    {
        $cli = new TestMigrationCLI(
            ['MigrationCLI.php', '--migration-id=migration-current'],
            $this->createProjectDatabase(),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('--migration-attempt-id is required for an Appwrite destination');

        $cli->getDestination();
    }

    private function createProjectDatabase(string $status = 'provisioning'): Database
    {
        $database = new Database(new TransactionalMemoryAdapter(), new Cache(new MemoryCache()));
        $database
            ->setDatabase('appwrite')
            ->setNamespace('_project');
        $database->create();
        $database->createCollection(new Collection(
            id: 'databases',
            attributes: [
                new Attribute(key: 'name', type: ColumnType::String, size: 256, required: true),
                new Attribute(key: 'enabled', type: ColumnType::Boolean, default: true),
                new Attribute(key: 'search', type: ColumnType::String, size: 16384),
                new Attribute(key: 'originalId', type: ColumnType::String, size: Database::LENGTH_KEY),
                new Attribute(key: 'type', type: ColumnType::String, size: 128, default: 'tablesdb'),
                new Attribute(key: 'database', type: ColumnType::String, size: 2000),
                new Attribute(key: 'status', type: ColumnType::String, size: 16),
                new Attribute(key: 'migrationId', type: ColumnType::String, size: Database::LENGTH_KEY),
                new Attribute(key: 'migrationAttemptId', type: ColumnType::String, size: Database::LENGTH_KEY),
            ],
        ));
        $database->getAuthorization()->skip(
            static fn (): Document => $database->createDocument('databases', new Document([
                '$id' => 'database',
                'name' => 'Database',
                'enabled' => true,
                'search' => 'database Database',
                'originalId' => null,
                'type' => 'tablesdb',
                'database' => '',
                'status' => $status,
                'migrationId' => 'migration-terminal',
                'migrationAttemptId' => 'attempt-terminal',
            ])),
        );

        return $database;
    }

    private function runTransfer(Database $database, Destination $destination): void
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

        $transfer = new Transfer($source, $destination);
        $database->getAuthorization()->skip(
            static fn () => $transfer->run([Resource::TYPE_DATABASE], static function (): void {
            }),
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['DESTINATION_PROVIDER'] = 'appwrite';
        $_ENV['DESTINATION_APPWRITE_TEST_PROJECT'] = 'destination-project';
        $_ENV['DESTINATION_APPWRITE_TEST_ENDPOINT'] = 'http://example.test/v1';
        $_ENV['DESTINATION_APPWRITE_TEST_KEY'] = 'test-key';
        $_ENV['DESTINATION_APPWRITE_TEST_PROJECT_INTERNAL_ID'] = '1';
    }
}
