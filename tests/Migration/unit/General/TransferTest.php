<?php

namespace Tests\Unit\General;

use PHPUnit\Framework\TestCase;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Database\Database;
use Utopia\Migration\Transfer;
use Utopia\Tests\Adapters\MockDestination;
use Utopia\Tests\Adapters\MockSource;

class TransferTest extends TestCase
{
    protected Transfer $transfer;
    protected MockSource $source;
    protected MockDestination $destination;

    public function setup(): void
    {
        $this->source = new MockSource();
        $this->destination = new MockDestination();

        $this->transfer = new Transfer(
            $this->source,
            $this->destination
        );
    }

    public function testRootResourceId(): void
    {
        /**
         * TEST FOR FAILURE
         * Make sure we can't create a transfer with multiple root resources when supplying a rootResourceId
         */
        try {
            $this->transfer->run([Resource::TYPE_USER, Resource::TYPE_DATABASE], function () {}, 'rootResourceId');
            $this->fail('Multiple Root resources should not be allowed');
        } catch (\Exception $e) {
            $this->assertEquals('Multiple root resources found. Only one root resource can be transferred at a time if using $rootResourceId.', $e->getMessage());
        }

        $this->source->pushMockResource(new Database('test', 'test'));
        $this->source->pushMockResource(new Database('test2', 'test'));

        /**
         * TEST FOR SUCCESS
         */
        $this->transfer->run([Resource::TYPE_DATABASE], function () {}, 'test');
        $this->assertEquals(1, count($this->destination->getResourceTypeData(Transfer::GROUP_DATABASES, Resource::TYPE_DATABASE)));

        $database = $this->destination->getResourceById(Transfer::GROUP_DATABASES, Resource::TYPE_DATABASE, 'test');
        /** @var Database $database */
        $this->assertNotNull($database);
        $this->assertEquals('test', $database->getDBName());
        $this->assertEquals('test', $database->getId());
    }
}
