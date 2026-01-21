<?php

namespace Utopia\Migration\Resources\Database\Attribute;

use Utopia\Migration\Resources\Database\Attribute;
use Utopia\Migration\Resources\Database\Collection;

class IP extends Text
{
    public function __construct(
        string  $key,
        Collection   $collection,
        bool    $required = false,
        ?string $default = null,
        bool    $array = false,
        int     $size = 39,
        string  $createdAt = '',
        string  $updatedAt = ''
    ) {
        parent::__construct(
            $key,
            $collection,
            required: $required,
            default: $default,
            array: $array,
            size: $size,
            format: 'ip',
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function getType(): string
    {
        return Attribute::TYPE_IP;
    }
}
