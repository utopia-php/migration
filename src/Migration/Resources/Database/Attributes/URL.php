<?php

namespace Utopia\Migration\Resources\Database\Attributes;

use Utopia\Migration\Resources\Database\Attribute;
use Utopia\Migration\Resources\Database\Collection;

class URL extends Text
{
    public function __construct(
        string $key,
        Collection $collection,
        bool $required = false,
        ?string $default = null,
        bool $array = false,
        int $size = 2000,
        protected string $createdAt = '',
        protected string $updatedAt = '',
    ) {
        parent::__construct(
            $key,
            $collection,
            required: $required,
            default: $default,
            array: $array,
            size: $size,
            format: 'url',
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );
    }

    public function getType(): string
    {
        return Attribute::TYPE_URL;
    }
}
