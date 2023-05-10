<?php

use Utopia\Transfer\Destination;
use Utopia\Transfer\Transfer;

class MockSource extends Destination {
    static function getName(): string
    {
        return 'MockSource';
    } 

    function getSupportedResources(): array
    {
        return [
            Transfer::GROUP_AUTH,
            Transfer::GROUP_DATABASES,
            Transfer::GROUP_FUNCTIONS,
            Transfer::GROUP_SETTINGS,
            Transfer::GROUP_STORAGE
        ];
    }

    function importResources(array $resources, callable $callback): void
    {
        
    }

    function check(array $resources = []): array
    {
        return [];
    }
}