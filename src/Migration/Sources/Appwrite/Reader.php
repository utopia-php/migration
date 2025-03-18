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
    /**
     * Get information about the resources currently on the source
     *
     * @param array $resources
     * @param array $report
     * @return mixed
     */
    public function report(array $resources, array &$report);

    /**
     * List databases that match the given queries
     *
     * @param array<QueryType> $queries
     * @return array
     */
    public function listDatabases(array $queries = []): array;

    /**
     * List collections that match the given queries
     *
     * @param Database $resource
     * @param array $queries
     * @return array
     */
    public function listCollections(Database $resource, array $queries = []): array;

    /**
     * List attributes that match the given queries
     *
     * @param Collection $resource
     * @param array<QueryType> $queries
     * @return array
     */
    public function listAttributes(Collection $resource, array $queries = []): array;

    /**
     * List indexes that match the given queries
     *
     * @param Collection $resource
     * @param array<QueryType> $queries
     * @return array
     */
    public function listIndexes(Collection $resource, array $queries = []): array;

    /**
     * List documents that match the given queries
     *
     * @param Collection $resource
     * @param array<QueryType> $queries
     * @return array
     */
    public function listDocuments(Collection $resource, array $queries = []): array;

    /**
     * Get a document by its ID in the given collection
     *
     * @param Collection $resource
     * @param string $documentId
     * @param array $queries
     * @return array
     */
    public function getDocument(Collection $resource, string $documentId, array $queries = []): array;

    /**
     * Return a query to select the given attributes
     *
     * @param array $attributes
     * @return QueryType|string
     */
    public function querySelect(array $attributes): mixed;

    /**
     * Return a query to filter the given attributes
     *
     * @param string $attribute
     * @param array $values
     * @return QueryType|string
     */
    public function queryEqual(string $attribute, array $values): mixed;

    /**
     * Return a query to paginate after the given resource
     *
     * @param Resource|string $resource
     * @return QueryType|string
     */
    public function queryCursorAfter(Resource|string $resource): mixed;

    /**
     * Return a query to limit the number of results
     * 
     * @param int $limit
     * @return QueryType|string
     */
    public function queryLimit(int $limit): mixed;
}
