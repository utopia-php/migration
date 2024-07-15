<?php

namespace Utopia\Migration\Resources\Database;

use Utopia\Migration\Resource;
use Utopia\Migration\Transfer;

class Collection extends Resource
{
    /**
     * @var list<Attribute>
     */
    private array $columns = [];

    /**
     * @var list<Index>
     */
    private array $indexes = [];

    private Database $database;

    protected bool $documentSecurity = false;

    protected string $name;

    public function __construct(Database $database, string $name, string $id, bool $documentSecurity = false, array $permissions = [])
    {
        $this->database = $database;
        $this->name = $name;
        $this->id = $id;
        $this->documentSecurity = $documentSecurity;
        $this->permissions = $permissions;
    }

    public static function getName(): string
    {
        return Resource::TYPE_COLLECTION;
    }

    public function getGroup(): string
    {
        return Transfer::GROUP_DATABASES;
    }

    public function isRoot(): bool
    {
        return false;
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

    public function getCollectionName(): string
    {
        return $this->name;
    }

    public function setCollectionName(string $name): self
    {
        $this->name = $name;

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

    public function asArray(): array
    {
        return [
            'name' => $this->name,
            'id' => $this->id,
            'permissions' => $this->permissions,
            'documentSecurity' => $this->documentSecurity,
        ];
    }
}
