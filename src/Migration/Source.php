<?php

namespace Utopia\Migration;

abstract class Source extends Target
{
    protected static int $defaultBatchSize = 100;

    /**
     * @var callable(array<Resource>): void $transferCallback
     */
    protected $transferCallback;

    /**
     * @var array<string, int>
     */
    public array $previousReport = [];

    public function getAuthBatchSize(): int
    {
        return static::$defaultBatchSize;
    }

    public function getDatabasesBatchSize(): int
    {
        return static::$defaultBatchSize;
    }

    public function getStorageBatchSize(): int
    {
        return static::$defaultBatchSize;
    }

    public function getFunctionsBatchSize(): int
    {
        return static::$defaultBatchSize;
    }

    public function getMessagingBatchSize(): int
    {
        return static::$defaultBatchSize;
    }

    public function getSitesBatchSize(): int
    {
        return static::$defaultBatchSize;
    }

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
                if (!in_array($resource->getName(), $resources)) {
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
     * @param array<string> $resources Resources to export
     */
    public function exportResources(array $resources): void
    {
        $groups = [];
        foreach ($resources as $resource) {
            $mapping = [
                Transfer::GROUP_AUTH => Transfer::GROUP_AUTH_RESOURCES,
                Transfer::GROUP_DATABASES => Transfer::GROUP_DATABASES_RESOURCES,
                Transfer::GROUP_STORAGE => Transfer::GROUP_STORAGE_RESOURCES,
                Transfer::GROUP_FUNCTIONS => Transfer::GROUP_FUNCTIONS_RESOURCES,
                Transfer::GROUP_MESSAGING => Transfer::GROUP_MESSAGING_RESOURCES,
                Transfer::GROUP_SITES => Transfer::GROUP_SITES_RESOURCES,
            ];

            foreach ($mapping as $group => $resources) {
                if (\in_array($resource, $resources, true)) {
                    $groups[$group][] = $resource;
                    break;
                }
            }
        }

        if (empty($groups)) {
            return;
        }

        foreach ($groups as $group => $resources) {
            switch ($group) {
                case Transfer::GROUP_AUTH:
                    $this->exportGroupAuth($this->getAuthBatchSize(), $resources);
                    break;
                case Transfer::GROUP_DATABASES:
                    $this->exportGroupDatabases($this->getDatabasesBatchSize(), $resources);
                    break;
                case Transfer::GROUP_STORAGE:
                    $this->exportGroupStorage($this->getStorageBatchSize(), $resources);
                    break;
                case Transfer::GROUP_FUNCTIONS:
                    $this->exportGroupFunctions($this->getFunctionsBatchSize(), $resources);
                    break;
                case Transfer::GROUP_MESSAGING:
                    $this->exportGroupMessaging($this->getMessagingBatchSize(), $resources);
                    break;
                case Transfer::GROUP_SITES:
                    $this->exportGroupSites($this->getSitesBatchSize(), $resources);
                    break;
            }
        }
    }

    /**
     * Export Auth Group
     *
     * @param int $batchSize
     * @param array<string> $resources Resources to export
     */
    abstract protected function exportGroupAuth(int $batchSize, array $resources): void;

    /**
     * Export Databases Group
     *
     * @param int $batchSize
     * @param array<string> $resources Resources to export
     */
    abstract protected function exportGroupDatabases(int $batchSize, array $resources): void;

    /**
     * Export Storage Group
     *
     * @param int $batchSize Max 5
     * @param array<string> $resources Resources to export
     */
    abstract protected function exportGroupStorage(int $batchSize, array $resources): void;

    /**
     * Export Functions Group
     *
     * @param int $batchSize
     * @param array<string> $resources Resources to export
     */
    abstract protected function exportGroupFunctions(int $batchSize, array $resources): void;

    /**
     * Export Messaging Group
     *
     * @param int $batchSize
     * @param array<string> $resources Resources to export
     */
    abstract protected function exportGroupMessaging(int $batchSize, array $resources): void;

    /**
     * Export Sites Group
     *
     * @param int $batchSize
     * @param array<string> $resources Resources to export
     */
    abstract protected function exportGroupSites(int $batchSize, array $resources): void;
}
