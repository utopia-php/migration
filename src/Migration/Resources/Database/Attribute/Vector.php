<?php

namespace Utopia\Migration\Resources\Database\Attribute;

use Utopia\Migration\Resources\Database\Attribute;
use Utopia\Migration\Resources\Database\Collection;

class Vector extends Attribute
{
    public function __construct(
        string $key,
        Collection $collection,
        bool $required = false,
        int  $size,
        ?array $default = null,
        string $createdAt = '',
        string $updatedAt = ''
    ) {
        parent::__construct(
            $key,
            $collection,
            size: $size,
            required: $required,
            default: $default,
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );
    }

    /**
     * @param array{
     *     key: string,
     *     collection?: array{
     *         database: array{
     *             id: string,
     *             name: string,
     *         },
     *         name: string,
     *         id: string,
     *         documentSecurity: bool,
     *         permissions: ?array<string>
     *     },
     *     table?: array{
     *         database: array{
     *             id: string,
     *             name: string,
     *         },
     *         name: string,
     *         id: string,
     *         rowSecurity: bool,
     *         permissions: ?array<string>
     *     },
     *     size: int,
     *     required: bool,
     *     default: ?array<mixed>,
     *     createdAt: string,
     *     updatedAt: string,
     * } $array
     * @return self
     */
    public static function fromArray(array $array): self
    {
        return new self(
            $array['key'],
            Collection::fromArray($array['table'] ?? $array['collection']),
            required: $array['required'],
            size:$array['size'],
            default: $array['default'],
            createdAt: $array['createdAt'] ?? '',
            updatedAt: $array['updatedAt'] ?? '',
        );
    }

    public function getType(): string
    {
        return Attribute::TYPE_VECTOR;
    }
}
