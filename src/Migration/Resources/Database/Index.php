<?php

namespace Utopia\Migration\Resources\Database;

use Utopia\Migration\Resource;
use Utopia\Migration\Transfer;

class Index extends Resource
{
    protected static string $timeStampFormatDb = 'Y-m-d H:i:s.v';
    public const TYPE_UNIQUE = 'unique';

    public const TYPE_FULLTEXT = 'fulltext';

    public const TYPE_KEY = 'key';

    protected string $createdAt;
    protected string $updatedAt;

    /**
     * @param string $id
     * @param string $key
     * @param Collection $collection
     * @param string $type
     * @param array<string> $attributes
     * @param array<int> $lengths
     * @param array<string> $orders
     * @param string|null $createdAt
     * @param string $updatedAt
     */
    public function __construct(
        string $id,
        private readonly string $key,
        private readonly Collection $collection,
        private readonly string $type = '',
        private readonly array $attributes = [],
        private readonly array $lengths = [],
        private readonly array $orders = [],
        ?string $createdAt = null,
        ?string $updatedAt = null,
    ) {
        $this->id = $id;
        $date = new \DateTime();
        $now = $date->format(self::$timeStampFormatDb);
        $this->$createdAt = $createdAt ?? $now;
        $this->$updatedAt = $updatedAt ?? $now;
    }

    /**
     * @param array{
     *     id: string,
     *     key: string,
     *     collection: array{
     *         database: array{
     *             id: string,
     *             name: string,
     *         },
     *         name: string,
     *         id: string,
     *         documentSecurity: bool,
     *         permissions: ?array<string>
     *     },
     *     type: string,
     *     attributes: array<string>,
     *     lengths: ?array<int>,
     *     orders: ?array<string>,
     *     createdAt: string,
     *     updatedAt: string,
     * } $array
     */
    public static function fromArray(array $array): self
    {
        return new self(
            $array['id'],
            $array['key'],
            Collection::fromArray($array['collection']),
            $array['type'],
            $array['attributes'],
            $array['lengths'] ?? [],
            $array['orders'] ?? [],
            createdAt: $array['createdAt'] ?? '',
            updatedAt: $array['updatedAt'] ?? '',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'key' => $this->key,
            'collection' => $this->collection,
            'type' => $this->type,
            'attributes' => $this->attributes,
            'lengths' => $this->lengths,
            'orders' => $this->orders,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
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
