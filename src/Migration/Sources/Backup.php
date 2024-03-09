<?php

namespace Utopia\Migration\Sources;

use Utopia\Database\Database;
use Utopia\Migration\Source;
use Utopia\Storage\Device;


class Backup extends Source
{

    protected string $path;

    protected Database $database;

    protected Device $storage;

    public function __construct(string $path, Database $database, Device $storage)
    {
        $this->path = $path;
        $this->database = $database;
        $this->storage = $storage;
    }

    public static function getName(): string
    {
        return 'Backup';
    }

    /**
     * Export Auth Group
     *
     * @param  int  $batchSize  Max 100
     * @param  string[]  $resources  Resources to export
     */
    protected function exportGroupAuth(int $batchSize, array $resources)
    {

    }

    /**
     * Export Databases Group
     *
     * @param  int  $batchSize  Max 100
     * @param  string[]  $resources  Resources to export
     */
    protected function exportGroupDatabases(int $batchSize, array $resources)
    {
    }

    /**
     * Export Storage Group
     *
     * @param  int  $batchSize  Max 5
     * @param  string[]  $resources  Resources to export
     */
    protected function exportGroupStorage(int $batchSize, array $resources)
    {
    }

    /**
     * Export Functions Group
     *
     * @param  int  $batchSize  Max 100
     * @param  string[]  $resources  Resources to export
     */
    protected function exportGroupFunctions(int $batchSize, array $resources)
    {
    }

    public static function getSupportedResources(): array
    {
    }


    public function report(array $resources = []): array
    {
    }
}
