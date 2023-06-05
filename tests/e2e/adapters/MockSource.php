<?php

namespace Utopia\Tests;

use Utopia\Transfer\Source;
use Utopia\Transfer\Transfer;

class MockSource extends Source
{
    public static function getName(): string
    {
        return 'MockSource';
    }

    public function getSupportedResources(): array
    {
        return [
            Transfer::GROUP_AUTH,
            Transfer::GROUP_DATABASES,
            Transfer::GROUP_FUNCTIONS,
            Transfer::GROUP_SETTINGS,
            Transfer::GROUP_STORAGE,
        ];
    }

    public function report(array $groups = []): array
    {
        return [];
    }
}
