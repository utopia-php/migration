<?php

namespace Utopia\Migration\Resources\Database;

use Utopia\Migration\Resource;

class Document extends Row
{
    public static function getName(): string
    {
        return Resource::TYPE_DOCUMENT;
    }
}
