<?php

namespace Utopia\Migration\Resources\Database;

use Utopia\Migration\Resource;
use Utopia\Migration\Transfer;

class Collection extends Resource
{
    /**
     * @param Database $database
     * @param string $name
     * @param string $id
     * @param bool $documentSecurity
     * @param array<string> $permissions
     */
    public function __construct(
        private readonly Database $database,
        private readonly string $name,
        string $id,
        private readonly bool $documentSecurity = false,
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
            Database::fromArray($array['database']),
            $array['name'],
            $array['id'],
            $array['documentSecurity'] ?? false,
            $array['permissions'] ?? []
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_merge([
            'database' => $this->database,
            //'databaseId' => $this->database->getId(),
            'id' => $this->id,
            'name' => $this->name,
            'documentSecurity' => $this->documentSecurity,
            'permissions' => $this->permissions,
        ]);
    }

    public static function getName(): string
    {
        return Resource::TYPE_COLLECTION;
    }

    public function getGroup(): string
    {
        return Transfer::GROUP_DATABASES;
    }

    public function getDatabase(): Database
    {
        return $this->database;
    }

    public function getCollectionName(): string
    {
        return $this->name;
    }

    public function getDocumentSecurity(): bool
    {
        return $this->documentSecurity;
    }
}
