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

    /**
     * @var array $permissions
     */
    protected array $permissions = [];

    /**
     * @var bool $documentSecurity
     */
    protected bool $documentSecurity = false;

    /**
     * @var string $name
     */
    protected string $name;

    /**
     * @var string $id
     */
    protected string $id;

    public function __construct(string $name = '', string $id = '', bool $documentSecurity = false, array $permissions = [])
    {
        $this->name = $name;
        $this->id = $id;
        $this->documentSecurity = $documentSecurity;
        $this->permissions = $permissions;
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

    public function getDocumentSecurity(): bool
    {
        return $this->documentSecurity;
    }

    public function setDocumentSecurity(bool $documentSecurity): self
    {
        $this->documentSecurity = $documentSecurity;
        return $this;
    }

    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function setPermissions(array $permissions): self
    {
        $this->permissions = $permissions;
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
            'permissions' => $this->permissions,
            'documentSecurity' => $this->documentSecurity,
        ];
    }
}