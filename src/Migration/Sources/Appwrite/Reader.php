<?php

namespace Utopia\Migration\Sources\Appwrite;

use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Database\Collection;
use Utopia\Migration\Resources\Database\Database;

/**
 * @template QueryType
 */
interface Reader
{
    public function report(array $resources, array &$report);

    /**
     * List databases that match the given queries
     *
     * @param array<QueryType> $queries
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
     * @param array<QueryType> $queries
     * @return array
     */
    public function listAttributes(Collection $resource, array $queries = []): array;

    /**
     * @param Collection $resource
     * @param array<QueryType> $queries
     * @return array
     */
    public function listIndexes(Collection $resource, array $queries = []): array;

    /**
     * @param Collection $resource
     * @param array<QueryType> $queries
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

    /**
     * @param array $attributes
     * @return QueryType|string
     */
    public function querySelect(array $attributes): mixed;

    /**
     * @param string $attribute
     * @param array $values
     * @return QueryType|string
     */
    public function queryEqual(string $attribute, array $values): mixed;

    /**
     * @param Resource|string $resource
     * @return QueryType|string
     */
    public function queryCursorAfter(Resource|string $resource): mixed;

    /**
     * @param int $limit
     * @return QueryType|string
     */
    public function queryLimit(int $limit): mixed;
}
