<?php

namespace Utopia\Tests\Unit\Sources;

use ArrayObject;
use Override;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Utopia\Cache\Adapter\Memory as MemoryCache;
use Utopia\Cache\Cache;
use Utopia\Database\Adapter\Memory as MemoryAdapter;
use Utopia\Database\Attribute;
use Utopia\Database\Collection;
use Utopia\Database\Database as UtopiaDatabase;
use Utopia\Database\Document;
use Utopia\Migration\Exception\Aborted;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Database\Database;
use Utopia\Migration\Resources\Database\Table;
use Utopia\Migration\Resources\Storage\Bucket;
use Utopia\Migration\Sources\CSV;
use Utopia\Migration\Sources\Firebase;
use Utopia\Migration\Sources\JSON;
use Utopia\Migration\Sources\NHost;
use Utopia\Migration\Sources\Supabase;
use Utopia\Migration\Transfer;
use Utopia\Query\Schema\ColumnType;
use Utopia\Storage\Device\Local;
use Utopia\Tests\Unit\Adapters\MockDestination;

final class SourceAbortTest extends TestCase
{
    private const string CALLBACK = 'callback';

    /**
     * @param array<string> $resources
     * @param array<Resource> $cached
     */
    #[DataProvider('firebaseProvider')]
    public function testFirebaseStopsAtTheAbort(array $resources, array $cached): void
    {
        $abort = $this->superseded();
        $log = new ArrayObject();
        $source = new class (['project_id' => 'project'], $log) extends Firebase {
            /**
             * @param array<string, mixed> $serviceAccount
             * @param ArrayObject<int, string> $log
             */
            public function __construct(array $serviceAccount, private readonly ArrayObject $log)
            {
                parent::__construct($serviceAccount);
            }

            #[Override]
            protected function call(string $method, string $path = '', array $headers = [], array $params = [], array &$responseHeaders = []): array|string
            {
                $this->log->append($method . ' ' . $path);

                return match (true) {
                    \str_ends_with($path, '/config') => ['signIn' => ['hashConfig' => []]],
                    \str_ends_with($path, '/downloadAccount') => ['users' => [['localId' => 'user']]],
                    \str_ends_with($path, ':listCollectionIds') => ['collectionIds' => ['collection']],
                    \str_ends_with($path, '/storage/v1/b') => ['items' => [['id' => 'bucket', 'name' => 'bucket']]],
                    \str_ends_with($path, '/o') => ['items' => [['name' => 'file']]],
                    \str_ends_with($path, '?alt=media') => 'data',
                    default => [],
                };
            }
        };
        $transfer = new Transfer($source, new MockDestination());

        foreach ($cached as $resource) {
            $transfer->getCache()->add($resource);
        }

        $this->assertAborted($abort, fn () => $transfer->run($resources, $this->abortingCallback($log, $abort)));

        $events = $log->getArrayCopy();
        $this->assertSame(\count($events) - 1, \array_search(self::CALLBACK, $events, true), 'Nothing may be requested after the abort.');
        $this->assertSame([], $source->getErrors());
    }

    /**
     * @return array<string, array{array<string>, array<Resource>}>
     */
    public static function firebaseProvider(): array
    {
        return [
            'users' => [[Resource::TYPE_USER, Resource::TYPE_BUCKET], []],
            'databases' => [[Resource::TYPE_DATABASE, Resource::TYPE_BUCKET], []],
            'tables' => [[Resource::TYPE_TABLE, Resource::TYPE_BUCKET], []],
            'buckets' => [[Resource::TYPE_BUCKET, Resource::TYPE_USER], []],
            'files' => [[Resource::TYPE_FILE, Resource::TYPE_USER], [(new Bucket('bucket', 'bucket'))->setOriginalId('bucket')]],
        ];
    }

    /**
     * @param array<Resource> $cached
     */
    #[DataProvider('nhostProvider')]
    public function testNHostStopsAtTheAbort(string $type, array $cached): void
    {
        $abort = $this->superseded();
        $log = new ArrayObject();
        $source = new NHost('subdomain', 'region', 'secret', 'database', 'username', 'password');
        $source->pdo = $this->pdo($log, $abort);

        $this->assertStopsAtTheFirstEvent($source, $type, $cached, $log, $abort);
    }

    /**
     * @return array<string, array{string, array<Resource>}>
     */
    public static function nhostProvider(): array
    {
        $database = new Database('public', 'public');
        $table = new Table($database, 'table', 'table');

        return [
            'users' => [Resource::TYPE_USER, []],
            'databases' => [Resource::TYPE_DATABASE, []],
            'tables' => [Resource::TYPE_TABLE, [$database]],
            'columns' => [Resource::TYPE_COLUMN, [$table]],
            'rows' => [Resource::TYPE_ROW, [$database, $table]],
            'indexes' => [Resource::TYPE_INDEX, [$table]],
            'buckets' => [Resource::TYPE_BUCKET, []],
            'files' => [Resource::TYPE_FILE, [new Bucket('bucket', 'bucket')]],
        ];
    }

    /**
     * @param array<Resource> $cached
     */
    #[DataProvider('supabaseProvider')]
    public function testSupabaseStopsAtTheAbort(string $type, array $cached): void
    {
        $abort = $this->superseded();
        $log = new ArrayObject();
        $source = new class () extends Supabase {
            public function __construct()
            {
                // The real constructor connects to Postgres; the test supplies the database instead.
            }
        };
        $source->pdo = $this->pdo($log, $abort);

        $this->assertStopsAtTheFirstEvent($source, $type, $cached, $log, $abort);
    }

    /**
     * @return array<string, array{string, array<Resource>}>
     */
    public static function supabaseProvider(): array
    {
        return [
            'users' => [Resource::TYPE_USER, []],
            'buckets' => [Resource::TYPE_BUCKET, []],
            'files' => [Resource::TYPE_FILE, [new Bucket('bucket', 'bucket')]],
        ];
    }

    public function testCsvStopsAtTheAbort(): void
    {
        $abort = $this->superseded();
        $directory = \sys_get_temp_dir() . '/csv_abort_' . \uniqid();
        \mkdir($directory);
        $device = new Local($directory);
        $filePath = $device->getPath('rows.csv');
        \file_put_contents($filePath, "\$id,name\nfirst,First\nsecond,Second\n");

        $source = CSV::fromResourceIds('database', 'table', $filePath, $device, $this->projectDatabase());
        $transfer = new Transfer($source, new MockDestination());

        try {
            $this->assertAborted($abort, static fn () => $transfer->run([Resource::TYPE_ROW], static fn () => throw $abort));
            $this->assertSame([], $source->getErrors());
        } finally {
            \array_map(\unlink(...), \glob($directory . '/*') ?: []);
            \rmdir($directory);
        }
    }

    public function testJsonStopsAtTheAbort(): void
    {
        $abort = $this->superseded();
        $directory = \sys_get_temp_dir() . '/json_abort_' . \uniqid();
        \mkdir($directory);
        $device = new Local($directory);
        $filePath = $device->getPath('rows.json');
        \file_put_contents($filePath, \json_encode([['$id' => 'first'], ['$id' => 'second']]) ?: '[]');

        $source = JSON::fromResourceIds('database', 'table', $filePath, $device, null);
        $transfer = new Transfer($source, new MockDestination());

        try {
            $this->assertAborted($abort, static fn () => $transfer->run([Resource::TYPE_ROW], static fn () => throw $abort));
            $this->assertSame([], $source->getErrors());
        } finally {
            \array_map(\unlink(...), \glob($directory . '/*') ?: []);
            \rmdir($directory);
        }
    }

    /**
     * Runs the type plus a type from a later group; the database answers every statement
     * with the abort and the callback aborts too, so the first event must also be the last.
     *
     * @param array<Resource> $cached
     * @param ArrayObject<int, string> $log
     */
    private function assertStopsAtTheFirstEvent(NHost $source, string $type, array $cached, ArrayObject $log, Aborted $abort): void
    {
        $transfer = new Transfer($source, new MockDestination());
        $later = \in_array($type, Transfer::GROUP_STORAGE_RESOURCES, true) ? Resource::TYPE_USER : Resource::TYPE_BUCKET;

        foreach ($cached as $resource) {
            $transfer->getCache()->add($resource);
        }

        $this->assertAborted($abort, fn () => $transfer->run([$type, $later], $this->abortingCallback($log, $abort)));

        $this->assertCount(1, $log, 'Nothing may happen after the abort.');
        $this->assertSame([], $source->getErrors());
    }

    private function superseded(): Aborted
    {
        return new class ('Migration attempt was superseded') extends Aborted {
        };
    }

    private function assertAborted(Aborted $abort, callable $run): void
    {
        try {
            $run();
        } catch (Aborted $caught) {
            $this->assertSame($abort, $caught);

            return;
        }

        $this->fail('The abort did not stop the transfer.');
    }

    /**
     * @param ArrayObject<int, string> $log
     */
    private function abortingCallback(ArrayObject $log, Aborted $abort): callable
    {
        return static function () use ($log, $abort): never {
            $log->append(self::CALLBACK);

            throw $abort;
        };
    }

    /**
     * @param ArrayObject<int, string> $log
     */
    private function pdo(ArrayObject $log, Aborted $abort): PDO
    {
        return new class ($log, $abort) extends PDO {
            /**
             * @param ArrayObject<int, string> $log
             */
            public function __construct(private readonly ArrayObject $log, private readonly Aborted $abort)
            {
                parent::__construct('sqlite::memory:');
            }

            #[Override]
            public function prepare(string $query, array $options = []): never
            {
                $this->log->append($query);

                throw $this->abort;
            }

            #[Override]
            public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): never
            {
                $this->log->append($query);

                throw $this->abort;
            }
        };
    }

    private function projectDatabase(): UtopiaDatabase
    {
        $database = new UtopiaDatabase(new MemoryAdapter(), new Cache(new MemoryCache()));
        $database
            ->setDatabase('appwrite')
            ->setNamespace('_project');
        $database->create();
        $database->getAuthorization()->disable();

        $database->createCollection(new Collection(
            id: 'databases',
            attributes: [
                new Attribute(key: 'name', type: ColumnType::String, size: 256),
                new Attribute(key: 'type', type: ColumnType::String, size: 128),
            ],
        ));
        $metadata = $database->createDocument('databases', new Document([
            '$id' => 'database',
            'name' => 'Database',
            'type' => 'tablesdb',
        ]));

        $tables = 'database_' . $metadata->getSequence();
        $database->createCollection(new Collection(
            id: $tables,
            attributes: [new Attribute(key: 'name', type: ColumnType::String, size: 256)],
        ));
        $database->createDocument($tables, new Document(['$id' => 'table', 'name' => 'Table']));

        $database->createCollection(new Collection(
            id: 'attributes',
            attributes: [
                new Attribute(key: 'databaseInternalId', type: ColumnType::String, size: 64),
                new Attribute(key: 'collectionInternalId', type: ColumnType::String, size: 64),
            ],
        ));

        return $database;
    }
}
