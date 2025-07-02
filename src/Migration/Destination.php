<?php

namespace Utopia\Migration;

use Utopia\CLI\Console;

abstract class Destination extends Target
{
    /**
     * Source
     */
    protected Source $source;

    public function getSource(): Source
    {
        return $this->source;
    }

    public function setSource(Source $source): self
    {
        $this->source = $source;

        return $this;
    }

    /**
     * Transfer Resources to Destination from Source callback
     *
     * @param array<string> $resources Resources to transfer
     * @param callable(array<Resource>): void $callback to run after transfer
     * @param string $rootResourceId Root resource ID, If enabled you can only transfer a single root resource
     */
    public function run(
        array $resources,
        callable $callback,
        string $rootResourceId = '',
        string $rootResourceType = '',
    ): void {
        Console::log(json_encode(['stage' => 'destination#run'], JSON_PRETTY_PRINT));

        $this->source->run(
            $resources,
            function (array $resources) use ($callback) {
                $this->import($resources, $callback);
            },
            $rootResourceId,
            $rootResourceType,
        );
    }

    /**
     * Import Resources
     *
     * @param  Resource[]  $resources  Resources to import
     * @param  callable  $callback  Callback to run after import
     */
    abstract protected function import(array $resources, callable $callback): void;
}
