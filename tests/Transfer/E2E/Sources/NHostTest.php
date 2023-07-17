<?php

namespace Utopia\Tests\E2E\Sources;

use Utopia\Tests\E2E\Adapters\Mock;
use Utopia\Transfer\Destination;
use Utopia\Transfer\Source;
use Utopia\Transfer\Sources\NHost;
use Utopia\Transfer\Transfer;

class NHostTest extends Base
{
    protected ?Source $source = null;

    protected ?Transfer $transfer = null;

    protected ?Destination $destination = null;

    protected function setUp(): void
    {
        // Check DB is online and ready
        $pdo = null;
        $tries = 5;

        while ($tries > 0) {
            try {
                $pdo = new \PDO('pgsql:host=nhost-db'.';port=5432;dbname=postgres', 'postgres', 'postgres');
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

                if ($pdo && $pdo->query('SELECT 1')->fetchColumn() === 1) {
                    break;
                } else {
                    var_dump('DB was offline, waiting 1s then retrying.');
                }
            } catch (\PDOException $e) {
            }

            usleep(1000000);
            $tries--;
        }

        if (! $pdo || $tries === 0) {
            throw new \Exception('DB was offline after 5 tries');
        }

        $this->source = new NHost(
            'xxxxxxxxxxxx',
            'eu-central-1',
            'hasuraSecret',
            'postgres',
            'postgres',
            'password'
        );
        $this->source->pdo = new \PDO('pgsql:host=nhost-db'.';port=5432;dbname=postgres', 'postgres', 'postgres');
        $this->source->storageURL = 'http://nhost-storage';

        $this->destination = new Mock();
        $this->transfer = new Transfer($this->source, $this->destination);
    }

    public function testSourceReport(): void
    {
        // Test report all
        $report = $this->source->report();

        $this->assertNotEmpty($report);
    }

    public function testRunTransfer(): void
    {
        $this->transfer->run(
            $this->source->getSupportedResources(),
            function ($data) {
                $this->assertNotEmpty($data);
            }
        );
    }
}
