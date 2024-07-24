<?php

namespace Utopia\Migration\Resources\Database\Attributes;

use Utopia\Migration\Resources\Database\Attribute;

class URL extends Text
{
    public function getType(): string
    {
        return Attribute::TYPE_URL;
    }
}
