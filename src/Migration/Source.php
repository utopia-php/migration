<?php

namespace Utopia\Migration;

abstract class Source extends Target
{
    /**
     * @var callable(array<Resource>): void $transferCallback
     */
    protected $transferCallback;

    /**
     * @var array<string, int>
     */
    public array $previousReport = [];

    /**
     * @param array<Resource> $resources
     * @return void
     */
    public function callback(array $resources): void
    {
        ($this->transferCallback)($resources);
    }

    /**
     * Transfer Resources into destination
     *
     * @param array<string> $resources Resources to transfer
     * @param callable $callback Callback to run after transfer
     * @param string $rootResourceId Root resource ID, If enabled you can only transfer a single root resource
     */
    public function run(array $resources, callable $callback, string $rootResourceId = '', string $rootResourceType = ''): void
    {
        $this->rootResourceId = $rootResourceId;
        $this->rootResourceType = $rootResourceType;

        $this->transferCallback = function (array $returnedResources) use ($callback, $resources) {
            $prunedResources = [];
            foreach ($returnedResources as $resource) {
                /** @var Resource $resource */
                if (! in_array($resource->getName(), $resources)) {
                    $resource->setStatus(Resource::STATUS_SKIPPED);
                } else {
                    $prunedResources[] = $resource;
                }
            }

            $callback($returnedResources);
            $this->cache->addAll($prunedResources);
        };

        $this->exportResources($resources);
    }

    /**
     * Export Resources
     *
     * @param  array<string>  $resources  Resources to export
     */
    public function exportResources(array $resources): void
    {
        // Convert Resources back into their relevant groups

        $batchSize = $this->getBatchSize();

        $groups = [];
        foreach ($resources as $resource) {
            if (\in_array($resource, Transfer::GROUP_AUTH_RESOURCES)) {
                $groups[Transfer::GROUP_AUTH][] = $resource;
            } elseif (\in_array($resource, Transfer::GROUP_DATABASES_RESOURCES)) {
                $groups[Transfer::GROUP_DATABASES][] = $resource;
            } elseif (\in_array($resource, Transfer::GROUP_STORAGE_RESOURCES)) {
                $groups[Transfer::GROUP_STORAGE][] = $resource;
            } elseif (\in_array($resource, Transfer::GROUP_FUNCTIONS_RESOURCES)) {
                $groups[Transfer::GROUP_FUNCTIONS][] = $resource;
            }
        }

        if (empty($groups)) {
            return;
        }

        // Send each group to the relevant export function
        foreach ($groups as $group => $resources) {
            switch ($group) {
                case Transfer::GROUP_AUTH:
                    $this->exportGroupAuth($batchSize, $resources);
                    break;
                case Transfer::GROUP_DATABASES:
                    $this->exportGroupDatabases($batchSize, $resources);
                    break;
                case Transfer::GROUP_STORAGE:
                    $this->exportGroupStorage($batchSize, $resources);
                    break;
                case Transfer::GROUP_FUNCTIONS:
                    $this->exportGroupFunctions($batchSize, $resources);
                    break;
            }
        }
    }
    public function getBatchSize(): int
    {
        return 100;
    }

    /**
     * Export Auth Group
     *
     * @param  int  $batchSize  Max 100
     * @param  array<string>  $resources  Resources to export
     */
    abstract protected function exportGroupAuth(int $batchSize, array $resources): void;

    /**
     * Export Databases Group
     *
     * @param  int  $batchSize  Max 100
     * @param  array<string>  $resources  Resources to export
     */
    abstract protected function exportGroupDatabases(int $batchSize, array $resources): void;

    /**
     * Export Storage Group
     *
     * @param  int  $batchSize  Max 5
     * @param  array<string>  $resources  Resources to export
     */
    abstract protected function exportGroupStorage(int $batchSize, array $resources): void;

    /**
     * Export Functions Group
     *
     * @param  int  $batchSize  Max 100
     * @param  array<string>  $resources  Resources to export
     */
    abstract protected function exportGroupFunctions(int $batchSize, array $resources): void;
}
