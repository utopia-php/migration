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
        if (!$resource->getInternalId()) {
            $resourceId = uniqid();
            if (isset($this->resourceCache[$resource->getName()][$resourceId])) {
                $resourceId = uniqid();
            }
            $resource->setInternalId(uniqid());
        }
        $this->resourceCache[$resource->getName()][$resource->getInternalId()] = $resource;
    }

    public function addAll(array $resources)
    {
        foreach ($resources as $resource) {
            $this->add($resource);
        }
    }

    public function update($resource)
    {
        if (!in_array($resource, $this->resourceCache[$resource->getName()])) {
            throw new \Exception('Resource does not exist in cache');
        }

        $this->resourceCache[$resource->getName()][$resource->getInternalId()] = $resource;
    }

    public function updateAll($resources)
    {
        foreach ($resources as $resource) {
            $this->update($resource);
        }
    }

    public function remove($resource)
    {
        if (!in_array($resource, $this->resourceCache[$resource->getName()])) {
            throw new \Exception('Resource does not exist in cache');
        }

        unset($this->resourceCache[$resource->getName()][$resource->getInternalId()]);
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
            return $this->resourceCache[$resource->getName()] ?? [];
        }
    }

    public function getAll()
    {
        return $this->resourceCache;
    }

    public function wipe()
    {
        $this->resourceCache = [];
    }
}
