<?php
namespace Utopia\Tests\E2E\Adapters;

use Utopia\Transfer\Destination;
use Utopia\Transfer\Resource;

class Mock extends Destination
{
    public array $data = [];

    public static function getName(): string
    {
        return 'Mock';
    }

    static function getSupportedResources(): array
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
            Resource::TYPE_ENVVAR,
            Resource::TYPE_TEAM,
            Resource::TYPE_MEMBERSHIP,
        ];
    }

    public function import(array $resources, callable $callback): void
    {
        foreach ($resources as $resource) {
            /** @var Resource $resource */
            switch ($resource->getName()) {
                case 'Deployment':
                    /** @var Deployment $resource */
                    if ($resource->getStart() === 0) {
                        $this->data[$resource->getGroup()][$resource->getName()][$resource->getInternalId()] = $resource->asArray();
                    }

                    // file_put_contents($this->path . 'deployments/' . $resource->getId() . '.tar.gz', $resource->getData(), FILE_APPEND);
                    break;
                case 'File':
                    //TODO: Handle Files and Deployments
                    // /** @var File $resource */

                    // // Handle folders
                    // if (str_contains($resource->getFileName(), '/')) {
                    //     $folders = explode('/', $resource->getFileName());
                    //     $folderPath = $this->path . '/files';

                    //     foreach ($folders as $folder) {
                    //         $folderPath .= '/' . $folder;

                    //         if (!\file_exists($folderPath) && str_contains($folder, '.') === false) {
                    //             mkdir($folderPath, 0777, true);
                    //         }
                    //     }
                    // }

                    // if ($resource->getStart() === 0 && \file_exists($this->path . '/files/' . $resource->getFileName())) {
                    //     unlink($this->path . '/files/' . $resource->getFileName());
                    // }

                    // file_put_contents($this->path . '/files/' . $resource->getFileName(), $resource->getData(), FILE_APPEND);
                    // break;
            }

            $resource->setStatus(Resource::STATUS_SUCCESS);
            $this->cache->update($resource);
        }

        $callback($resources);
    }

    public function report(array $groups = []): array
    {
        return [];
    }
}
