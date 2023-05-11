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
            $this->assertContains($resource,  Resource::ALL_RESOURCES);
        }
    }

    public function testTransferCache(): void
    {
        $this->source->registerTransferCache($this->createMock(\Utopia\Transfer\ResourceCache::class));

        $this->assertNotNull($this->source->resourceCache);
    }

    public abstract function testReport(): void;

    public function validateReport(array $report) {
        foreach ($report as $resource => $amount) {
            $this->assertContains($resource, Resource::ALL_RESOURCES);
            $this->assertIsInt($amount);
        }
    }

    public abstract function testExportResources(): void;
}