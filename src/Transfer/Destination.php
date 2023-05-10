<?php

namespace Utopia\Transfer;

abstract class Destination extends Target
{
    /**
     * Source
     *
     * @var Source $source
     */
    protected Source $source;

    /**
     * Get Source
     *
     * @return Source
     */
    public function getSource(): Source
    {
        return $this->source;
    }

    /**
     * Set Soruce
     *
     * @param Source $source
     *
     * @return self
     */
    public function setSource(Source $source): self
    {
        $this->source = $source;
        return $this;
    }

    /**
     * Transfer Resources to Destination from Source callback
     *
     * @param array $resources
     * @param callable $callback
     */
    public function run(array $resources, callable $callback): void
    {
        $this->source->run($resources, function (array $resources) use ($callback) {
            $this->importResources($resources, $callback);
        });
    }

    /**
     * Import Resources
     *
     * @param array $resources
     * @param callable $callback (Progress $progress)
     *
     */
    abstract public function importResources(array $resources, callable $callback): void;
}
