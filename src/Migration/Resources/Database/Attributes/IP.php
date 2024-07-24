<?php

namespace Utopia\Migration\Resources\Database\Attributes;

use Utopia\Migration\Resources\Database\Attribute;

class IP extends Text
{
    public function getType(): string
    {
        return Attribute::TYPE_IP;
    }
}
