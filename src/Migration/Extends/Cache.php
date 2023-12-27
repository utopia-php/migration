<?php

namespace Utopia\Migration\Extends;

use Utopia\Cache\Cache as UtopiaCache;

/**
 * Add namespacing to Cache lib
 */
class Cache extends UtopiaCache
{
    private $namespace;

    public function __construct(string $namespace, \Utopia\Cache\Adapter $adapter)
    {
        $this->namespace = $namespace;
        parent::__construct($adapter);
    }

    /**
     * Load cached data. return false in no valid cache.
     *
     * @param  string  $key
     * @param  int  $ttl time in seconds
     * @return mixed
     */
    public function load(string $key, int $ttl): mixed
    {
        return parent::load($this->namespace . $key, $ttl);
    }

    /**
     * Save data to cache. Returns data on success of false on failure.
     *
     * @param  string  $key
     * @param  string|array  $data
     * @return bool|string|array
     */
    public function save(string $key, mixed $data): bool|string|array
    {
        return parent::save($this->namespace . $key, $data);
    }

    /**
     * Removes data from cache. Returns true on success of false on failure.
     *
     * @param  string  $key
     * @return bool
     */
    public function purge(string $key): bool
    {
        return parent::purge($this->namespace . $key);
    }
}
