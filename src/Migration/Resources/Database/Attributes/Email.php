<?php

namespace Utopia\Migration\Resources\Database\Attributes;

use Utopia\Migration\Resources\Database\Attribute;

class Email extends Text
{
    public function getTypeName(): string
    {
        return Attribute::TYPE_EMAIL;
    }
}
