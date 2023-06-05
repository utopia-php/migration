<?php

namespace Tests\E2E\Sources;

class Appwrite extends SourceTest
{
    public function testExportResources(): void
    {
    }

    public function testReport(): void
    {
        $report = $this->source->report();

        $this->assertIsArray($report);
        $this->validateReport($report);
    }
}
