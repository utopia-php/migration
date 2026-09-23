<?php

namespace Utopia\Tests\Unit\Sources;

use Appwrite\Client;
use Appwrite\Service;
use Appwrite\Services\TablesDB;
use ArrayObject;
use LogicException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;
use Throwable;
use Utopia\Migration\Exception\Aborted;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Auth\Team;
use Utopia\Migration\Resources\Database\Database;
use Utopia\Migration\Resources\Database\Table;
use Utopia\Migration\Resources\Functions\Func;
use Utopia\Migration\Resources\Messaging\Topic;
use Utopia\Migration\Resources\Sites\Site;
use Utopia\Migration\Resources\Storage\Bucket;
use Utopia\Migration\Sources\Appwrite;
use Utopia\Migration\Sources\Appwrite\Reader\API;
use Utopia\Migration\Transfer;
use Utopia\Tests\Unit\Adapters\MockDestination;

final class AppwriteAbortTest extends TestCase
{
    private const string TIMESTAMP = '2026-09-24T00:00:00.000+00:00';

    public function testAbortFromTheProgressCallbackStopsLaterTypesAndGroups(): void
    {
        $abort = $this->superseded();
        $log = new ArrayObject();
        $source = $this->source($log, [
            '/teams' => ['total' => 1, 'teams' => [self::team('team')]],
            '/storage/buckets' => ['total' => 1, 'buckets' => [self::bucket('bucket')]],
            '/messaging/topics' => ['total' => 1, 'topics' => [self::topic('topic')]],
        ]);
        $destination = new MockDestination();
        $transfer = new Transfer($source, $destination);

        $this->assertAborted($abort, static fn () => $transfer->run(
            [Resource::TYPE_TEAM, Resource::TYPE_BUCKET, Resource::TYPE_TOPIC],
            static fn () => throw $abort,
        ));

        $this->assertSame(['/teams'], $log->getArrayCopy());
        $this->assertSame([Transfer::GROUP_AUTH], \array_keys($destination->data));
        $this->assertSame([], $source->getErrors());
    }

    /**
     * @param array<string> $resources
     * @param array<Resource> $cached
     * @param array<string, array<string, mixed>> $responses
     * @param array<string> $expected
     */
    #[DataProvider('itemProvider')]
    public function testAbortFromTheProgressCallbackStopsLaterItems(array $resources, array $cached, array $responses, array $expected): void
    {
        $abort = $this->superseded();
        $log = new ArrayObject();
        $source = $this->source($log, $responses);
        $transfer = new Transfer($source, new MockDestination());

        foreach ($cached as $resource) {
            $transfer->getCache()->add($resource);
        }

        $this->assertAborted($abort, static fn () => $transfer->run($resources, static fn () => throw $abort));

        $this->assertSame($expected, $log->getArrayCopy());
        $this->assertSame([], $source->getErrors());
    }

    /**
     * @return array<string, array{array<string>, array<Resource>, array<string, array<string, mixed>>, array<string>}>
     */
    public static function itemProvider(): array
    {
        $deployments = ['total' => 2, 'deployments' => [self::deployment('first'), self::deployment('second')]];

        return [
            'files' => [
                [Resource::TYPE_FILE],
                [new Bucket('bucket', 'Bucket')],
                ['/storage/buckets/bucket/files' => ['total' => 2, 'files' => [self::file('first'), self::file('second')]]],
                ['/storage/buckets/bucket/files', 'GET /storage/buckets/bucket/files/first/download'],
            ],
            'function deployments' => [
                [Resource::TYPE_DEPLOYMENT],
                [new Func('function', 'Function', 'node-22')],
                ['/functions/function/deployments' => $deployments],
                [
                    '/functions/function/deployments',
                    'HEAD /functions/function/deployments/first/download',
                    'GET /functions/function/deployments/first/download',
                ],
            ],
            'active function deployments' => [
                [Resource::TYPE_ENVIRONMENT_VARIABLE],
                [
                    new Func('first', 'First', 'node-22', activeDeployment: 'active'),
                    new Func('second', 'Second', 'node-22', activeDeployment: 'active'),
                ],
                [
                    '/functions/first/deployments/active' => self::deployment('active'),
                    '/functions/second/deployments/active' => self::deployment('active'),
                ],
                [
                    '/functions/first/deployments/active',
                    'HEAD /functions/first/deployments/active/download',
                    'GET /functions/first/deployments/active/download',
                ],
            ],
            'site deployments' => [
                [Resource::TYPE_SITE_DEPLOYMENT],
                [new Site('site', 'Site', 'nextjs', 'node-22')],
                ['/sites/site/deployments' => $deployments],
                [
                    '/sites/site/deployments',
                    'HEAD /sites/site/deployments/first/download',
                    'GET /sites/site/deployments/first/download',
                ],
            ],
            'active site deployments' => [
                [Resource::TYPE_SITE_VARIABLE],
                [
                    new Site('first', 'First', 'nextjs', 'node-22', activeDeployment: 'active'),
                    new Site('second', 'Second', 'nextjs', 'node-22', activeDeployment: 'active'),
                ],
                [
                    '/sites/first/deployments/active' => self::deployment('active'),
                    '/sites/second/deployments/active' => self::deployment('active'),
                ],
                [
                    '/sites/first/deployments/active',
                    'HEAD /sites/first/deployments/active/download',
                    'GET /sites/first/deployments/active/download',
                ],
            ],
        ];
    }

    /**
     * @param array<Resource> $cached
     */
    #[DataProvider('typeProvider')]
    public function testAbortDuringAnExportStopsTheTransfer(string $type, array $cached): void
    {
        $abort = $this->superseded();
        $log = new ArrayObject();
        $source = $this->source($log, [], $abort);
        $transfer = new Transfer($source, new MockDestination());
        $later = \in_array($type, Transfer::GROUP_INTEGRATIONS_RESOURCES, true) ? Resource::TYPE_RULE : Resource::TYPE_WEBHOOK;

        foreach ($cached as $resource) {
            $transfer->getCache()->add($resource);
        }

        $this->assertAborted($abort, static fn () => $transfer->run([$type, $later], static function (): void {
        }));

        $this->assertCount(1, $log, 'Nothing may be requested after the abort.');
        $this->assertSame([], $source->getErrors());
    }

    /**
     * @return array<string, array{string, array<Resource>}>
     */
    public static function typeProvider(): array
    {
        $database = new Database('database', 'Database');
        $table = new Table($database, 'Table', 'table');

        return [
            'users' => [Resource::TYPE_USER, []],
            'teams' => [Resource::TYPE_TEAM, []],
            'memberships' => [Resource::TYPE_MEMBERSHIP, [new Team('team', 'Team')]],
            'auth methods' => [Resource::TYPE_AUTH_METHODS, []],
            'OAuth2 providers' => [Resource::TYPE_OAUTH2_PROVIDER, []],
            'policies' => [Resource::TYPE_POLICIES, []],
            'databases' => [Resource::TYPE_DATABASE, []],
            'tables' => [Resource::TYPE_TABLE, [$database]],
            'columns' => [Resource::TYPE_COLUMN, [$table]],
            'indexes' => [Resource::TYPE_INDEX, [$table]],
            'rows' => [Resource::TYPE_ROW, [$table]],
            'buckets' => [Resource::TYPE_BUCKET, []],
            'files' => [Resource::TYPE_FILE, [new Bucket('bucket', 'Bucket')]],
            'functions' => [Resource::TYPE_FUNCTION, []],
            'deployments' => [Resource::TYPE_DEPLOYMENT, [new Func('function', 'Function', 'node-22')]],
            'sites' => [Resource::TYPE_SITE, []],
            'site deployments' => [Resource::TYPE_SITE_DEPLOYMENT, [new Site('site', 'Site', 'nextjs', 'node-22')]],
            'project variables' => [Resource::TYPE_PROJECT_VARIABLE, []],
            'protocols' => [Resource::TYPE_PROJECT_PROTOCOLS, []],
            'labels' => [Resource::TYPE_PROJECT_LABELS, []],
            'services' => [Resource::TYPE_PROJECT_SERVICES, []],
            'email templates' => [Resource::TYPE_PROJECT_EMAIL_TEMPLATE, []],
            'rules' => [Resource::TYPE_RULE, []],
            'providers' => [Resource::TYPE_PROVIDER, []],
            'topics' => [Resource::TYPE_TOPIC, []],
            'subscribers' => [Resource::TYPE_SUBSCRIBER, [new Topic('topic', 'Topic')]],
            'messages' => [Resource::TYPE_MESSAGE, []],
            'platforms' => [Resource::TYPE_PLATFORM, []],
            'API keys' => [Resource::TYPE_API_KEY, []],
            'webhooks' => [Resource::TYPE_WEBHOOK, []],
            'SMTP' => [Resource::TYPE_SMTP, []],
        ];
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
     * A real Appwrite source whose SDK requests and downloads are answered from the script
     * and recorded in the log, in order.
     *
     * @param ArrayObject<int, string> $log
     * @param array<string, array<string, mixed>> $responses
     */
    private function source(ArrayObject $log, array $responses, ?Throwable $unscripted = null): Appwrite
    {
        $source = new class ($log) extends Appwrite {
            /**
             * @param ArrayObject<int, string> $log
             */
            public function __construct(private readonly ArrayObject $log)
            {
                parent::__construct('project', 'http://localhost', 'key', static fn (): null => null);
            }

            #[Override]
            protected function call(string $method, string $path = '', array $headers = [], array $params = [], array &$responseHeaders = []): array|string
            {
                $this->log->append($method . ' ' . $path);

                return 'data';
            }
        };

        $client = new class ($log, $responses, $unscripted ?? new LogicException('Unscripted request')) extends Client {
            /**
             * @param ArrayObject<int, string> $log
             * @param array<string, array<string, mixed>> $responses
             */
            public function __construct(
                private readonly ArrayObject $log,
                private readonly array $responses,
                private readonly Throwable $unscripted,
            ) {
                parent::__construct();
            }

            #[Override]
            public function call(string $method, string $path = '', array $headers = [], array $params = [], ?string $responseType = null): array
            {
                $this->log->append($path);

                return $this->responses[$path] ?? throw $this->unscripted;
            }
        };

        $class = new ReflectionClass(Appwrite::class);

        foreach ($class->getProperties() as $property) {
            $type = $property->getType();

            if ($type instanceof ReflectionNamedType && \is_subclass_of($type->getName(), Service::class)) {
                $service = $type->getName();
                $property->setValue($source, new $service($client));
            }
        }

        $class->getProperty('client')->setValue($source, $client);
        $class->getProperty('reader')->setValue($source, new API(new TablesDB($client)));

        return $source;
    }

    /**
     * @return array<string, mixed>
     */
    private static function team(string $id): array
    {
        return [
            '$id' => $id,
            '$createdAt' => self::TIMESTAMP,
            '$updatedAt' => self::TIMESTAMP,
            'name' => $id,
            'total' => 0,
            'prefs' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function bucket(string $id): array
    {
        return [
            '$id' => $id,
            '$createdAt' => self::TIMESTAMP,
            '$updatedAt' => self::TIMESTAMP,
            '$permissions' => [],
            'fileSecurity' => false,
            'name' => $id,
            'enabled' => true,
            'maximumFileSize' => 1024,
            'allowedFileExtensions' => [],
            'compression' => 'none',
            'encryption' => false,
            'antivirus' => false,
            'transformations' => false,
            'totalSize' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function topic(string $id): array
    {
        return [
            '$id' => $id,
            '$createdAt' => self::TIMESTAMP,
            '$updatedAt' => self::TIMESTAMP,
            'name' => $id,
            'emailTotal' => 0,
            'smsTotal' => 0,
            'pushTotal' => 0,
            'subscribe' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function file(string $id): array
    {
        return [
            '$id' => $id,
            'bucketId' => 'bucket',
            '$createdAt' => self::TIMESTAMP,
            '$updatedAt' => self::TIMESTAMP,
            '$permissions' => [],
            'name' => $id,
            'signature' => '',
            'mimeType' => 'text/plain',
            'sizeOriginal' => 4,
            'sizeActual' => 4,
            'chunksTotal' => 1,
            'chunksUploaded' => 1,
            'encryption' => false,
            'compression' => 'none',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function deployment(string $id): array
    {
        return [
            '$id' => $id,
            '$createdAt' => self::TIMESTAMP,
            '$updatedAt' => self::TIMESTAMP,
            'type' => 'manual',
            'resourceId' => '',
            'resourceType' => '',
            'entrypoint' => 'index.js',
            'sourceSize' => 4,
            'buildSize' => 0,
            'totalSize' => 4,
            'buildId' => '',
            'activate' => true,
            'screenshotLight' => '',
            'screenshotDark' => '',
            'status' => 'ready',
            'buildLogs' => '',
            'buildDuration' => 0,
            'providerRepositoryName' => '',
            'providerRepositoryOwner' => '',
            'providerRepositoryUrl' => '',
            'providerCommitHash' => '',
            'providerCommitAuthorUrl' => '',
            'providerCommitAuthor' => '',
            'providerCommitMessage' => '',
            'providerCommitUrl' => '',
            'providerBranch' => '',
            'providerBranchUrl' => '',
        ];
    }
}
