<?php

namespace Utopia\Transfer;

/**
 * Cache stores a local version of all data copied over from the source, This can be used as reference point for
 * previous transfers and also help the destination to determine what needs to be updated, modified,
 * added or removed. It is also used for debugging and validation purposes.
 */
class Cache
{
    protected $cache = [];

    public function __construct()
    {
        $this->cache = [];
    }

    /**
     * Add Resource
     *
     * Places the resource in the cache, in the cache backend this also gets assigned a unique ID.
     *
     * @param  resource  $resource
     * @return void
     */
    public function add($resource)
    {
        if (! $resource->getInternalId()) {
            $resourceId = uniqid();
            if (isset($this->cache[$resource->getName()][$resourceId])) {
                $resourceId = uniqid();
            }
            $resource->setInternalId(uniqid());
        }

        if ($resource->getName() == Resource::TYPE_FILE || $resource->getName() == Resource::TYPE_FUNCTION) {
            /** @var \Utopia\Transfer\Resources\Storage\File|\Utopia\Transfer\Resources\Functions\Func $resource */
            $resource->setData(''); // Prevent Memory Leak
        }

        $this->cache[$resource->getName()][$resource->getInternalId()] = $resource;
    }

    public function addAll(array $resources)
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
     * @param  resource  $resource
     * @return void
     */
    public function update($resource)
    {
        if (! in_array($resource->getName(), $this->cache)) {
            $this->add($resource);
        }

        $this->cache[$resource->getName()][$resource->getInternalId()] = $resource;
    }

    public function updateAll($resources)
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
     * @param  resource  $resource
     * @return void
     */
    public function remove($resource)
    {
        if (! in_array($resource, $this->cache[$resource->getName()])) {
            throw new \Exception('Resource does not exist in cache');
        }

        unset($this->cache[$resource->getName()][$resource->getInternalId()]);
    }

    /**
     * Get Resources
     *
     * @param  string|resource  $resourceType
     * @return resource[]
     */
    public function get($resource)
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
     * @return array
     */
    public function getAll()
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
    public function wipe()
    {
        $this->cache = [];
    }
}
