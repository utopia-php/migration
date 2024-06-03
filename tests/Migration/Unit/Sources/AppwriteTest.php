<?php

namespace Utopia\Tests\Unit\Sources;

use Appwrite\Client;
use Appwrite\Query;
use Appwrite\Services\Teams;
use Appwrite\Services\Users;
use Utopia\CLI\Console;
use Utopia\Migration\Resource;
use Utopia\Migration\Sources\Appwrite;
use Utopia\Migration\Transfer;
use Utopia\Tests\Unit\Adapters\MockDestination;

class AppwriteTest extends Base
{
    private Client $client;

    public static function setUpBeforeClass(): void
    {
        if (file_exists('projects.json')) {
            Console::info('Appwrite already bootstrapped, skipping');

            return;
        }

        // Check Appwrite is online and ready
        $tries = 5;

        while ($tries > 0) {
            if ($tries === 0) {
                throw new \Exception('Appwrite was offline after 5 tries');
            }

            // Static doesn't have access to $this->call
            $ch = curl_init('http://appwrite/v1/health');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($status === 200) {
                break;
            }

            sleep(5);
            $tries--;
        }

        Console::info('Bootstrapping Appwrite...');

        $stdout = '';
        Console::execute(
            'appwrite-toolkit --endpoint http://appwrite/v1 --auto bootstrap --amount 1',
            '',
            $stdout,
        );
        Console::info($stdout);

        Console::info('Running Faker...');

        $stdout = '';
        Console::execute(
            'appwrite-toolkit --endpoint http://appwrite/v1 --auto faker',
            '',
            $stdout
        );
        Console::info($stdout);

        Console::info('Initial setup complete');
    }

    public function setup(): void
    {
        // Parse Faker JSON
        $projects = json_decode(file_get_contents('projects.json'), true);
        $project = $projects[0];

        $this->source = new Appwrite(
            $project['$id'],
            'http://appwrite/v1',
            $project['apiKey']
        );

        $this->client = new Client();
        $this->client
            ->setEndpoint('http://appwrite/v1')
            ->setProject($project['$id'])
            ->setKey($project['apiKey']);

        $this->destination = new MockDestination();
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
        $transfer = new Transfer($this->source, $this->destination);

        $transfer->run(
            $this->source->getSupportedResources(),
            function () {
            }
        );

        $this->assertEquals(0, count($transfer->getReport('error')));

        return array_merge($state, [
            'transfer' => $transfer,
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
    public function testValidateAuthTransfer($state): void
    {
        $userClient = new Users($this->client);
        $teamClient = new Teams($this->client);

        /** @var Transfer $transfer */
        $transfer = $state['transfer'];

        /** @var MockDestination $destination */
        $destination = $transfer->getDestination();

        // Check Users
        $last = '';
        while (true) {
            $response = $userClient->list(
                empty($last) ? [] : [Query::cursorAfter($last)]
            );

            foreach ($response['users'] as $user) {
                // Check if exists
                $destinationUser = $destination->get('user', $user['$id']);

                if (empty($destinationUser)) {
                    $this->fail('User ' . $user['$id'] . ' not found in destination');
                }

                // Compare data
                $this->assertEquals($user['$id'], $destinationUser['id']);
                $this->assertEquals($user['email'], $destinationUser['email']);
                $this->assertEquals($user['name'], $destinationUser['username']);
                $this->assertEquals($user['password'], $destinationUser['passwordHash']);
                $this->assertEquals($user['phone'], $destinationUser['phone']);
                $this->assertEquals($user['emailVerification'], $destinationUser['emailVerified']);
                $this->assertEquals($user['phoneVerification'], $destinationUser['phoneVerified']);

                $last = $user['$id'];
            }

            if (empty($response['sum'])) {
                break;
            }
        }

        // Check Teams
        $last = '';
        while (true) {
            $response = $teamClient->list(
                empty($last) ? [] : [Query::cursorAfter($last)]
            );

            foreach ($response['teams'] as $team) {
                // Check if exists
                $destinationTeam = $destination->get('team', $team['$id']);

                if (empty($destinationTeam)) {
                    $this->fail('Team ' . $team['$id'] . ' not found in destination');
                }

                // Compare data
                $this->assertEquals($team['$id'], $destinationTeam['id']);
                $this->assertEquals($team['name'], $destinationTeam['name']);
                $this->assertEquals($team['prefs'], $destinationTeam['preferences']);

                $last = $team['$id'];
            }

            if (empty($response['sum'])) {
                break;
            }
        }
    }

    /**
     * @depends testValidateAuthTransfer
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
     * @depends testValidateDatabaseTransfer
     */
    public function testValidateStorageTransfer($state): void
    {
        // Check each resource and make sure it's 1:1

        // Validate Buckets

        // Validate Files
    }

    /**
     * Compare data between original and copy ignoring any fields that are not relevant
     *
     *
     * @return bool
     */
    private function compareData(array $original, array $copy, array $ignore)
    {
    }
}
