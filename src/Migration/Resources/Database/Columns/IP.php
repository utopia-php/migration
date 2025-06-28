<?php

namespace Utopia\Migration\Resources\Database\Columns;

use Utopia\Migration\Resources\Database\Column;
use Utopia\Migration\Resources\Database\Table;

class IP extends Text
{
    public function __construct(
        string  $key,
        Table   $table,
        bool    $required = false,
        ?string $default = null,
        bool    $array = false,
        int     $size = 39,
        string  $createdAt = '',
        string  $updatedAt = ''
    ) {
        parent::__construct(
            $key,
            $table,
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
        return Column::TYPE_IP;
    }
}
