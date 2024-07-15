<?php

namespace Utopia\Tests\Adapters;

use Utopia\Migration\Exception;
use Utopia\Migration\Resource;
use Utopia\Migration\Source;
use Utopia\Migration\Transfer;

class MockSource extends Source
{
    private array $mockResources = [];

    public function pushMockResource(Resource $resource): void
    {
        if (!key_exists($resource->getGroup(), $this->mockResources)) {
            $this->mockResources[$resource->getGroup()] = [];
        }

        if (!key_exists($resource->getName(), $this->mockResources[$resource->getGroup()])) {
            $this->mockResources[$resource->getGroup()][$resource->getName()] = [];
        }

        $this->mockResources[$resource->getGroup()][$resource->getName()][$resource->getId()] = $resource;
    }

    public function getMockResources(): array
    {
        return $this->mockResources;
    }

    public function getMockResourcesByType(string $group, string $type): array
    {
        return array_values($this->mockResources[$group][$type]) ?? [];
    }

    public function getMockResourceById(string $group, string $type, string $id): ?Resource
    {
        return $this->mockResources[$group][$type][$id] ?? null;
    }

    public function clearMockResources(): void
    {
        $this->mockResources = [];
    }

    private function handleResourceTransfer(string $group, string $type): void
    {
        if (in_array($type, Transfer::ROOT_RESOURCES) && !empty($this->rootResourceId)) {
            $this->callback([$this->getMockResourceById($group, $type, $this->rootResourceId)]);
            return;
        }

        $resources = $this->getMockResourcesByType($group, $type) ?? [];
        $this->callback($resources);
        return;
    }

    public static function getName(): string
    {
        return 'MockSource';
    }

    public static function getSupportedResources(): array
    {
        return [
            Resource::TYPE_ATTRIBUTE,
            Resource::TYPE_BUCKET,
            Resource::TYPE_COLLECTION,
            Resource::TYPE_DATABASE,
            Resource::TYPE_DOCUMENT,
            Resource::TYPE_FILE,
            Resource::TYPE_FUNCTION,
            Resource::TYPE_DEPLOYMENT,
            Resource::TYPE_HASH,
            Resource::TYPE_INDEX,
            Resource::TYPE_USER,
            Resource::TYPE_ENVIRONMENT_VARIABLE,
            Resource::TYPE_TEAM,
            Resource::TYPE_MEMBERSHIP,
        ];
    }

    public function report(array $resources = []): array
    {
        return [];
    }

    /**
     * Export Auth Group
     *
     * @param  int  $batchSize  Max 100
     * @param  string[]  $resources  Resources to export
     */
    protected function exportGroupAuth(int $batchSize, array $resources)
    {
        foreach (Transfer::GROUP_AUTH_RESOURCES as $resource) {
            if (!\in_array($resource, $resources)) {
                continue;
            }

            $this->handleResourceTransfer(Transfer::GROUP_AUTH, $resource);
        }
    }

    /**
     * Export Databases Group
     *
     * @param  int  $batchSize  Max 100
     * @param  string[]  $resources  Resources to export
     */
    protected function exportGroupDatabases(int $batchSize, array $resources)
    {
        foreach (Transfer::GROUP_DATABASES_RESOURCES as $resource) {
            if (!\in_array($resource, $resources)) {
                continue;
            }

            $this->handleResourceTransfer(Transfer::GROUP_DATABASES, $resource);
        }
    }

    /**
     * Export Storage Group
     *
     * @param  int  $batchSize  Max 5
     * @param  string[]  $resources  Resources to export
     */
    protected function exportGroupStorage(int $batchSize, array $resources)
    {
        foreach (Transfer::GROUP_STORAGE_RESOURCES as $resource) {
            if (!\in_array($resource, $resources)) {
                continue;
            }

            $this->handleResourceTransfer(Transfer::GROUP_STORAGE, $resource);
        }
    }

    /**
     * Export Functions Group
     *
     * @param  int  $batchSize  Max 100
     * @param  string[]  $resources  Resources to export
     */
    protected function exportGroupFunctions(int $batchSize, array $resources)
    {
        foreach (Transfer::GROUP_FUNCTIONS_RESOURCES as $resource) {
            if (!\in_array($resource, $resources)) {
                continue;
            }

            $this->handleResourceTransfer(Transfer::GROUP_FUNCTIONS, $resource);
        }
    }
}