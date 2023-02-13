<?php

namespace Utopia\Transfer\Resources;

use Utopia\Transfer\Resource;

class Collection extends Resource
{
    /**
     * @var list<Attribute> $columns
     */

    private array $columns = [];

    /**
     * @var list<Index> $indexes
     */
    private array $indexes = [];

    public function __construct(protected string $name, protected string $id)
    {
    }

    public function getName(): string
    {
        return 'collection';
    }

    public function getCollectionName(): string
    {
        return $this->name;
    }

    public function setCollectionName(string $name): self
    {
        $this->name = $name;
        return $this;
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

    public function getAttributes(): array
    {
        return $this->columns;
    }

    /**
     * @param list<Attribute> $columns
     * @return self
     */
    public function setAttributes(array $columns): self
    {
        $this->columns = $columns;
        return $this;
    }

    public function getIndexes(): array
    {
        return $this->indexes;
    }

    /**
     * @param list<Index> $indexes
     * @return self
     */
    public function setIndexes(array $indexes): self
    {
        $this->indexes = $indexes;
        return $this;
    }

    public function asArray(): array
    {
        return [
            'name' => $this->name,
            'id' => $this->id,
            'columns' =>  array_map(function ($column) {
                return $column->asArray();
            }, $this->columns),
            'indexes' =>  array_map(function ($index) {
                return $index->asArray();
            }, $this->indexes),
        ];
    }
}