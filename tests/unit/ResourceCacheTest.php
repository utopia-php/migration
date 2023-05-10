<?php

use PHPUnit\Framework\TestCase;
use Utopia\Transfer\Resource;
use Utopia\Transfer\ResourceCache;
use Utopia\Transfer\Transfer;

class ConcreteResource extends Resource
{
    static function getName(): string
    {
        return 'TestResource';
    }

    public function getGroup(): string
    {
        return Transfer::GROUP_GENERAL;
    }

    public function asArray(): array
    {
        return [];
    }
}

class ResourceCacheTest extends TestCase
{
    public function testAdd()
    {
        $cache = new ResourceCache();

        $resource = new ConcreteResource();
        $cache->add($resource);

        $this->assertArrayHasKey($resource->getName(), $cache->getAll());
        $this->assertArrayHasKey($resource->getInternalId(), $cache->getAll()[$resource->getName()]);
        $this->assertEquals($resource, $cache->getAll()[$resource->getName()][$resource->getInternalId()]);

        return $cache;
    }

    /**
     * @depends testAdd
     */
    public function testRemove(ResourceCache $cache)
    {
        $resources = $cache->get(ConcreteResource::getName());
        $resource = $resources[array_keys($resources)[0]];

        $cache->remove($resource);

        $this->assertArrayNotHasKey($resource->getInternalId(), $cache->getAll()[$resource->getName()]);

        return $cache;
    }

    /**
     * @depends testRemove
     */
    public function testWipe(ResourceCache $cache)
    {
        $resource = new ConcreteResource();
        $cache->add($resource);

        $resource2 = new ConcreteResource();
        $cache->add($resource2);

        $cache->wipe();

        $this->assertEmpty($cache->getAll());

        return $cache;
    }

    /**
     * @depends testWipe
     */
    public function testStatusCountersAdd(ResourceCache $cache)
    {
        $resource1 = new ConcreteResource();
        $resource2 = new ConcreteResource();
        $resource3 = new ConcreteResource();

        $resource1->setStatus(Resource::STATUS_SUCCESS);
        $resource2->setStatus(Resource::STATUS_ERROR);
        $resource3->setStatus(Resource::STATUS_SKIPPED);

        $cache->add($resource1);
        $cache->add($resource2);
        $cache->add($resource3);

        $this->assertEquals(1, $cache->getStatusCounters()[Resource::STATUS_SUCCESS]);
        $this->assertEquals(1, $cache->getStatusCounters()[Resource::STATUS_ERROR]);
        $this->assertEquals(1, $cache->getStatusCounters()[Resource::STATUS_SKIPPED]);

        return $cache;
    }

    /**
     * @depends testStatusCountersAdd
     */
    public function testStatusCountersUpdate(ResourceCache $cache)
    {
        $resources = $cache->get(ConcreteResource::getName());
        $resource = $resources[array_keys($resources)[0]];

        $resource->setStatus(Resource::STATUS_ERROR);
        $cache->update($resource);

        $resourceStatus = $cache->getStatusCounters();

        $this->assertEquals(2, $resourceStatus[Resource::STATUS_ERROR]);
        $this->assertEquals(0, $resourceStatus[Resource::STATUS_SUCCESS]);
        $this->assertEquals(1, $resourceStatus[Resource::STATUS_SKIPPED]);

        return $cache;
    }

    /**
     * @depends testStatusCountersUpdate
     */
    public function testStatusCountersRemove(ResourceCache $cache)
    {
        $resources = $cache->get(ConcreteResource::getName());
        $resource = $resources[array_keys($resources)[0]];

        $cache->remove($resource);

        $statusCounters = $cache->getStatusCounters();

        $this->assertEquals(1, $statusCounters[Resource::STATUS_ERROR]);
        $this->assertEquals(0, $statusCounters[Resource::STATUS_SUCCESS]);
        $this->assertEquals(1, $statusCounters[Resource::STATUS_SKIPPED]);
    }
}
