<?php

use Utopia\Transfer\Destination;
use Utopia\Transfer\Resource;
use Utopia\Transfer\Transfer;

class MockDestination extends Destination {
    static function getName(): string
    {
        return 'MockDestination';
    } 

    function getSupportedResources(): array
    {
        return [
            Resource::TYPE_ATTRIBUTE,
            Resource::TYPE_BUCKET,
            Resource::TYPE_COLLECTION,
            Resource::TYPE_DATABASE,
            Resource::TYPE_DOCUMENT,
            Resource::TYPE_FILE,
            Resource::TYPE_FILEDATA,
            Resource::TYPE_FUNCTION,
            Resource::TYPE_DEPLOYMENT,
            Resource::TYPE_HASH,
            Resource::TYPE_INDEX,
            Resource::TYPE_USER,
            Resource::TYPE_ENVVAR,
            Resource::TYPE_TEAM,
            Resource::TYPE_TEAM_MEMBERSHIP,
        ];
    }

    function importResources(array $resources, callable $callback): void
    {
    }

    function report(array $groups = []): array
    {
        return [];
    }
}