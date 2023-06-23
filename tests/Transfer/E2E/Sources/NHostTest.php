<?php

namespace Utopia\Tests\E2E\Sources;

use Utopia\Transfer\Sources\NHost;
use Utopia\Transfer\Transfer;
use Utopia\Tests\E2E\Adapters\Mock;
use Utopia\Transfer\Destination;
use Utopia\Transfer\Source;

class NHostTest extends Base
{
    protected ?Source $source = null;
    protected ?Transfer $transfer = null;
    protected ?Destination $destination = null;

    protected function setUp(): void
    {
        $this->source = new NHost(
            'xxxxxxxxxxxx',
            'eu-central-1',
            'xxxxxxxxxxxxxxxxxx',
            'xxxxxxxxxxxxxxx',
            'xxxxxxxxx',
            'xxxxxxxxxxxxxxxx'
        );
        $this->source->pdo = new \PDO('pgsql:host=nhost-db' . ';port=5432;dbname=postgres', 'postgres', 'postgres');
        $this->source->storageURL = 'http://nhost-storage:3000';

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
