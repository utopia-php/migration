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
     * @param array<string> $resources Resources to transfer
     * @param callable(array<Resource>): void $callback to run after transfer
     * @param string $rootResourceId Root resource ID. If set, only this root resource is transferred.
     * @param string $rootResourceType Resource type for $rootResourceId.
     * @param string $rootResourceChildId Optional child filter under the root resource. For database roots, this is the collection/table ID.
     */
    public function run(
        array $resources,
        callable $callback,
        string $rootResourceId = '',
        string $rootResourceType = '',
        string $rootResourceChildId = '',
    ): void {
        $this->source->run(
            $resources,
            function (array $resources) use ($callback) {
                $this->import($resources, $callback);
            },
            $rootResourceId,
            $rootResourceType,
            $rootResourceChildId,
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
