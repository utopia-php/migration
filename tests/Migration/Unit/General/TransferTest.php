<?php

namespace Utopia\Tests\Unit\General;

use PHPUnit\Framework\TestCase;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Database\Database;
use Utopia\Migration\Transfer;
use Utopia\Tests\Unit\Adapters\MockDestination;
use Utopia\Tests\Unit\Adapters\MockSource;

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

    /**
     * @throws \Exception
     */
    public function testRootResourceId(): void
    {
        /**
         * TEST FOR FAILURE
         * Make sure we can't create a transfer with multiple root resources when supplying a rootResourceId
         */
        try {
            $this->transfer->run([Resource::TYPE_USER, Resource::TYPE_DATABASE], function () {}, 'rootResourceId');
            $this->fail('Multiple root resources should not be allowed');
        } catch (\Exception $e) {
            $this->assertSame('Resource type must be set when resource ID is set.', $e->getMessage());
        }

        $this->source->pushMockResource(new Database('test', 'test'));
        $this->source->pushMockResource(new Database('test2', 'test'));

        /**
         * TEST FOR SUCCESS
         */
        $this->transfer->run(
            [Resource::TYPE_DATABASE],
            function () {},
            'test',
            Resource::TYPE_DATABASE
        );
        $this->assertCount(1, $this->destination->getResourceTypeData(Transfer::GROUP_DATABASES, Resource::TYPE_DATABASE));

        $database = $this->destination->getResourceById(Transfer::GROUP_DATABASES, Resource::TYPE_DATABASE, 'test');
        /** @var Database $database */
        $this->assertNotNull($database);
        $this->assertSame('test', $database->getDatabaseName());
        $this->assertSame('test', $database->getId());
    }
}
