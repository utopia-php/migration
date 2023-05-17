<?php

namespace Utopia\Transfer;

use Utopia\Transfer\ResourceCache;
use Utopia\Transfer\Destination;
use Utopia\Transfer\Source;

class Transfer
{
    public const GROUP_GENERAL = 'General';
    public const GROUP_AUTH = 'Auth';
    public const GROUP_STORAGE = 'Storage';
    public const GROUP_FUNCTIONS = 'Functions';
    public const GROUP_DATABASES = 'Databases';
    public const GROUP_SETTINGS = 'Settings';

    public const GROUP_AUTH_RESOURCES = [Resource::TYPE_USER, Resource::TYPE_TEAM, Resource::TYPE_TEAM_MEMBERSHIP, Resource::TYPE_HASH];
    public const GROUP_STORAGE_RESOURCES = [Resource::TYPE_FILE, Resource::TYPE_BUCKET, Resource::TYPE_FILEDATA];
    public const GROUP_FUNCTIONS_RESOURCES = [Resource::TYPE_FUNCTION, Resource::TYPE_ENVVAR, Resource::TYPE_DEPLOYMENT];
    public const GROUP_DATABASES_RESOURCES = [Resource::TYPE_DATABASE, Resource::TYPE_COLLECTION, Resource::TYPE_INDEX, Resource::TYPE_ATTRIBUTE, Resource::TYPE_DOCUMENT];
    public const GROUP_SETTINGS_RESOURCES = [];
    public const ALL_PUBLIC_RESOURCES = [
        Resource::TYPE_USER, Resource::TYPE_TEAM,
        Resource::TYPE_TEAM_MEMBERSHIP, Resource::TYPE_FILE,
        Resource::TYPE_BUCKET, Resource::TYPE_FUNCTION,
        Resource::TYPE_ENVVAR, Resource::TYPE_DEPLOYMENT,
        Resource::TYPE_DATABASE, Resource::TYPE_COLLECTION,
        Resource::TYPE_INDEX, Resource::TYPE_ATTRIBUTE,
        Resource::TYPE_DOCUMENT
    ];

    public const STORAGE_MAX_CHUNK_SIZE = 1024 * 1024 * 5; // 5MB

    /**
     * @param Source $source
     * @param Destination $destination
     *
     * @return Transfer
     */
    public function __construct(Source $source, Destination $destination)
    {
        $this->source = $source;
        $this->destination = $destination;
        $this->resourceCache = new ResourceCache();

        $this->source->registerTransferCache($this->resourceCache);
        $this->destination->registerTransferCache($this->resourceCache);
        $this->destination->setSource($source);

        return $this;
    }

    /**
     * @var Source
     */
    protected Source $source;

    /**
     * @var Destination
     */
    protected Destination $destination;

    /**
     * @var string
     */
    protected string $currentResource;

    /**
     * A local cache of resources that were transferred.
     *
     * @var ResourceCache
     */
    protected ResourceCache $resourceCache;

    /**
     * @var array
     */
    protected array $options = [];

    /**
     * @var array
     */
    protected array $callbacks = [];

    /**
     * @var array
     */
    protected array $events = [];

    public function getStatusCounters()
    {
        $status = [];

        foreach ($this->resourceCache->getAll() as $resources) {
            foreach ($resources as $resource) {
                /** @var Resource $resource */
                if (!array_key_exists($resource->getName(), $status)) {
                    $status[$resource->getName()] = [
                        Resource::STATUS_PENDING => 0,
                        Resource::STATUS_SUCCESS => 0,
                        Resource::STATUS_ERROR => 0,
                        Resource::STATUS_SKIPPED => 0,
                        Resource::STATUS_PROCESSING => 0,
                        Resource::STATUS_WARNING => 0,
                    ];
                }

                $status[$resource->getName()][$resource->getStatus()]++;
            }
        }

        return $status;
    }

    /**
     * Transfer Resources between adapters
     *
     * @param array $resources
     * @param callable $callback (array $resources)
     */
    public function run(array $resources, callable $callback): void
    {
        // Allows you to push entire groups if you want.
        $computedResources = [];
        foreach ($resources as $resource) {
            if (is_array($resource)) {
                $computedResources = array_merge($computedResources, $resource);
            } else {
                $computedResources[] = $resource;
            }
        }

        $this->destination->run($computedResources, $callback, $this->source);
    }

    /**
     * Get Resource Cache
     *
     * @return ResourceCache
     */
    public function getResourceCache(): ResourceCache
    {
        return $this->resourceCache;
    }

    /**
     *  Get Current Resource
     *
     * @return string
     **/

    public function getCurrentResource(): string
    {
        return $this->currentResource;
    }

    /**
     * Get Transfer Report
     * 
     * @param string $statusLevel
     * If no status level is provided, all status types will be returned.
     * 
     * @return array
     */
    public function getReport(string $statusLevel = ''): array
    {
        $report = [];

        $resourceCache = $this->resourceCache->getAll();

        foreach ($resourceCache as $type => $resources) {
            foreach ($resources as $resource) {
                if ($statusLevel && $resource->getStatus() !== $statusLevel) {
                    continue;
                }

                $report[] = [
                    'resource' => $type,
                    'id' => $resource->getId(),
                    'status' => $resource->getStatus(),
                    'message' => $resource->getReason(),
                ];
            }
        }

        return $report;
    }
}
