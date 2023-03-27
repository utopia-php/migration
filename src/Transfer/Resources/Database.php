<?php

namespace Utopia\Transfer\Resources;

use Utopia\Transfer\Resource;

const TYPE_STRING = 'string';
const TYPE_INTEGER = 'integer';
const TYPE_FLOAT = 'float';
const TYPE_BOOLEAN = 'boolean';
const TYPE_OBJECT = 'object';
const TYPE_ARRAY = 'array';
const TYPE_NULL = 'null';

class Database extends Resource
{
    const DB_RELATIONAL = 'relational';
    const DB_NON_RELATIONAL = 'non-relational';

    /**
     * @var list<Collection> $collections
     */
    private array $collections = [];

    protected string $name;
    protected string $id;
    protected string $type;

    public function __construct(string $name = '', string $id = '', string $type = self::DB_RELATIONAL)
    {
        $this->name = $name;
        $this->id = $id;
        $this->type = $type;
    }

    public function getName(): string
    {
        return 'Database';
    }

    public function getDBName(): string
    {
        return $this->name;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
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

    public function getCollections(): array
    {
        return $this->collections;
    }

    /**
     * @param list<Collection> $collections
     * @return self
     */
    public function setCollections(array $collections): self
    {
        $this->collections = $collections;

        return $this;
    }

    public function asArray(): array
    {
        return [
            'name' => $this->name,
            'id' => $this->id,
            'collections' => array_map(function ($collection) {
                return $collection->asArray();
            }, $this->collections)
        ];
    }
}