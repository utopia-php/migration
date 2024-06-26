<?php

namespace Utopia\Migration;

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
     * @param  array<string>  $resources  Resources to transfer
     * @param  callable  $callback  Callback to run after transfer
     */
    public function run(array $resources, callable $callback): void
    {
        $this->source->run($resources, function (array $resources) use ($callback) {
            $this->import($resources, $callback);
        });
    }

    /**
     * Import Resources
     *
     * @param  resource[]  $resources  Resources to import
     * @param  callable  $callback  Callback to run after import
     */
    abstract protected function import(array $resources, callable $callback): void;
}
