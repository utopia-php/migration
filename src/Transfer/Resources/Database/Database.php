<?php

namespace Utopia\Transfer\Resources\Database;

use Utopia\Transfer\Resource;
use Utopia\Transfer\Transfer;

const TYPE_STRING = 'string';
const TYPE_INTEGER = 'integer';
const TYPE_FLOAT = 'float';
const TYPE_BOOLEAN = 'boolean';
const TYPE_OBJECT = 'object';
const TYPE_ARRAY = 'array';
const TYPE_NULL = 'null';

class Database extends Resource
{
    /**
     * @var list<Collection>
     */
    private array $collections = [];

    protected string $name;

    protected ?string $id;

    public function __construct(string $name = '', ?string $id = '')
    {
        $this->name = $name;
        $this->id = $id;
    }

    public static function getName(): string
    {
        return Resource::TYPE_DATABASE;
    }

    public function getGroup(): string
    {
        return Transfer::GROUP_DATABASES;
    }

    public function getDBName(): string
    {
        return $this->name;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return list<Collection>
     */
    public function getCollections(): array
    {
        return $this->collections;
    }

    /**
     * @param  list<Collection>  $collections
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
            }, $this->collections),
        ];
    }
}
