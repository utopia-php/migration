<?php

namespace Utopia\Tests\E2E\Sources;

use Utopia\Transfer\Sources\NHost;
use Utopia\Transfer\Transfer;
use Utopia\Tests\E2E\Adapters\MockDestination;

class NHostTest extends SourceCore
{
    protected ?NHost $source = null;
    protected ?Transfer $transfer = null;
    protected ?MockDestination $destination = null;

    public function __construct()
    {
        $this->source = new NHost(
            'xxxxxxxxxxxx',
            'eu-central-1',
            'xxxxxxxxxxxxxxxxxx',
            'xxxxxxxxxxxxxxx',
            'xxxxxxxxx',
            'xxxxxxxxxxxxxxxx'
        );

        $this->destination = new MockDestination();
        $this->transfer = new Transfer($this->source, $this->destination);

        // $this->source->pdo = new \PDO('pgsql:host=nhost-db' . ';port=5432;dbname=postgres', 'postgres', 'postgres');
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
