<?php

namespace Utopia\Transfer\Resources\Database;

use Utopia\Transfer\Resource;
use Utopia\Transfer\Transfer;

class Index extends Resource
{
    protected string $key;
    protected string $type;
    protected array $attributes;
    protected array $orders;
    protected Collection $collection;

    public const TYPE_UNIQUE = 'unique';
    public const TYPE_FULLTEXT = 'fulltext';
    public const TYPE_KEY = 'key';

    /**
     * @param string $key
     * @param string $type
     * @param Collection $collection
     * @param list<Attribute> $attributes
     * @param array $orders
     */

    public function __construct(string $id, string $key, Collection $collection, string $type = '', array $attributes = [], array $orders = [])
    {
        $this->id = $id;
        $this->key = $key;
        $this->type = $type;
        $this->attributes = $attributes;
        $this->orders = $orders;
        $this->collection = $collection;
    }

    static function getName(): string
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

    public function setKey(string $key): self
    {
        $this->key = $key;
        return $this;
    }

    public function getCollection(): Collection
    {
        return $this->collection;
    }

    public function setCollection(Collection $collection): self
    {
        $this->collection = $collection;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @param list<Attribute> $attributes
     * @return self
     */
    public function setAttributes(array $attributes): self
    {
        $this->attributes = $attributes;
        return $this;
    }

    public function getOrders(): array
    {
        return $this->orders;
    }

    public function setOrders(array $orders): self
    {
        $this->orders = $orders;
        return $this;
    }

    public function asArray(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type,
            'attributes' => $this->attributes,
            'orders' => $this->orders,
        ];
    }
}
