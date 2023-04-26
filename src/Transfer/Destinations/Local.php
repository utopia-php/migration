<?php

namespace Utopia\Transfer\Destinations;

use Utopia\Transfer\Destination;
use Utopia\Transfer\Resources\Storage\File;
use Utopia\Transfer\Resources\Storage\FileData;
use Utopia\Transfer\Resource;
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
        }
    }

    /**
     * Get Name
     *
     * @return string
     */
    public function getName(): string
    {
        return 'Local';
    }

    /**
     * Get Supported Resources
     *
     * @return array
     */
    public function getSupportedResources(): array
    {
        return [
            Transfer::GROUP_AUTH,
            Transfer::GROUP_DATABASES,
            Transfer::GROUP_DOCUMENTS,
            Transfer::GROUP_STORAGE,
            Transfer::GROUP_FUNCTIONS
        ];
    }

    /**
     * Check if destination is valid
     *
     * @param array $resources
     * @return array
     */
    public function check(array $resources = []): array
    {
        $report = [
            'Users' => [],
            'Databases' => [],
            'Documents' => [],
            'Files' => [],
            'Functions' => []
        ];

        if (empty($resources)) {
            $resources = $this->getSupportedResources();
        }

        // Check we can write to the file
        if (!\is_writable($this->path . '/backup.json')) {
            $report['Databases'][] = 'Unable to write to file: ' . $this->path;
            throw new \Exception('Unable to write to file: ' . $this->path);
        }

        return $report;
    }

    public function syncFile(): void
    {
        \file_put_contents($this->path . '/backup.json', \json_encode($this->data, JSON_PRETTY_PRINT));
    }

    public function importResources(array $resources, callable $callback, string $group): void
    {
        foreach ($resources as $resource) {
            /** @var Resource $resource */
            switch ($resource->getName()) {
                case "FileData": {
                        /** @var FileData $resource */

                        // Handle folders
                        if (str_contains($resource->getFile()->getFileName(), '/')) {
                            $folders = explode('/', $resource->getFile()->getFileName());
                            $folderPath = $this->path . '/files';

                            foreach ($folders as $folder) {
                                $folderPath .= '/' . $folder;

                                if (!\file_exists($folderPath) && str_contains($folder, '.') === false) {
                                    mkdir($folderPath, 0777, true);
                                }
                            }
                        }

                        file_put_contents($this->path . '/files/' . $resource->getFile()->getFileName(), $resource->getData(), FILE_APPEND);
                        break;
                    }
                case "File": {
                        /** @var File $resource */
                        if (\file_exists($this->path . '/files/' . $resource->getFileName())) {
                            \unlink($this->path . '/files/' . $resource->getFileName());
                        }
                        break;
                    }
            }

            $this->data[$group][$resource->getName()][] = $resource->asArray();
            $resource->setStatus(Resource::STATUS_SUCCESS);
            $this->resourceCache->update($resource);
            $this->syncFile();
        }
    }
}
