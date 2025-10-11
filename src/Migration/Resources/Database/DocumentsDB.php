<?php

namespace Utopia\Migration\Resources\Database;

use Utopia\Migration\Resource;

class DocumentsDB extends Database
{
    public static function getName(): string
    {
        return Resource::TYPE_DOCUMENTSDB_DATABASE;
    }
}
