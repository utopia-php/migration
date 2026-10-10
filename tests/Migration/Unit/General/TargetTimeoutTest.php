<?php

namespace Utopia\Tests\Unit\General;

use Override;
use PHPUnit\Framework\TestCase;
use Utopia\Migration\Sources\Appwrite;
use Utopia\Migration\Target;

/**
 * A remote that accepts the connection and never answers must fail the request,
 * not hold the migration open forever.
 */
final class TargetTimeoutTest extends TestCase
{
    /** @var resource|null */
    private $server = null;

    private string $endpoint = '';

    #[Override]
    protected function setUp(): void
    {
        // The kernel completes the handshake from the listen backlog, so requests
        // are sent and then wait on a server that never reads or replies.
        $server = \stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        $this->assertNotFalse($server, $errorMessage);
        $this->server = $server;
        $this->endpoint = 'http://' . \stream_socket_get_name($server, false) . '/v1';
    }

    #[Override]
    protected function tearDown(): void
    {
        if (\is_resource($this->server)) {
            \fclose($this->server);
        }
    }

    public function testCallAbandonsAStalledResponse(): void
    {
        $target = new class ($this->endpoint) extends Target {
            public const int STALL_TIMEOUT = 1;

            public function __construct(string $endpoint)
            {
                $this->endpoint = $endpoint;
            }

            #[Override]
            public static function getName(): string
            {
                return 'Stalled';
            }

            #[Override]
            public static function getSupportedResources(): array
            {
                return [];
            }

            #[Override]
            public function run(array $resources, callable $callback, string $rootResourceId = ''): void
            {
            }

            #[Override]
            public function report(array $resources = [], array $resourceIds = []): array
            {
                return $this->call('GET', '/health/version');
            }
        };

        $started = \microtime(true);

        try {
            $target->report();
            $this->fail('A request to a remote that never answers returned.');
        } catch (\Exception $error) {
            $this->assertSame(\Utopia\Migration\Exception::CODE_INTERNAL, $error->getCode());
        }

        $this->assertLessThan(10, \microtime(true) - $started);
    }

    public function testSourceReportFailsWhenTheApiStalls(): void
    {
        $source = new class ($this->endpoint) extends Appwrite {
            public const int REQUEST_TIMEOUT = 1;

            public function __construct(string $endpoint)
            {
                parent::__construct('project', $endpoint, 'key', fn () => null);
            }
        };

        $started = \microtime(true);

        try {
            $source->report(['user']);
            $this->fail('A report against a source that never answers returned.');
        } catch (\Exception $error) {
            $this->assertStringContainsString('timed out', $error->getMessage());
        }

        $this->assertLessThan(10, \microtime(true) - $started);
    }
}
