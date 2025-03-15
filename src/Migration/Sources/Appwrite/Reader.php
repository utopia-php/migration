<?php

namespace Utopia\Migration\Sources\Appwrite;

use Utopia\Database\Query;
use Utopia\Migration\Resources\Database\Collection;
use Utopia\Migration\Resources\Database\Database;

interface Reader
{
    public function report(array $resources, array &$report);

    /**
     * List databases that match the given queries
     *
     * @param array<Query> $queries
     * @return array
     */
    public function listDatabases(array $queries = []): array;

    /**
     * @param Database $resource
     * @param array $queries
     * @return array
     */
    public function listCollections(Database $resource, array $queries = []): array;

    /**
     * @param Collection $resource
     * @param array<Query> $queries
     * @return array
     */
    public function listAttributes(Collection $resource, array $queries = []): array;

    /**
     * @param Collection $resource
     * @param array<Query> $queries
     * @return array
     */
    public function listIndexes(Collection $resource, array $queries = []): array;

    /**
     * @param Collection $resource
     * @param array<Query> $queries
     * @return array
     */
    public function listDocuments(Collection $resource, array $queries = []): array;

    /**
     * @param Collection $resource
     * @param string $documentId
     * @param array $queries
     * @return array
     */
    public function getDocument(Collection $resource, string $documentId, array $queries = []): array;
}