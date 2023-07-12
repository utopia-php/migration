<?php

namespace Utopia\Transfer\Resources\Database;

use Utopia\Transfer\Resource;
use Utopia\Transfer\Transfer;

class Document extends Resource
{
    protected ?string $id;

    protected Database $database;

    protected Collection $collection;

    protected array $data;

    protected array $permissions;

    public function __construct(string $id, Database $database, Collection $collection, array $data = [], array $permissions = [])
    {
        $this->id = $id;
        $this->database = $database;
        $this->collection = $collection;
        $this->data = $data;
        $this->permissions = $permissions;
    }

    public static function getName(): string
    {
        return Resource::TYPE_DOCUMENT;
    }

    public function getGroup(): string
    {
        return Transfer::GROUP_DATABASES;
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

    public function getDatabase(): Database
    {
        return $this->database;
    }

    public function setDatabase(Database $database): self
    {
        $this->database = $database;

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

    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Set Data
     *
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function getPermissions(): array
    {
        return $this->permissions;
    }

    /**
     * Set Permissions
     *
     * @param  array<string>  $permissions
     */
    public function setPermissions(array $permissions): self
    {
        $this->permissions = $permissions;

        return $this;
    }

    public function asArray(): array
    {
        return [
            'id' => $this->id,
            'database' => $this->database,
            'collection' => $this->collection,
            'attributes' => $this->data,
            'permissions' => $this->permissions,
        ];
    }
}
