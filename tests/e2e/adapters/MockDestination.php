<?php

namespace Utopia\Tests;

use Utopia\Transfer\Destination;
use Utopia\Transfer\Resource;

class MockDestination extends Destination
{
    public static function getName(): string
    {
        return 'MockDestination';
    }

    public function getSupportedResources(): array
    {
        return [
            Resource::TYPE_ATTRIBUTE,
            Resource::TYPE_BUCKET,
            Resource::TYPE_COLLECTION,
            Resource::TYPE_DATABASE,
            Resource::TYPE_DOCUMENT,
            Resource::TYPE_FILE,
            Resource::TYPE_FUNCTION,
            Resource::TYPE_DEPLOYMENT,
            Resource::TYPE_HASH,
            Resource::TYPE_INDEX,
            Resource::TYPE_USER,
            Resource::TYPE_ENVVAR,
            Resource::TYPE_TEAM,
            Resource::TYPE_MEMBERSHIP,
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
