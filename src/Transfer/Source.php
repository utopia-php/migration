<?php

namespace Utopia\Transfer;

abstract class Source extends Target
{
    protected $transferCallback;

    public function callback(array $resources): void
    {
        ($this->transferCallback)($resources);
    }

    /**
     * Transfer Resources into destination
     */
    public function run(array $resources, callable $callback): void
    {
        $this->transferCallback = function (array $returnedResources) use ($callback, $resources) {
            $prunedResurces = [];
            foreach ($returnedResources as $resource) {
                /** @var Resource $resource */
                if (! in_array($resource->getName(), $resources)) {
                    $resource->setStatus(Resource::STATUS_SKIPPED);
                } else {
                    $prunedResurces[] = $resource;
                }
            }

            $this->cache->addAll($prunedResurces);
            $callback($returnedResources);
        };

        $this->exportResources($resources, 100);
    }

    /**
     * Export Resources
     *
     * @param  string[]  $resources
     * @return void
     */
    public function exportResources(array $resources, int $batchSize)
    {
        // Convert Resources back into their relevant groups

        $groups = [];
        foreach ($resources as $resource) {
            if (in_array($resource, Transfer::GROUP_AUTH_RESOURCES)) {
                $groups[Transfer::GROUP_AUTH][] = $resource;
            } elseif (in_array($resource, Transfer::GROUP_DATABASES_RESOURCES)) {
                $groups[Transfer::GROUP_DATABASES][] = $resource;
            } elseif (in_array($resource, Transfer::GROUP_STORAGE_RESOURCES)) {
                $groups[Transfer::GROUP_STORAGE][] = $resource;
            } elseif (in_array($resource, Transfer::GROUP_FUNCTIONS_RESOURCES)) {
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

    /**
     * Export Auth Group
     *
     * @param  array  $resources Resources to export
     * @return void
     */
    abstract protected function exportGroupAuth(int $batchSize, array $resources);

    /**
     * Export Databases Group
     *
     * @param  int  $batchSize Max 100
     * @param  array  $resources Resources to export
     * @return void
     */
    abstract protected function exportGroupDatabases(int $batchSize, array $resources);

    /**
     * Export Storage Group
     *
     * @param  int  $batchSize Max 5
     * @param  array  $resources Resources to export
     * @return void
     */
    abstract protected function exportGroupStorage(int $batchSize, array $resources);

    /**
     * Export Functions Group
     *
     * @param  int  $batchSize Max 100
     * @param  array  $resources Resources to export
     * @return void
     */
    abstract protected function exportGroupFunctions(int $batchSize, array $resources);
}
