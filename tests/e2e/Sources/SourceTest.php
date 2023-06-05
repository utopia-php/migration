<?php

namespace Tests\E2E\Sources;

use PHPUnit\Framework\TestCase;
use Utopia\Transfer\Resource;
use Utopia\Transfer\Source;

abstract class SourceTest extends TestCase
{
    protected ?Source $source = null;

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
        $this->source->registerCache($this->createMock(\Utopia\Transfer\Cache::class));

        $this->assertNotNull($this->source->cache);
    }

    abstract public function testReport(): void;

    public function validateReport(array $report)
    {
        foreach ($report as $resource => $amount) {
            $this->assertContains($resource, Resource::ALL_RESOURCES);
            $this->assertIsInt($amount);
        }
    }

    abstract public function testExportResources(): void;
}
