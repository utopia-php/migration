<?php

namespace Utopia\Transfer;

use Utopia\Transfer\Destination;
use Utopia\Transfer\Source;

class Transfer {
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
    function __construct(Source $source, Destination $destination) {
        $this->source = $source;
        $this->destination = $destination;

        $this->source->registerLogs($this->logs);
        $this->destination->registerLogs($this->logs);

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
     * @var array
     */
    protected array $resources = [];

    /**
     * @var array
     */
    protected array $options = [];

    /**
     * @var array
     */
    protected array $logs = [];

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
    public function run(array $resources, callable $callback): void {
        $this->destination->run($resources, function ($data) use ($resources) {
            var_dump($data);
        }, $this->source);
    }
}