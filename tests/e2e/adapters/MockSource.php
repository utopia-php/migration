<?php

namespace Utopia\Tests;

use Utopia\Transfer\Destination;
use Utopia\Transfer\Transfer;

class MockSource extends Destination
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

    public function importResources(array $resources, callable $callback): void
    {
    }

    public function report(array $groups = []): array
    {
        return [];
    }
}
