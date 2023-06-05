<?php

namespace Utopia\Transfer\Destinations;

use Utopia\Transfer\Destination;
use Utopia\Transfer\Resource;
use Utopia\Transfer\Resources\Functions\Deployment;
use Utopia\Transfer\Resources\Storage\File;
use Utopia\Transfer\Transfer;

/**
 * Local
 *
 * Used to export data to a local file system or for testing purposes.
 * Exports all data to a single JSON File.
 */
class Local extends Destination
{
    private array $data = [];

    protected string $path;

    public function __construct(string $path)
    {
        $this->path = $path;

        if (!\file_exists($this->path)) {
            mkdir($this->path, 0777, true);
            mkdir($this->path . '/files', 0777, true);
            mkdir($this->path . '/deployments', 0777, true);
        }
    }

    /**
     * Get Name
     */
    public static function getName(): string
    {
        return 'Local';
    }

    /**
     * Get Supported Resources
     */
    public function getSupportedResources(): array
    {
        return [
            Resource::TYPE_ATTRIBUTE,
            Resource::TYPE_BUCKET,
            Resource::TYPE_COLLECTION,
            Resource::TYPE_DATABASE,
            Resource::TYPE_DEPLOYMENT,
            Resource::TYPE_DOCUMENT,
            Resource::TYPE_ENVVAR,
            Resource::TYPE_FILE,
            Resource::TYPE_FUNCTION,
            Resource::TYPE_HASH,
            Resource::TYPE_INDEX,
            Resource::TYPE_TEAM,
            Resource::TYPE_MEMBERSHIP,
            Resource::TYPE_USER
        ];
    }

    public function report(array $resources = []): array
    {
        $report = [];

        if (empty($resources)) {
            $resources = $this->getSupportedResources();
        }

        // Check we can write to the file
        if (!\is_writable($this->path . '/backup.json')) {
            $report[Transfer::GROUP_DATABASES][] = 'Unable to write to file: ' . $this->path;
            throw new \Exception('Unable to write to file: ' . $this->path);
        }

        return $report;
    }

    public function sync(): void
    {
        $jsonEncodedData = \json_encode($this->data, JSON_PRETTY_PRINT);

        if ($jsonEncodedData === false) {
            throw new \Exception('Unable to encode data to JSON, Are you accidentally encoding binary data?');
        }

        \file_put_contents($this->path . '/backup.json', \json_encode($this->data, JSON_PRETTY_PRINT));
    }

    public function importResources(array $resources, callable $callback): void
    {
        foreach ($resources as $resource) {
            /** @var resource $resource */
            switch ($resource->getName()) {
                case 'Deployment':
                    /** @var Deployment $resource */
                    if ($resource->getStart() === 0) {
                        $this->data[$resource->getGroup()][$resource->getName()][$resource->getInternalId()] = $resource->asArray();
                    }

                    file_put_contents($this->path . 'deployments/' . $resource->getId() . '.tar.gz', $resource->getData(), FILE_APPEND);
                    break;
                case 'File':
                    /** @var File $resource */

                    // Handle folders
                    if (str_contains($resource->getFileName(), '/')) {
                        $folders = explode('/', $resource->getFileName());
                        $folderPath = $this->path . '/files';

                        foreach ($folders as $folder) {
                            $folderPath .= '/' . $folder;

                            if (!\file_exists($folderPath) && str_contains($folder, '.') === false) {
                                mkdir($folderPath, 0777, true);
                            }
                        }
                    }

                    if ($resource->getStart() === 0 && \file_exists($this->path . '/files/' . $resource->getFileName())) {
                        unlink($this->path . '/files/' . $resource->getFileName());
                    }

                    file_put_contents($this->path . '/files/' . $resource->getFileName(), $resource->getData(), FILE_APPEND);
                    break;
            }

            $resource->setStatus(Resource::STATUS_SUCCESS);
            $this->cache->update($resource);
            $this->sync();
        }

        $callback($resources);
    }
}
