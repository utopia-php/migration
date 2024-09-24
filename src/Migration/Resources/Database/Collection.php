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
     * @param string $createdAt
     * @param string $updatedAt
     */
    public function __construct(
        private readonly Database $database,
        private readonly string $name,
        string $id,
        private readonly bool $documentSecurity = false,
        array $permissions = [],
        protected string $createdAt = '',
        protected string $updatedAt = '',
    ) {
        $this->id = $id;
        $this->permissions = $permissions;
    }

    /**
     * @param array{
     *     database: array{
     *        id: string,
     *        name: string,
 *         },
     *     name: string,
     *     id: string,
     *     documentSecurity: bool,
     *     permissions: ?array<string>,
     *     createdAt: string,
     *     updatedAt: string
     * } $array
     */
    public static function fromArray(array $array): self
    {
        return new self(
            Database::fromArray($array['database']),
            name: $array['name'],
            id: $array['id'],
            documentSecurity: $array['documentSecurity'],
            permissions: $array['permissions'] ?? [],
            createdAt: $array['createdAt'] ?? '',
            updatedAt: $array['updatedAt'] ?? '',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_merge([
            'database' => $this->database,
            'id' => $this->id,
            'name' => $this->name,
            'documentSecurity' => $this->documentSecurity,
            'permissions' => $this->permissions,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
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
