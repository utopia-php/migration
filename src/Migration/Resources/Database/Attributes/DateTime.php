<?php

namespace Utopia\Migration\Resources\Database\Attributes;

use Utopia\Migration\Resources\Database\Attribute;
use Utopia\Migration\Resources\Database\Collection;

class DateTime extends Attribute
{
    public function __construct(
        string $key,
        Collection $collection,
        bool $required = false,
        ?string $default = null,
        bool $array = false,
        string $createdAt = '',
        string $updatedAt = ''
    ) {
        parent::__construct(
            $key,
            $collection,
            required: $required,
            default: $default,
            array: $array,
            filters: ['datetime'],
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );
    }

    public function getType(): string
    {
        return Attribute::TYPE_DATETIME;
    }

    /**
     * @param array{
     *     key: string,
     *     collection: array{
     *         database: array{
     *             id: string,
     *             name: string,
     *         },
     *         name: string,
     *         id: string,
     *         documentSecurity: bool,
     *         permissions: ?array<string>
     *     },
     *     required: bool,
     *     array: bool,
     *     default: ?string,
     *     createdAt: string,
     *     updatedAt: string,
     * } $array
     * @return self
     */
    public static function fromArray(array $array): self
    {
        return new self(
            $array['key'],
            Collection::fromArray($array['collection']),
            required: $array['required'],
            default: $array['default'],
            array: $array['array'],
            createdAt: $array['createdAt'] ?? '',
            updatedAt: $array['updatedAt'] ?? '',
        );
    }
}
