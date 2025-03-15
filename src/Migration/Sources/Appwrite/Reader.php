<?php

namespace Utopia\Migration\Sources\Appwrite;

use Utopia\Migration\Resources\Database\Collection;
use Utopia\Migration\Resources\Database\Database;

interface Reader
{
    public function report(array $resources, array &$report);

    public function listDatabases(): array;

    public function listCollections(Database $database): array;

    public function listAttributes(Collection $collection): array;

    public function listIndexes(Collection $collection): array;

    public function listDocuments(Collection $collection): array;
}