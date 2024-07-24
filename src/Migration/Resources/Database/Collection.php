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
        string $id,
        private readonly string $name,
        private readonly bool $documentSecurity = false,
        array $permissions = [],
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
     *     permissions: ?array<string>
     * } $array
     */
    public static function fromArray(array $array): self
    {
        return new self(
            Database::fromArray($array['database']),
            id: $array['id'],
            name: $array['name'],
            documentSecurity: $array['documentSecurity'],
            permissions: $array['permissions'] ?? []
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
