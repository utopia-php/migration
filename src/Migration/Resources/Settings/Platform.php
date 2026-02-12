<?php

namespace Utopia\Migration\Resources\Settings;

use Utopia\Migration\Resource;
use Utopia\Migration\Transfer;

class Platform extends Resource
{
    /**
     * @param string $id
     * @param string $type
     * @param string $name
     * @param string $key
     * @param string $store
     * @param string $hostname
     */
    public function __construct(
        string $id,
        private readonly string $type,
        private readonly string $name,
        private readonly string $key = '',
        private readonly string $store = '',
        private readonly string $hostname = '',
    ) {
        $this->id = $id;
    }

    /**
     * @param array<string, mixed> $array
     * @return self
     */
    public static function fromArray(array $array): self
    {
        return new self(
            $array['id'],
            $array['type'],
            $array['name'],
            $array['key'] ?? '',
            $array['store'] ?? '',
            $array['hostname'] ?? '',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'key' => $this->key,
            'store' => $this->store,
            'hostname' => $this->hostname,
        ];
    }

    public static function getName(): string
    {
        return Resource::TYPE_PLATFORM;
    }

    public function getGroup(): string
    {
        return Transfer::GROUP_SETTINGS;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getPlatformName(): string
    {
        return $this->name;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getStore(): string
    {
        return $this->store;
    }

    public function getHostname(): string
    {
        return $this->hostname;
    }
}
