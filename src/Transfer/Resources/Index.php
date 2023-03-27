<?php

namespace Utopia\Transfer\Resources;

use Utopia\Transfer\Resource;

class Index extends Resource {

    protected string $key;
    protected string $type;
    protected array $attributes;
    protected array $orders;

    const TYPE_UNIQUE = 'unique';
    const TYPE_FULLTEXT = 'fulltext';
    const TYPE_KEY = 'key';

    /**
     * @param string $key
     * @param string $type
     * @param list<Attribute> $attributes
     * @param array $orders
     */

    public function __construct(string $id, string $key, string $type = '', array $attributes = [], array $orders = [])
    {
        $this->id = $id;
        $this->key = $key;
        $this->type = $type;
        $this->attributes = $attributes;
        $this->orders = $orders;
    }

    public function getName(): string
    {
        return 'index';
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