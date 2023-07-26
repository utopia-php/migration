<?php

namespace Utopia\Tests\E2E\Sources;

use Utopia\Tests\E2E\Adapters\Mock;
use Utopia\Transfer\Destination;
use Utopia\Transfer\Resource;
use Utopia\Transfer\Source;
use Utopia\Transfer\Sources\NHost;
use Utopia\Transfer\Sources\Supabase;
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

        $this->source = new Supabase(
            'http://supabase-api',
            'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
            'supabase-db',
            'postgres',
            'postgres',
            'postgres'
        );

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
        $this->transfer->run($this->source->getSupportedResources(),
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
        $this->assertGreaterThan(0, count($users));
        $foundUser = null;

        foreach ($users as $user) {
            /** @var \Utopia\Transfer\Resources\Auth\User $user */
            if ($user->getEmail() == 'albert.kihn95@yahoo.com') {
                $foundUser = $user;

                break;
            }
        }

        if (!$foundUser) {
            $this->fail('User "albert.kihn95@yahoo.com" not found');

            return;
        }

        $this->assertEquals('success', $foundUser->getStatus());
        $this->assertEquals('$2a$10$NGZAAOfXeheUoH9V3dnRoeR.r3J5ynnSZ6KjvHxOUlV8XUrulJzQa', $foundUser->getPasswordHash()->getHash());
        $this->assertEquals('bcrypt', $foundUser->getPasswordHash()->getAlgorithm());
        $this->assertEquals(['email'], $foundUser->getTypes());
    }

    /**
     * @depends testValidateTransfer
     */
    public function testValidateDatabaseTransfer($state): void
    {
        // Find known database
        $databases = $state['source']->cache->get(Resource::TYPE_DATABASE);
        $this->assertGreaterThan(0, count($databases));
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

        // Find Known Collections
        $collections = $state['source']->cache->get(Resource::TYPE_COLLECTION);
        $this->assertGreaterThan(0, count($collections));

        $foundCollection = null;
        
        foreach ($collections as $collection) {
            /** @var \Utopia\Transfer\Resources\Database\Collection $collection */
            if ($collection->getDatabase()->getDBName() === 'public' && $collection->getCollectionName() === 'test') {
                $foundCollection = $collection;

                break;
            }
        }

        if (!$foundCollection) {
            $this->fail('Collection "test" not found');

            return;
        }

        $this->assertEquals('success', $foundCollection->getStatus());
        $this->assertEquals('test', $foundCollection->getCollectionName());
        $this->assertEquals('public', $foundCollection->getDatabase()->getDBName());
        $this->assertEquals('public', $foundCollection->getDatabase()->getId());

        // Find Known Documents
        $documents = $state['source']->cache->get(Resource::TYPE_DOCUMENT);
        $this->assertGreaterThan(0, count($documents));

        $foundDocument = null;

        foreach ($documents as $document) {
            /** @var \Utopia\Transfer\Resources\Database\Document $document */
            if ($document->getCollection()->getDatabase()->getDBName() === 'public' && $document->getCollection()->getCollectionName() === 'test') {
                $foundDocument = $document;
            }

            break;
        }

        if (!$foundDocument) {
            $this->fail('Document "1" not found');

            return;
        }

        $this->assertEquals('success', $foundDocument->getStatus());
    }

    /**
     * @depends testValidateTransfer
     */
    public function testValidateStorageTransfer($state): void
    {
        // Find known bucket
        $buckets = $state['source']->cache->get(Resource::TYPE_BUCKET);
        $this->assertGreaterThan(0, count($buckets));

        $foundBucket = null;

        foreach ($buckets as $bucket) {
            /** @var \Utopia\Transfer\Resources\Storage\Bucket $bucket */
            if ($bucket->getBucketName() === 'Test Bucket 1') {
                $foundBucket = $bucket;
            }

            break;
        }

        if (!$foundBucket) {
            $this->fail('Bucket "Test Bucket 1" not found');

            return;
        }

        $this->assertEquals('success', $foundBucket->getStatus());

        // Find known file
        $files = $state['source']->cache->get(Resource::TYPE_FILE);
        $this->assertGreaterThan(0, count($files));

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
        $this->assertEquals('image/png', $foundFile->getMimeType());
        $this->assertEquals(679233, $foundFile->getSize());
        $this->assertEquals('', $foundFile->getData()); // Memory Leak Check
    }
}
