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

    public const GROUP_FUNCTIONS_RESOURCES = [Resource::TYPE_FUNCTION, Resource::TYPE_ENVIRONMENT_VARIABLE, Resource::TYPE_DEPLOYMENT];

    public const GROUP_DATABASES_RESOURCES = [Resource::TYPE_DATABASE, Resource::TYPE_COLLECTION, Resource::TYPE_INDEX, Resource::TYPE_ATTRIBUTE, Resource::TYPE_DOCUMENT];

    public const GROUP_SETTINGS_RESOURCES = [];

    public const ALL_PUBLIC_RESOURCES = [
        Resource::TYPE_USER,
        Resource::TYPE_TEAM,
        Resource::TYPE_MEMBERSHIP,
        Resource::TYPE_FILE,
        Resource::TYPE_BUCKET,
        Resource::TYPE_FUNCTION,
        Resource::TYPE_ENVIRONMENT_VARIABLE,
        Resource::TYPE_DEPLOYMENT,
        Resource::TYPE_DATABASE,
        Resource::TYPE_COLLECTION,
        Resource::TYPE_INDEX,
        Resource::TYPE_ATTRIBUTE,
        Resource::TYPE_DOCUMENT,
    ];

    public const ROOT_RESOURCES = [
        Resource::TYPE_BUCKET,
        Resource::TYPE_DATABASE,
        Resource::TYPE_FUNCTION,
        Resource::TYPE_USER,
        Resource::TYPE_TEAM,
    ];

    public const STORAGE_MAX_CHUNK_SIZE = 1024 * 1024 * 5; // 5MB

    protected Source $source;

    protected Destination $destination;

    protected string $currentResource;

    /**
     * A local cache of resources that were transferred.
     */
    protected Cache $cache;

    /**
     * @var array<string, mixed>
     */
    protected array $options = [];

    /**
     * @var array<string, mixed>
     */
    protected array $events = [];

    /**
     * @var array<string, mixed>
     */
    protected array $resources = [];

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

    /**
     * @return array<string, array<string, int>>
     */
    public function getStatusCounters(): array
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
                if ($resource != 'size' && $resource != 'version' && isset($status[$resource])) {
                    $status[$resource]['pending'] = $data;
                }
            }
        }

        foreach ($this->cache->getAll() as $resources) {
            foreach ($resources as $resource) {
                /** @var resource $resource */
                if (isset($status[$resource->getName()])) {
                    $status[$resource->getName()][$resource->getStatus()]++;
                    if ($status[$resource->getName()]['pending'] > 0) {
                        $status[$resource->getName()]['pending']--;
                    }
                }
            }
        }

        // Process Destination Errors
        foreach ($this->destination->getErrors() as $error) {
            /** @var Exception $error */
            if (isset($status[$error->getResourceGroup()])) {
                $status[$error->getResourceGroup()][Resource::STATUS_ERROR]++;
            }
        }

        // Process source errors
        foreach ($this->source->getErrors() as $error) {
            /** @var Exception $error */
            if (isset($status[$error->getResourceName()])) {
                $status[$error->getResourceName()][Resource::STATUS_ERROR]++;
            }
        }

        // Remove all empty resources
        foreach ($status as $resource => $data) {
            $allEmpty = true;

            foreach ($data as $count) {
                if ($count > 0) {
                    $allEmpty = false;
                }
            }

            if ($allEmpty) {
                unset($status[$resource]);
            }
        }

        return $status;
    }

    /**
     * Transfer Resources between adapters
     *
     * @param  array<string>  $resources Resources to transfer
     * @param  callable  $callback Callback to run after transfer
     * @param  string|null  $rootResourceId Root resource ID, If enabled you can only transfer a single root resource
     *
     * @throws \Exception
     */
    public function run(array $resources, callable $callback, string $rootResourceId = null): void
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

        // Check we don't have multiple root resources if rootResourceId is set

        $rootResourceId = $rootResourceId ?? ''; // Convert null to empty string

        if ($rootResourceId) {
            $rootResourceCount = count(array_intersect($computedResources, self::ROOT_RESOURCES));

            if ($rootResourceCount > 1) {
                throw new \Exception('Multiple root resources found. Only one root resource can be transferred at a time if using $rootResourceId.');
            }
        }

        $this->resources = $computedResources;

        $this->destination->run($computedResources, $callback, $rootResourceId);
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
     * @param  string  $statusLevel If no status level is provided, all status types will be returned.
     * @return array<array<string, mixed>>
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

    /**
     * @throws \Exception
     */
    public static function extractServices(array $services): array
    {
        $resources = [];
        foreach ($services as $service) {
            var_dump('converting resource === '.$service);
            $resources = match ($service) {
                self::GROUP_FUNCTIONS => array_merge($resources, self::GROUP_FUNCTIONS_RESOURCES),
                self::GROUP_STORAGE => array_merge($resources, self::GROUP_STORAGE_RESOURCES),
                self::GROUP_GENERAL => array_merge($resources, []),
                self::GROUP_AUTH => array_merge($resources, self::GROUP_AUTH_RESOURCES),
                self::GROUP_DATABASES => array_merge($resources, self::GROUP_DATABASES_RESOURCES),
                self::GROUP_SETTINGS => array_merge($resources, self::GROUP_SETTINGS_RESOURCES),
                default => throw new \Exception('No service group found'),
            };
        }

        return $resources;
    }
}
