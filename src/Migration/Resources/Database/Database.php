<?php

namespace Utopia\Migration\Resources\Database;

use Utopia\Migration\Resource;
use Utopia\Migration\Transfer;

const TYPE_STRING = 'string';
const TYPE_INTEGER = 'integer';
const TYPE_FLOAT = 'float';
const TYPE_BOOLEAN = 'boolean';
const TYPE_OBJECT = 'object';
const TYPE_ARRAY = 'array';
const TYPE_NULL = 'null';

class Database extends Resource
{
    public function __construct(
        string $id = '',
        private readonly string $name = '',
        protected string $createdAt = '',
        protected string $updatedAt = '',
        protected bool $enabled = true,
        protected string $originalId = '',
        protected string $type = '',
    ) {
        $this->id = $id;
    }

    /**
     * @param array{
     *     id: string,
     *     name: string,
     *     createdAt: string,
     *     updatedAt: string,
     *     enabled: bool,
     *     originalId: string|null,
     * } $array
     */
    public static function fromArray(array $array): self
    {
        return new self(
            $array['id'],
            $array['name'],
            createdAt: $array['createdAt'] ?? '',
            updatedAt: $array['updatedAt'] ?? '',
            enabled: $array['enabled'] ?? true,
            originalId: $array['originalId'] ?? '',
            type: $array['type'] ?? '',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'enabled' => $this->enabled,
            'type' => $this->type,
        ];
    }

    public static function getName(): string
    {
        return Resource::TYPE_DATABASE;
    }

    public function getGroup(): string
    {
        return Transfer::GROUP_DATABASES;
    }

    public function getDatabaseName(): string
    {
        return $this->name;
    }

    public function getEnabled(): bool
    {
        return $this->enabled;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
