<?php

namespace Utopia\Transfer;

use Utopia\Transfer\ResourceCache;
use Utopia\Transfer\Destination;
use Utopia\Transfer\Source;

class Transfer
{
    public const GROUP_GENERAL = 'General'; // for things that don't belong to any group
    public const GROUP_AUTH = 'Auth';
    public const GROUP_STORAGE = 'Storage';
    public const GROUP_FUNCTIONS = 'Functions';
    public const GROUP_DATABASES = 'Databases';
    public const GROUP_DOCUMENTS = 'Documents';
    
    public const STORAGE_MAX_CHUNK_SIZE = 1024 * 1024 * 5; // 5MB

    /**
     * @param Source $source
     * @param Destination $destination
     *
     * @return Transfer
     */
    public function __construct(Source $source, Destination $destination)
    {
        $this->source = $source;
        $this->destination = $destination;
        $this->resourceCache = new ResourceCache();

        $this->source->registerTransferCache($this->resourceCache);
        $this->destination->registerTransferCache($this->resourceCache);
        $this->destination->setSource($source);

        return $this;
    }

    /**
     * @var Source
     */
    protected Source $source;

    /**
     * @var Destination
     */
    protected Destination $destination;

    /**
     * @var string
     */
    protected string $currentResource;

    /**
     * A local cache of resources that were transferred.
     *
     * @var ResourceCache
     */
    protected ResourceCache $resourceCache;

    /**
     * @var array
     */
    protected array $options = [];

    /**
     * @var array
     */
    protected array $callbacks = [];

    /**
     * @var array
     */
    protected array $events = [];

    /**
     * Transfer Resources between adapters
     *
     * @param array $resources
     * @param callable $callback (Progress $progress)
     */
    public function run(array $resources, callable $callback): void
    {
        $this->destination->run($resources, function (Progress $progress) use ($callback) {
            //TODO: Rewrite to use ResourceCache to calculate this
            $this->currentResource = $progress->getResourceType();

            $callback($progress);
        }, $this->source);
    }

    /**
     * Get Resource Cache
     *
     * @return ResourceCache
     */
    public function getResourceCache(): ResourceCache
    {
        return $this->resourceCache;
    }

    /**
     *  Get Current Resource
     *
     * @return string
     **/

    public function getCurrentResource(): string
    {
        return $this->currentResource;
    }
}
