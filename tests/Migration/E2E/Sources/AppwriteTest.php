<?php

namespace Utopia\Tests\E2E\Sources;

use Utopia\CLI\Console;
use Utopia\Migration\Resource;
use Utopia\Migration\Sources\Appwrite;
use Utopia\Migration\Transfer;
use Utopia\Tests\E2E\Adapters\MockDestination;

class AppwriteTest extends Base
{
    protected function setUp(): void
    {
        // Check Appwrite is online and ready
        $tries = 5;

        while ($tries > 0) {
            try {
                $this->call('GET', 'http://localhost:8000/v1');
                break;
            } catch (\Exception $e) {
            }

            sleep(5);
            $tries--;
        }

        // Bootstrap Appwrite
        Console::execute(
            "appwrite-toolkit --endpoint http://appwrite/v1 --auto bootstrap --amount 1",
            '',
            ''
        );

        // Run Faker
        Console::execute(
            "appwrite-toolkit --endpoint http://appwrite/v1 --auto faker",
            '',
            ''
        );

        // Parse Faker JSON
        $projects = json_decode(file_get_contents('projects.json'), true);
        $project = $projects[0];

        // Create Appwrite Source
        $this->source = new Appwrite($project['$id'], 'http://localhost:8000', $project['key']);

        $this->destination = new MockDestination();
        $this->transfer = new Transfer($this->source, $this->destination);
    }

    public function testSourceReport()
    {
        // Test report all
        $report = $this->source->report();

        $this->assertNotEmpty($report);

        return [
            'report' => $report,
        ];
    }

    /**
     * @depends testSourceReport
     */
    public function testRunTransfer($state)
    {
        $this->transfer->run(
            $this->source->getSupportedResources(),
            function () {
            }
        );

        $this->assertEquals(0, count($this->transfer->getReport('error')));

        return array_merge($state, [
            'transfer' => $this->transfer,
            'source' => $this->source,
        ]);
    }

    /**
     * @depends testRunTransfer
     */
    public function testValidateTransfer($state)
    {
        $statusCounters = $state['transfer']->getStatusCounters();
        $this->assertNotEmpty($statusCounters);

        foreach ($statusCounters as $resource => $counters) {
            $this->assertNotEmpty($counters);

            if ($counters[Resource::STATUS_ERROR] > 0) {
                $this->fail('Resource ' . $resource . ' has ' . $counters[Resource::STATUS_ERROR] . ' errors');

                return;
            }
        }

        return $state;
    }

    /**
     * @depends testValidateTransfer
     */
    public function testValidateUsersTransfer($state): void
    {
        // Process all users from Appwrite source and check if our copy is 1:1
    }

    /**
     * @depends testValidateTransfer
     */
    public function testValidateDatabaseTransfer($state): void
    {
        // Check each resource and make sure it's 1:1

        // Databases

        // Collections

        // Attributes

        // Indexes

        // Documents
    }

    /**
     * @depends testValidateTransfer
     */
    public function testValidateStorageTransfer($state): void
    {
        // Check each resource and make sure it's 1:1

        // Validate Buckets

        // Validate Files
    }
}
