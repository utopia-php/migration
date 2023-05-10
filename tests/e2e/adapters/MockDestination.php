<?php

use Utopia\Transfer\Destination;
use Utopia\Transfer\Transfer;

class MockDestination extends Destination {
    static function getName(): string
    {
        return 'MockDestination';
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