<?php

namespace Utopia\Transfer;

class Cache
{
    protected $cache = [];

    public function __construct()
    {
        $this->cache = [];
    }

    public function add($resource)
    {
        if (! $resource->getInternalId()) {
            $resourceId = uniqid();
            if (isset($this->cache[$resource->getName()][$resourceId])) {
                $resourceId = uniqid();
            }
            $resource->setInternalId(uniqid());
        }
        $this->cache[$resource->getName()][$resource->getInternalId()] = $resource;
    }

    public function addAll(array $resources)
    {
        foreach ($resources as $resource) {
            $this->add($resource);
        }
    }

    public function update($resource)
    {
        if (! in_array($resource, $this->cache[$resource->getName()])) {
            throw new \Exception('Resource does not exist in cache');
        }

        $this->cache[$resource->getName()][$resource->getInternalId()] = $resource;
    }

    public function updateAll($resources)
    {
        foreach ($resources as $resource) {
            $this->update($resource);
        }
    }

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

    public function getAll()
    {
        return $this->cache;
    }

    public function wipe()
    {
        $this->cache = [];
    }
}
