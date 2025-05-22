<?php

namespace Utopia\Migration;

use Utopia\Migration\Resources\Functions\Deployment;
use Utopia\Migration\Resources\Storage\File;

/**
 * Cache stores a local version of all data copied over from the source, This can be used as reference point for
 * previous transfers and also help the destination to determine what needs to be updated, modified,
 * added or removed. It is also used for debugging and validation purposes.
 */
class Cache
{
    /**
     * @var array<string, array<string, Resource|string>> $cache
     */
    protected array $cache = [];

    public function __construct()
    {
        $this->cache = [];
    }

    /**
     * Add Resource
     *
     * Places the resource in the cache, in the cache backend this also gets assigned a unique ID.
     *
     */
    public function add(Resource $resource): void
    {
        if (! $resource->getSequence()) {
            $resourceId = uniqid();
            if (isset($this->cache[$resource->getName()][$resourceId])) {
                $resourceId = uniqid();
                // todo: $resourceId is not used?
            }
            $resource->setSequence(uniqid());
        }

        if ($resource->getName() == Resource::TYPE_DOCUMENT) {
            $status = $resource->getStatus();
            $documentId = $resource->getSequence();
            $this->cache[$resource->getName()][$documentId] = $status;
            return;
        }

        if ($resource->getName() == Resource::TYPE_FILE || $resource->getName() == Resource::TYPE_DEPLOYMENT) {
            /** @var File|Deployment $resource */
            $resource->setData(''); // Prevent Memory Leak
        }

        $this->cache[$resource->getName()][$resource->getSequence()] = $resource;
    }

    /**
     * Add All Resources
     *
     * @param  array<Resource>  $resources
     * @return void
     */
    public function addAll(array $resources): void
    {
        foreach ($resources as $resource) {
            $this->add($resource);
        }
    }

    /**
     * Update Resource
     *
     * Updates the resource in the cache, if the resource does not exist in the cache an exception is thrown.
     * Use Add to add a new resource to the cache.
     *
     * @param Resource $resource
     * @return void
     */
    public function update(Resource $resource): void
    {
        // if documents then updating the status counter only
        if ($resource->getName() == Resource::TYPE_DOCUMENT) {
            $documentId = $resource->getSequence();
            if (!isset($this->cache[$resource->getName()][$documentId])) {
                $this->add($resource);
            } else {
                $status = $resource->getStatus();
                $this->cache[$resource->getName()][$documentId] = $status;
            }
            return;
        }

        if (! in_array($resource->getName(), $this->cache)) {
            $this->add($resource);
        }

        $this->cache[$resource->getName()][$resource->getSequence()] = $resource;
    }

    /**
     * @param array<Resource> $resources
     * @return void
     */
    public function updateAll(array $resources): void
    {
        foreach ($resources as $resource) {
            $this->update($resource);
        }
    }

    /**
     * Remove Resource
     *
     * Removes the resource from the cache, if the resource does not exist in the cache an exception is thrown.
     *
     * @param Resource $resource
     * @return void
     * @throws \Exception
     */
    public function remove(Resource $resource): void
    {
        if ($resource->getName() === Resource::TYPE_DOCUMENT) {
            if (! isset($this->cache[$resource->getName()][$resource->getSequence()])) {
                throw new \Exception('Resource does not exist in cache');
            }
        }
        if (! in_array($resource, $this->cache[$resource->getName()])) {
            throw new \Exception('Resource does not exist in cache');
        }

        unset($this->cache[$resource->getName()][$resource->getSequence()]);
    }

    /**
     * Get Resources
     *
     * @param string|Resource $resource
     * @return array<Resource>
     */
    public function get(string|Resource $resource): array
    {
        if (is_string($resource)) {
            return $this->cache[$resource] ?? [];
        } else {
            return $this->cache[$resource->getName()] ?? [];
        }
    }

    /**
     * Get All Resources
     *
     * @return array<string, array<string, Resource|string>>
     */
    public function getAll(): array
    {
        return $this->cache;
    }

    /**
     * Wipe Cache
     *
     * Removes all resources from the cache.
     *
     * @return void
     */
    public function wipe(): void
    {
        $this->cache = [];
    }
}
