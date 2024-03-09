<?php

namespace Utopia\Migration\Destinations;

use Utopia\Database\Database;
use Utopia\Database\DateTime;
use Utopia\Database\Document;
use Utopia\Database\Exception;
use Utopia\Database\Exception\Authorization;
use Utopia\Database\Exception\Conflict;
use Utopia\Database\Exception\Structure;
use Utopia\Migration\Destination;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Functions\Deployment;
use Utopia\Migration\Resources\Storage\File;
use Utopia\Migration\Transfer;
use Utopia\Storage\Device;

/**
 * Local
 *
 * Used to export data to a local file system or for testing purposes.
 * Exports all data to a single JSON File.
 */
class Backup extends Destination
{
    private array $data = [];

    protected string $path;

    protected Database $database;

    protected Device $storage;

    protected Document $backup;

    public function __construct(Document $backup, string $path, Database $database, Device $storage)
    {
        $this->path = $path;
        $this->database = $database;
        $this->storage = $storage;
        $this->backup = $backup;

        if (! \file_exists($this->path)) {
            mkdir($this->path, 0777, true);
            mkdir($this->path.'/files', 0777, true);
            mkdir($this->path.'/deployments', 0777, true);
        }

        var_dump('__construct');
        var_dump($this->backup);
    }

    public static function getName(): string
    {
        return 'Backup';
    }

    public static function getSupportedResources(): array
    {
        return [
            Resource::TYPE_ATTRIBUTE,
            Resource::TYPE_BUCKET,
            Resource::TYPE_COLLECTION,
            Resource::TYPE_DATABASE,
            Resource::TYPE_DEPLOYMENT,
            Resource::TYPE_DOCUMENT,
            Resource::TYPE_ENVIRONMENT_VARIABLE,
            Resource::TYPE_FILE,
            Resource::TYPE_FUNCTION,
            Resource::TYPE_HASH,
            Resource::TYPE_INDEX,
            Resource::TYPE_TEAM,
            Resource::TYPE_MEMBERSHIP,
            Resource::TYPE_USER,
        ];
    }

    public function report(array $resources = []): array
    {
        $report = [];

        if (empty($resources)) {
            $resources = $this->getSupportedResources();
        }

        // Check we can write to the file
        if (! \is_writable($this->path.'/backup.json')) {
            $report[Transfer::GROUP_DATABASES][] = 'Unable to write to file: '.$this->path;
            throw new \Exception('Unable to write to file: '.$this->path);
        }

        return $report;
    }

    /**
     * @throws \Exception
     */
    private function sync(): void
    {
        $jsonEncodedData = \json_encode($this->data, JSON_PRETTY_PRINT);

        if ($jsonEncodedData === false) {
            throw new \Exception('Unable to encode data to JSON, Are you accidentally encoding binary data?');
        }

        \file_put_contents($this->path.'/backup.json', \json_encode($this->data, JSON_PRETTY_PRINT));

        var_dump('completed');
        var_dump($this->backup);
//        $this->backup
//            ->setAttribute('finishedAt', DateTime::now())
//            ->setAttribute('status', 'completed')
//        ;

    }

    /**
     * @throws Authorization
     * @throws Structure
     * @throws Conflict
     * @throws Exception
     */
    protected function import(array $resources, callable $callback): void
    {

        var_dump('import');
        var_dump($this->backup);
//        $this->backup
//            ->setAttribute('startedAt', DateTime::now())
//            ->setAttribute('status', 'started')
//        ;

        $this->database->updateDocument('backups', $this->backup->getId() ,$this->backup);

        foreach ($resources as $resource) {
            /** @var resource $resource */
            switch ($resource->getName()) {
                case Resource::TYPE_DEPLOYMENT:
                    /** @var Deployment $resource */
                    if ($resource->getStart() === 0) {
                        $this->data[$resource->getGroup()][$resource->getName()][] = $resource->asArray();
                    }

                    file_put_contents($this->path.'deployments/'.$resource->getId().'.tar.gz', $resource->getData(), FILE_APPEND);
                    $resource->setData('');
                    break;
                case Resource::TYPE_FILE:
                    /** @var File $resource */

                    // Handle folders
                    if (str_contains($resource->getFileName(), '/')) {
                        $folders = explode('/', $resource->getFileName());
                        $folderPath = $this->path.'/files';

                        foreach ($folders as $folder) {
                            $folderPath .= '/'.$folder;

                            if (! \file_exists($folderPath) && str_contains($folder, '.') === false) {
                                mkdir($folderPath, 0777, true);
                            }
                        }
                    }

                    if ($resource->getStart() === 0 && \file_exists($this->path.'/files/'.$resource->getFileName())) {
                        unlink($this->path.'/files/'.$resource->getFileName());
                    }

                    file_put_contents($this->path.'/files/'.$resource->getFileName(), $resource->getData(), FILE_APPEND);
                    $resource->setData('');
                    break;
                default:
                    $this->data[$resource->getGroup()][$resource->getName()][] = $resource->asArray();
                    break;
            }

            $resource->setStatus(Resource::STATUS_SUCCESS);
            $this->cache->update($resource);
            $this->sync();
        }

        $callback($resources);
    }
}
