<?php

namespace Utopia\Transfer;

class ResourceCache
{
    /**
     * Resource Cache
     *
     * @var array $resourceCache
     */
    protected $resourceCache = [];

    public function __construct()
    {
        $this->resourceCache = [];
    }

    public function add($resource)
    {
        $resourceUUID = uniqid();
        $resource->setInternalId($resourceUUID); // Assign each resource a unique ID
        $this->resourceCache[get_class($resource)][$resourceUUID] = $resource;
    }

    public function addAll($resources)
    {
        foreach ($resources as $resource) {
            $this->add($resource);
        }
    }

    public function update($resource)
    {
        if (!in_array($resource, $this->resourceCache[get_class($resource)])) {
            throw new \Exception('Resource does not exist in cache');
        }

        $this->resourceCache[get_class($resource)][$resource->getInternalId()] = $resource;
    }

    public function updateAll($resources)
    {
        foreach ($resources as $resource) {
            $this->update($resource);
        }
    }

    public function remove($resource)
    {
        if (!in_array($resource, $this->resourceCache[get_class($resource)])) {
            throw new \Exception('Resource does not exist in cache');
        }

        unset($this->resourceCache[get_class($resource)][$resource->getInternalId()]);
    }

    /**
     * Get Resources
     *
     * @param string|Resource $resourceType
     * 
     * @return Resource[]
     */
    public function get($resource)
    {
        if (is_string($resource)) {
            return $this->resourceCache[$resource] ?? [];
        } else {
            return $this->resourceCache[get_class($resource)] ?? [];
        }
    }

    public function getAll()
    {
        return $this->resourceCache;
    }

    public function clear()
    {
        $this->resourceCache = [];
    }
}
