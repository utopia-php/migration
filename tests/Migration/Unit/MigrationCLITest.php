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

#[BackupGlobals(true)]
final class MigrationCLITest extends TestCase
{
    public function testHelpExplainsExplicitProvisioningRecoveryAttestation(): void
    {
        $this->assertStringContainsString('--recover-provisioning', \MigrationCLI::getHelp());
        $this->assertStringContainsString('no active migration', \MigrationCLI::getHelp());
        $this->assertStringContainsString('refused by default', \MigrationCLI::getHelp());
    }

    public function testProvisioningRecoveryRequiresExplicitOperatorAttestation(): void
    {
        foreach ([false, true] as $recover) {
            $database = $this->createProjectDatabase();
            $arguments = $recover ? ['MigrationCLI.php', '--recover-provisioning'] : ['MigrationCLI.php'];
            $cli = new TestMigrationCLI($arguments, $database);

            $destination = $cli->getDestination();
            $this->runTransfer($database, $destination);

            $created = $database->getAuthorization()->skip(
                static fn (): Document => $database->getDocument('databases', 'database'),
            );

            if (! $recover) {
                $this->assertNotSame([], $destination->getErrors());
                $this->assertSame('provisioning', $created->getAttribute('status'));
                $this->assertTrue($database->getCollection('database_'.$created->getSequence())->isEmpty());
                continue;
            }

            $this->assertSame([], $destination->getErrors());
            $this->assertSame('ready', $created->getAttribute('status'));
            $this->assertFalse($database->getCollection('database_'.$created->getSequence())->isEmpty());
        }
    }

    private function createProjectDatabase(): Database
    {
        $database = new Database(new MemoryAdapter(), new Cache(new MemoryCache()));
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
                'status' => 'provisioning',
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
