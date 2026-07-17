<?php

namespace Utopia\Migration;

abstract class Destination extends Target
{
    /**
     * @var array{rootResourceId: string, rootResourceType: string, rootResourceChildId: string}|null
     */
    private ?array $resourceSelector = null;

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
        $import = function (array $resources) use ($callback) {
            $this->import($resources, $callback);
        };

        if ($this->resourceSelector !== null) {
            $this->source->runWithResourceSelector(
                $resources,
                $import,
                $this->resourceSelector['rootResourceId'],
                $this->resourceSelector['rootResourceType'],
                $this->resourceSelector['rootResourceChildId'],
            );

            return;
        }

        $this->source->run($resources, $import, $rootResourceId, $rootResourceType);
    }

    /**
     * Transfer resources using separate, opaque root and child IDs.
     *
     * @param array<string> $resources Resources to transfer
     * @param callable(array<Resource>): void $callback to run after transfer
     */
    public function runWithResourceSelector(
        array $resources,
        callable $callback,
        string $rootResourceId,
        string $rootResourceType,
        string $rootResourceChildId,
    ): void {
        $previousResourceSelector = $this->resourceSelector;
        $this->resourceSelector = [
            'rootResourceId' => $rootResourceId,
            'rootResourceType' => $rootResourceType,
            'rootResourceChildId' => $rootResourceChildId,
        ];

        try {
            $this->run($resources, $callback, $rootResourceId, $rootResourceType);
        } finally {
            $this->resourceSelector = $previousResourceSelector;
        }
    }

    /**
     * Import Resources
     *
     * @param  Resource[]  $resources  Resources to import
     * @param  callable  $callback  Callback to run after import
     */
    abstract protected function import(array $resources, callable $callback): void;
}
