<?php

namespace Utopia\Tests\Adapters;

use Utopia\Migration\Resource;
use Utopia\Migration\Source;

class MockSource extends Source
{
    private array $mockResources = [];

    public function pushMockResource(Resource $resource): void
    {
        $this->mockResources[] = $resource;
    }

    public function getMockResources(): array
    {
        return $this->mockResources;
    }

    public function clearMockResources(): void
    {
        $this->mockResources = [];
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

    public function exportResources(array $resources, int $batchSize)
    {
        
    }

    /**
     * Export Auth Group
     *
     * @param  int  $batchSize  Max 100
     * @param  string[]  $resources  Resources to export
     */
    protected function exportGroupAuth(int $batchSize, array $resources)
    {
        return;
    }

    /**
     * Export Databases Group
     *
     * @param  int  $batchSize  Max 100
     * @param  string[]  $resources  Resources to export
     */
    protected function exportGroupDatabases(int $batchSize, array $resources)
    {
        return;
    }

    /**
     * Export Storage Group
     *
     * @param  int  $batchSize  Max 5
     * @param  string[]  $resources  Resources to export
     */
    protected function exportGroupStorage(int $batchSize, array $resources)
    {
        return;
    }

    /**
     * Export Functions Group
     *
     * @param  int  $batchSize  Max 100
     * @param  string[]  $resources  Resources to export
     */
    protected function exportGroupFunctions(int $batchSize, array $resources)
    {
        return;
    }
}