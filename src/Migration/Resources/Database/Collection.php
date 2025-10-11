<?php

namespace Utopia\Migration\Resources\Database;

use Override;
use Utopia\Migration\Resource;

class Collection extends Table
{
    #[Override]
    public static function getName(): string
    {
        return Resource::TYPE_COLLECTION;
    }
}
