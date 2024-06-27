<?php

namespace Utopia\Migration\Resources\Database;

use Utopia\Migration\Resource;
use Utopia\Migration\Transfer;

class Document extends Resource
{
    /**
     * @param string $id
     * @param Collection $collection
     * @param array<string, mixed> $data
     * @param array<string> $permissions
     */
    public function __construct(
        string $id,
        private readonly Collection $collection,
        private readonly array $data = [],
        array $permissions = []
    ) {
        $this->id = $id;
        $this->permissions = $permissions;
    }

    /**
     * @param array<string, mixed> $array
     */
    public static function fromArray(array $array): self
    {
        return new self(
            $array['id'],
            Collection::fromArray($array['collection']),
            $array['attributes'],
            $array['permissions'] ?? []
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'collection' => $this->collection,
            'attributes' => $this->data,
            'permissions' => $this->permissions,
        ];
    }

    public static function getName(): string
    {
        return Resource::TYPE_DOCUMENT;
    }

    public function getGroup(): string
    {
        return Transfer::GROUP_DATABASES;
    }

    public function getCollection(): Collection
    {
        return $this->collection;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }
}
