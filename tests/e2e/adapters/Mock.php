<?php

namespace Utopia\Tests;

use Utopia\Transfer\Source;
use Utopia\Transfer\Transfer;

class Mock extends Source
{
    public static function getName(): string
    {
        return 'MockSource';
    }

    public static function getSupportedResources(): array
    {
        return [
            Transfer::GROUP_AUTH,
            Transfer::GROUP_DATABASES,
            Transfer::GROUP_FUNCTIONS,
            Transfer::GROUP_SETTINGS,
            Transfer::GROUP_STORAGE,
        ];
    }

    public function report(array $groups = []): array
    {
        return [];
    }

    /**
     * Export Auth Group
     *
     * @param  array  $resources Resources to export
     * @return void
     */
    protected function exportGroupAuth(int $batchSize, array $resources)
    {

    }

    /**
     * Export Databases Group
     *
     * @param  int  $batchSize Max 100
     * @param  array  $resources Resources to export
     * @return void
     */
    protected function exportGroupDatabases(int $batchSize, array $resources)
    {

    }

    /**
     * Export Storage Group
     *
     * @param  int  $batchSize Max 5
     * @param  array  $resources Resources to export
     * @return void
     */
    protected function exportGroupStorage(int $batchSize, array $resources)
    {

    }

    /**
     * Export Functions Group
     *
     * @param  int  $batchSize Max 100
     * @param  array  $resources Resources to export
     * @return void
     */
    protected function exportGroupFunctions(int $batchSize, array $resources)
    {

    }
}
