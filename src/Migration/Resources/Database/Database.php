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
    ) {
        $this->id = $id;
        // Do we need to $this->name = $name;
    }

    /**
     * @param array{
     *     id: string,
     *     name: string,
     * } $array
     */
    public static function fromArray(array $array): self
    {
        return new self(
            $array['id'],
            $array['name'],
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
}
