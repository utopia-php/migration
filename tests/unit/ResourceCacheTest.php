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
}
