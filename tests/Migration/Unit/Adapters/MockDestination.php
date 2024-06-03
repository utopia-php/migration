<?php

namespace Utopia\Tests\Unit\Adapters;

use Utopia\Migration\Destination;
use Utopia\Migration\Resource;

class MockDestination extends Destination
{
    public array $data = [];

    public static function getName(): string
    {
        return 'Mock Destination';
    }

    public static function getSupportedResources(): array
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
            Resource::TYPE_ENVIRONMENT_VARIABLE,
            Resource::TYPE_TEAM,
            Resource::TYPE_MEMBERSHIP,
        ];
    }

    public function import(array $resources, callable $callback): void
    {
        foreach ($resources as $resource) {
            /** @var resource $resource */
            switch ($resource->getName()) {
                case 'Deployment':
                    /** @var Deployment $resource */
                    if ($resource->getStart() === 0) {
                        $this->data[$resource->getName()][$resource->getInternalId()] = $resource->asArray();
                    }

                    // file_put_contents($this->path . 'deployments/' . $resource->getId() . '.tar.gz', $resource->getData(), FILE_APPEND);
                    break;
                case Resource::TYPE_FILE:
                    /** @var File $resource */
                    break;
                default:
                    $this->data[$resource->getName()][$resource->getId()] = $resource->asArray();
                    break;
            }

            $resource->setStatus(Resource::STATUS_SUCCESS);
            $this->cache->update($resource);
        }

        $callback($resources);
    }

    public function report(array $groups = []): array
    {
        return [];
    }

    public function get(string $resource, string $id): ?array
    {
        if (! array_key_exists($resource, $this->data)) {
            return null;
        }

        if (! array_key_exists($id, $this->data[$resource])) {
            return null;
        }

        return $this->data[$resource][$id];
    }
}
