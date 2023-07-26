<?php

namespace Utopia\Tests\E2E\Sources;

use Utopia\Tests\E2E\Adapters\Mock;
use Utopia\Transfer\Destination;
use Utopia\Transfer\Resource;
use Utopia\Transfer\Source;
use Utopia\Transfer\Sources\NHost;
use Utopia\Transfer\Transfer;

class SupabaseTest extends Base
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
                $pdo = new \PDO('pgsql:host=supabase-db'.';port=5432;dbname=postgres', 'postgres', 'postgres');
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

        if (!$pdo || $tries === 0) {
            throw new \Exception('DB was offline after 5 tries');
        }

        // Check Storage is online and ready
        $tries = 5;
        while ($tries > 0) {
            try {
                $this->call('GET', 'http://supabase-storage/', ['Content-Type' => 'text/plain']);

                break;
            } catch (\Exception $e) {}

            usleep(1000000);
            $tries--;
        }

        if ($tries === 0) {
            throw new \Exception('Storage was offline after 5 tries');
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
            function () {}
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
                $this->fail('Resource '.$resource.' has '.$counters[Resource::STATUS_ERROR].' errors');

                return;
            }
        }

        return $state;
    }

    /**
     * @depends testValidateTransfer
     */
    public function testValidateUserTransfer($state): void
    {
        // Find known user
        $users = $state['source']->cache->get(Resource::TYPE_USER);
        $foundUser = null;

        foreach ($users as $user) {
            /** @var \Utopia\Transfer\Resources\Auth\User $user */
            if ($user->getEmail() === 'test@test.com') {
                $foundUser = $user;
            }

            break;
        }

        if (!$foundUser) {
            $this->fail('User "test@test.com" not found');

            return;
        }

        $this->assertEquals('success', $foundUser->getStatus());
        $this->assertEquals('$2a$10$ARQ/f.K6OmCjZ8XF0U.6fezPMlxDqsmcl0Rs6xQVkvj62u7gcSzOW', $foundUser->getPasswordHash()->getHash());
        $this->assertEquals('bcrypt', $foundUser->getPasswordHash()->getAlgorithm());
        $this->assertEquals('test@test.com', $foundUser->getUsername());
        $this->assertEquals(['email'], $foundUser->getTypes());
    }

    /**
     * @depends testValidateTransfer
     */
    public function testValidateDatabaseTransfer($state): void
    {
        // Find known database
        $databases = $state['source']->cache->get(Resource::TYPE_DATABASE);
        $foundDatabase = null;

        foreach ($databases as $database) {
            /** @var \Utopia\Transfer\Resources\Database $database */
            if ($database->getDBName() === 'public') {
                $foundDatabase = $database;
            }

            break;
        }

        if (!$foundDatabase) {
            $this->fail('Database "public" not found');

            return;
        }

        $this->assertEquals('success', $foundDatabase->getStatus());
        $this->assertEquals('public', $foundDatabase->getDBName());
        $this->assertEquals('public', $foundDatabase->getId());
    }

    /**
     * @depends testValidateTransfer
     */
    public function testValidateStorageTransfer($state): void
    {
        // Find known bucket
        $buckets = $state['source']->cache->get(Resource::TYPE_BUCKET);
        $foundBucket = null;

        foreach ($buckets as $bucket) {
            /** @var \Utopia\Transfer\Resources\Bucket $bucket */
            if ($bucket->getId() === 'default') {
                $foundBucket = $bucket;
            }

            break;
        }

        if (!$foundBucket) {
            $this->fail('Bucket "default" not found');

            return;
        }

        $this->assertEquals('success', $foundBucket->getStatus());
        $this->assertEquals('default', $foundBucket->getId());

        // Find known file
        $files = $state['source']->cache->get(Resource::TYPE_FILE);
        $foundFile = null;

        foreach ($files as $file) {
            /** @var \Utopia\Transfer\Resources\File $file */
            if ($file->getFileName() === 'tulips.png') {
                $foundFile = $file;
            }

            break;
        }

        if (!$foundFile) {
            $this->fail('File "tulips.png" not found');

            return;
        }
        /** @var \Utopia\Transfer\Resources\Storage\File $foundFile */

        $this->assertEquals('success', $foundFile->getStatus());
        $this->assertEquals('tulips.png', $foundFile->getFileName());
        $this->assertEquals('default', $foundFile->getBucket()->getId());
        $this->assertEquals('image/png', $foundFile->getMimeType());
        $this->assertEquals(679233, $foundFile->getSize());
        $this->assertEquals('', $foundFile->getData()); // Memory Leak Check
    }
}
