<?php

namespace Utopia\Transfer;

use Utopia\Transfer\Destination;
use Utopia\Transfer\Source;

class Transfer
{
    const RESOURCE_USERS = 'users';
    const RESOURCE_FILES = 'files';
    const RESOURCE_FUNCTIONS = 'functions';
    const RESOURCE_DATABASES = 'databases';
    const RESOURCE_COLLECTIONS = 'collections';

    /**
     * @param Source $source
     * @param Destination $destination
     * 
     * @return Transfer
     */
    function __construct(Source $source, Destination $destination)
    {
        $this->source = $source;
        $this->destination = $destination;

        $this->source->registerLogs($this->logs);
        $this->source->registerResourceCache($this->resources);
        $this->destination->registerLogs($this->logs);
        $this->destination->registerResourceCache($this->resources);

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
     * A local cache of resources that were transferred.
     * 
     * @var array
     */
    protected array $resources = [
        self::RESOURCE_COLLECTIONS => [],
        self::RESOURCE_DATABASES => [],
        self::RESOURCE_FILES => [],
        self::RESOURCE_FUNCTIONS => [],
        self::RESOURCE_USERS => []
    ];

    /**
     * @var array
     */
    protected array $options = [];

    /**
     * @var array
     */
    protected array $logs = [
        Log::ERROR => [],
        Log::WARNING => [],
        Log::INFO => [],
        Log::FATAL => []
    ];

    /**
     * @var array
     */
    protected array $callbacks = [];

    /**
     * @var array
     */
    protected array $events = [];

    /**
     * Transfer Resources between adapters
     * 
     * @param array $resources
     * @param callable $callback
     */
    public function run(array $resources, callable $callback): void
    {
        $this->destination->run($resources, $callback, $this->source);
    }

    /**
     * Get Logs
     * 
     * If no level is provided then the function returns all logs combined ordered by timestamp.
     * 
     * @param string $level
     * 
     * @return array
     */
    public function getLogs($level = ''): array
    {
        if (!empty($level)) {
            return $this->logs[$level];
        }

        $mergedLogs = array_merge($this->logs[Log::ERROR], $this->logs[Log::WARNING], $this->logs[Log::INFO], $this->logs[Log::FATAL]);

        $timestamps = [];
        foreach ($mergedLogs as $key => $log) {
            $timestamps[$key] = $log->getTimestamp();
        }
        array_multisort($timestamps, SORT_ASC, $mergedLogs);

        return $mergedLogs;
    }
}
