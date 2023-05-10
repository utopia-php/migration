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
     *
     * @param array $resources
     * @param callable $callback
     */
    public function run(array $resources, callable $callback): void
    {
        $this->transferCallback = function (array $resources) use ($callback) {
            $this->resourceCache->addAll($resources);
            $callback($resources);
        };

        $this->exportResources($resources, 100);
    }

    /**
     * Export Resources
     *
     * @param string[] $resources
     * @param int $batchSize
     *
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
                    $this->exportAuthGroup($batchSize, $resources);
                    break;
                case Transfer::GROUP_DATABASES:
                    $this->exportDatabasesGroup($batchSize, $resources);
                    break;
                case Transfer::GROUP_STORAGE:
                    $this->exportStorageGroup($batchSize, $resources);
                    break;
                case Transfer::GROUP_FUNCTIONS:
                    $this->exportFunctionsGroup($batchSize, $resources);
                    break;
            }
        }
    }

    /**
     * Export Auth Group
     *
     * @param int $batchSize
     * @param array $resources Resources to export
     *
     * @return void
     */
    abstract public function exportAuthGroup(int $batchSize, array $resources);

    /**
     * Export Databases Group
     *
     * @param int $batchSize Max 100
     * @param array $resources Resources to export
     *
     * @return void
     */
    abstract public function exportDatabasesGroup(int $batchSize, array $resources);

    /**
     * Export Storage Group
     *
     * @param int $batchSize Max 5
     * @param array $resources Resources to export
     *
     * @return void
     */
    abstract public function exportStorageGroup(int $batchSize, array $resources);

    /**
     * Export Functions Group
     *
     * @param int $batchSize Max 100
     * @param array $resources Resources to export
     *
     * @return void
     */
    abstract public function exportFunctionsGroup(int $batchSize, array $resources);
}
