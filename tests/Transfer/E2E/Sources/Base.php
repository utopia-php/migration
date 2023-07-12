<?php

namespace Utopia\Tests\E2E\Sources;

use PHPUnit\Framework\TestCase;
use Utopia\Tests\E2E\Adapters\Mock;
use Utopia\Tests\E2E\Adapters\MockDestination;
use Utopia\Transfer\Destination;
use Utopia\Transfer\Resource;
use Utopia\Transfer\Source;
use Utopia\Transfer\Transfer;

abstract class Base extends TestCase
{
    protected ?Transfer $transfer = null;
    protected ?Source $source = null;
    protected ?Destination $destination = null;

    protected function setUp(): void
    {
        if (!$this->source)
            throw new \Exception('Source not set');

        $this->destination = new Mock();
        $this->transfer = new Transfer($this->source, $this->destination);
    }

    public function testGetName(): void
    {
        $this->assertNotEmpty($this->source::getName());
    }

    public function testGetSupportedResources(): void
    {
        $this->assertNotEmpty($this->source->getSupportedResources());

        foreach ($this->source->getSupportedResources() as $resource) {
            $this->assertContains($resource, Resource::ALL_RESOURCES);
        }
    }

    public function testCache(): void
    {
        $cache = $this->createMock(\Utopia\Transfer\Cache::class);
        $this->source->registerCache($cache);

        $this->assertNotNull($this->source->cache);
    }
}
