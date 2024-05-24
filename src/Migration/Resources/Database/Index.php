<?php

namespace Utopia\Migration\Resources\Database;

use Utopia\Migration\Resource;
use Utopia\Migration\Transfer;

class Index extends Resource
{
    public const string TYPE_UNIQUE = 'unique';

    public const string TYPE_FULLTEXT = 'fulltext';

    public const string TYPE_KEY = 'key';

    /**
     * @param string $id
     * @param string $key
     * @param Collection $collection
     * @param string $type
     * @param array<string> $attributes
     * @param array<int> $lengths
     * @param array<string> $orders
     */
    public function __construct(
        string $id,
        private readonly string $key,
        private readonly Collection $collection,
        private readonly string $type = '',
        private readonly array $attributes = [],
        private readonly array $lengths = [],
        private readonly array $orders = []
    ) {
        $this->id = $id;
    }

    /**
     * @param array<string, mixed> $array
     */
    public static function fromArray(array $array): self
    {
        return new self(
            $array['id'],
            $array['key'],
            Collection::fromArray($array['collection']),
            $array['type'] ?? '',
            $array['attributes'],
            $array['lengths'] ?? [],
            $array['orders'] ?? []
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'key' => $this->key,
            'collection' => $this->collection,
            'type' => $this->type,
            'attributes' => $this->attributes,
            'lengths' => $this->lengths,
            'orders' => $this->orders,
        ];
    }

    public static function getName(): string
    {
        return Resource::TYPE_INDEX;
    }

    public function getGroup(): string
    {
        return Transfer::GROUP_DATABASES;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getCollection(): Collection
    {
        return $this->collection;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array<string>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @return array<string>
     */
    public function getOrders(): array
    {
        return $this->orders;
    }
}
