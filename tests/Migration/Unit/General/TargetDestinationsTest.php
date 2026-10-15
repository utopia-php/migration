<?php

namespace Utopia\Tests\Unit\General;

use Appwrite\AppwriteException;
use Override;
use PHPUnit\Framework\TestCase;
use Utopia\Client\Destinations\IPRange;
use Utopia\Client\Destinations\PublicInternet;
use Utopia\Migration\Exception;
use Utopia\Migration\Sources\Appwrite;
use Utopia\Migration\Sources\Appwrite\Client;

final class TargetDestinationsTest extends TestCase
{
    private const string REFUSED = 'not an allowed destination';

    /** @var resource|null */
    private $server = null;

    #[Override]
    protected function tearDown(): void
    {
        if (\is_resource($this->server)) {
            \proc_terminate($this->server, 9);
            \proc_close($this->server);
        }

        $this->server = null;
    }

    public function testTargetConnectsAnywhereByDefault(): void
    {
        $this->assertSame(['reached' => true], $this->target()->fetch($this->serve()));
    }

    public function testTargetRefusesAnAddressItsDestinationsDoNotAllow(): void
    {
        $target = $this->target()->setDestinations(new PublicInternet());

        try {
            $target->fetch($this->serve());
            $this->fail('A loopback address must be refused under PublicInternet.');
        } catch (\Exception $error) {
            $this->assertSame(Exception::CODE_INTERNAL, $error->getCode());
            $this->assertStringContainsString(self::REFUSED, $error->getMessage());
        }
    }

    public function testTargetReachesARangeItsDestinationsAdmit(): void
    {
        $target = $this->target()->setDestinations(new PublicInternet(new IPRange('127.0.0.0/8')));

        $this->assertSame(['reached' => true], $target->fetch($this->serve()));
    }

    public function testTargetAppliesDestinationsSetAfterARequest(): void
    {
        $url = $this->serve();
        $target = $this->target();
        $target->fetch($url);

        $target->setDestinations(new PublicInternet());

        $this->expectExceptionMessage(self::REFUSED);
        $target->fetch($url);
    }

    public function testSdkClientRefusesAnAddressItsDestinationsDoNotAllow(): void
    {
        $client = (new Client())->setDestinations(new PublicInternet());
        $client->setEndpoint($this->serve());

        $this->expectException(AppwriteException::class);
        $this->expectExceptionMessage(self::REFUSED);
        $client->call(Client::METHOD_GET, '/health');
    }

    public function testSdkClientReachesARangeItsDestinationsAdmit(): void
    {
        $client = (new Client())->setDestinations(new PublicInternet(new IPRange('127.0.0.0/8')));
        $client->setEndpoint($this->serve());

        $this->assertSame(['reached' => true], $client->call(Client::METHOD_GET, '/health'));
    }

    public function testAppwriteSourcePassesItsDestinationsToTheSdkClient(): void
    {
        $source = new class ('project', $this->serve(), 'key', fn () => null) extends Appwrite {
            public function sdk(): Client
            {
                return $this->client;
            }
        };

        $this->assertSame(['reached' => true], $source->sdk()->call(Client::METHOD_GET, '/health'));

        $source->setDestinations(new PublicInternet());

        $this->expectExceptionMessage(self::REFUSED);
        $source->sdk()->call(Client::METHOD_GET, '/health');
    }

    private function target(): RequestTarget
    {
        return new RequestTarget();
    }

    private function serve(): string
    {
        $script = \dirname(__DIR__, 2).'/resources/destinations/server.php';
        $server = \proc_open([PHP_BINARY, $script], [1 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($server);
        $this->server = $server;

        $port = \trim((string) \fgets($pipes[1]));
        $this->assertMatchesRegularExpression('/^\d+$/', $port, 'The test server did not report its port.');

        return "http://127.0.0.1:{$port}";
    }
}
