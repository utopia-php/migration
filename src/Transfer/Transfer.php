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
    const RESOURCE_DOCUMENTS = 'documents';

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
        $this->source->registerTransferHooks($this->resources, $this->counters);
        $this->destination->registerLogs($this->logs);
        $this->destination->registerTransferHooks($this->resources, $this->counters);

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
     * Counters
     * 
     * @var array $counter
     */
    protected $counters = [
        Transfer::RESOURCE_USERS => [
            'total' => 0,
            'current' => 0,
            'failed' => 0,
            'skipped' => 0,
        ],
        Transfer::RESOURCE_FILES => [
            'total' => 0,
            'current' => 0,
            'failed' => 0,
            'skipped' => 0,
        ],
        Transfer::RESOURCE_FUNCTIONS => [
            'total' => 0,
            'current' => 0,
            'failed' => 0,
            'skipped' => 0,
        ],
        Transfer::RESOURCE_DATABASES => [
            'total' => 0,
            'current' => 0,
            'failed' => 0,
            'skipped' => 0,
        ],
        Transfer::RESOURCE_DOCUMENTS => [
            'total' => 0,
            'current' => 0,
            'failed' => 0,
            'skipped' => 0,
        ]
    ];

    /**
     * A local cache of resources that were transferred.
     * 
     * @var array
     */
    protected array $resources = [
        self::RESOURCE_DOCUMENTS => [],
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
        Log::FATAL => [],
        Log::SUCCESS => []
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
     * @param callable $callback (Progress $progress)
     */
    public function run(array $resources, callable $callback): void
    {
        $this->destination->run($resources, function (Progress $progress) use ($callback) {
            $this->currentResource = $progress->getResourceType();

            $callback($progress);
        }, $this->source);
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

    /**
     * Get Resource Cache
     * 
     * @return array
     */
    public function getResourceCache(): array
    {
        return $this->resources;
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
}
