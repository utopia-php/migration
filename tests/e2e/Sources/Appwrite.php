<?php

namespace Tests\E2E\Sources;

use PHPUnit\Framework\TestCase;
use Utopia\Transfer\Resources\Auth\Hash;
use Utopia\Transfer\Resources\Auth\Team;
use Utopia\Transfer\Resources\Auth\User;

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
