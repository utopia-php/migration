<?php

namespace Utopia\Migration;

class Transfer
{
    public const GROUP_GENERAL = 'general';

    public const GROUP_AUTH = 'auth';

    public const GROUP_STORAGE = 'storage';

    public const GROUP_FUNCTIONS = 'functions';

    public const GROUP_DATABASES = 'databases';

    public const GROUP_SETTINGS = 'settings';

    public const GROUP_AUTH_RESOURCES = [Resource::TYPE_USER, Resource::TYPE_TEAM, Resource::TYPE_MEMBERSHIP, Resource::TYPE_HASH];

    public const GROUP_STORAGE_RESOURCES = [Resource::TYPE_FILE, Resource::TYPE_BUCKET];

    public const GROUP_FUNCTIONS_RESOURCES = [Resource::TYPE_FUNCTION, Resource::TYPE_ENVVAR, Resource::TYPE_DEPLOYMENT];

    public const GROUP_DATABASES_RESOURCES = [Resource::TYPE_DATABASE, Resource::TYPE_COLLECTION, Resource::TYPE_INDEX, Resource::TYPE_ATTRIBUTE, Resource::TYPE_DOCUMENT];

    public const GROUP_SETTINGS_RESOURCES = [];

    public const ALL_PUBLIC_RESOURCES = [
        Resource::TYPE_USER, Resource::TYPE_TEAM,
        Resource::TYPE_MEMBERSHIP, Resource::TYPE_FILE,
        Resource::TYPE_BUCKET, Resource::TYPE_FUNCTION,
        Resource::TYPE_ENVVAR, Resource::TYPE_DEPLOYMENT,
        Resource::TYPE_DATABASE, Resource::TYPE_COLLECTION,
        Resource::TYPE_INDEX, Resource::TYPE_ATTRIBUTE,
        Resource::TYPE_DOCUMENT,
    ];

    public const STORAGE_MAX_CHUNK_SIZE = 1024 * 1024 * 5; // 5MB

    /**
     * @return Transfer
     */
    public function __construct(Source $source, Destination $destination)
    {
        $this->source = $source;
        $this->destination = $destination;
        $this->cache = new Cache();

        $this->source->registerCache($this->cache);
        $this->destination->registerCache($this->cache);
        $this->destination->setSource($source);

        return $this;
    }

    protected Source $source;

    protected Destination $destination;

    protected string $currentResource;

    /**
     * A local cache of resources that were transferred.
     */
    protected Cache $cache;

    protected array $options = [];

    protected array $callbacks = [];

    protected array $events = [];

    protected array $resources = [];

    public function getStatusCounters()
    {
        $status = [];

        foreach ($this->resources as $resource) {
            $status[$resource] = [
                Resource::STATUS_PENDING => 0,
                Resource::STATUS_SUCCESS => 0,
                Resource::STATUS_ERROR => 0,
                Resource::STATUS_SKIPPED => 0,
                Resource::STATUS_PROCESSING => 0,
                Resource::STATUS_WARNING => 0,
            ];
        }

        if ($this->source->previousReport) {
            foreach ($this->source->previousReport as $resource => $data) {
                if ($resource != 'size' && $resource != 'version') {
                    $status[$resource]['pending'] = $data;
                }
            }
        }

        foreach ($this->cache->getAll() as $resources) {
            foreach ($resources as $resource) {
                /** @var resource $resource */
                $status[$resource->getName()][$resource->getStatus()]++;
                $status[$resource->getName()]['pending']--;
            }
        }

        return $status;
    }

    /**
     * Transfer Resources between adapters
     *
     * @param  callable  $callback (array $resources)
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

        $computedResources = array_map('strtolower', $computedResources);

        $this->resources = $computedResources;
        $this->destination->run($computedResources, $callback, $this->source);
    }

    /**
     * Get Cache
     */
    public function getCache(): Cache
    {
        return $this->cache;
    }

    /**
     *  Get Current Resource
     *
     **/
    public function getCurrentResource(): string
    {
        return $this->currentResource;
    }

    /**
     * Get Transfer Report
     *
     * @param  string  $statusLevel
     * If no status level is provided, all status types will be returned.
     */
    public function getReport(string $statusLevel = ''): array
    {
        $report = [];

        $cache = $this->cache->getAll();

        foreach ($cache as $type => $resources) {
            foreach ($resources as $resource) {
                if ($statusLevel && $resource->getStatus() !== $statusLevel) {
                    continue;
                }

                $report[] = [
                    'resource' => $type,
                    'id' => $resource->getId(),
                    'status' => $resource->getStatus(),
                    'message' => $resource->getMessage(),
                ];
            }
        }

        return $report;
    }
}
