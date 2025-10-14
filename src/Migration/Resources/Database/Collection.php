<?php

namespace Utopia\Migration\Resources\Database;

use Utopia\Migration\Resource;

class Collection extends Table
{
    public static function getName(): string
    {
        return Resource::TYPE_COLLECTION;
    }

    /**
     * @param array{
     *     database: array{
     *        id: string,
     *        name: string,
     *     },
     *     name: string,
     *     id: string,
     *     documentSecurity?: bool,
     *     rowSecurity?: bool,
     *     permissions: ?array<string>,
     *     createdAt: string,
     *     updatedAt: string,
     *     enabled: bool
     * } $array
    */
    public static function fromArray(array $array): self
    {
        return new self(
            DocumentsDB::fromArray($array['database']),
            name: $array['name'],
            id: $array['id'],
            rowSecurity: $array['rowSecurity'] ?? $array['documentSecurity'],
            permissions: $array['permissions'] ?? [],
            createdAt: $array['createdAt'] ?? '',
            updatedAt: $array['updatedAt'] ?? '',
            enabled: $array['enabled'] ?? true,
        );
    }
}
