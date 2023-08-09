<?php

namespace Utopia\Migration;

abstract class Destination extends Target
{
    /**
     * Source
     */
    protected Source $source;

    /**
     * Get Source
     */
    public function getSource(): Source
    {
        return $this->source;
    }

    /**
     * Set Soruce
     */
    public function setSource(Source $source): self
    {
        $this->source = $source;

        return $this;
    }

    /**
     * Transfer Resources to Destination from Source callback
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
     * @param  callable  $callback (array $resources)
     */
    abstract protected function import(array $resources, callable $callback): void;
}
