<?php

namespace Utopia\Migration\Sources\Appwrite;

use Utopia\Database\Query;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Database\Collection;
use Utopia\Migration\Resources\Database\Database;
use Utopia\Migration\Resources\Database\Database as DatabaseResource;

/**
 * @template QueryType
 */
abstract class Reader
{
    /**
     * Get the maximum batch size for the source
     * @return int
     */
    abstract public function getBatchSize(): int;

    /**
     * Get information about the resources currently on the source
     *
     * @param array $resources
     * @param array &$report
     * @return void
     */
    abstract public function report(array $resources, array &$report): void;

    public function foreachDatabase(callable $callback): void
    {
        $lastDatabase = null;
        while (true) {
            $databases = $this->listDatabases(
                $lastDatabase
                    ? [Query::cursorAfter($lastDatabase), Query::limit($this->getBatchSize())]
                    : [Query::limit($this->getBatchSize())]
            );
            foreach ($databases as $database) {
                $callback($database);
            }
            $lastDatabase = end($databases)['$id'] ?? null;
            if (\count($databases) < $this->getBatchSize()) {
                break;
            }
        }
    }

    public function foreachCollection(DatabaseResource $dbResource, callable $callback): void
    {
        $lastCollection = null;
        while (true) {
            $collections = $this->listCollections(
                $dbResource,
                $lastCollection
                    ? [Query::cursorAfter($lastCollection), Query::limit($this->getBatchSize())]
                    : [Query::limit($this->getBatchSize())]
            );
            foreach ($collections as $collection) {
                $callback($collection);
            }
            $lastCollection = end($collections)['$id'] ?? null;
            if (\count($collections) < $this->getBatchSize()) {
                break;
            }
        }
    }

    /**
     * List databases that match the given queries
     *
     * @param array<QueryType> $queries
     * @return array
     */
    abstract public function listDatabases(array $queries = []): array;

    /**
     * List collections that match the given queries
     *
     * @param Database $resource
     * @param array $queries
     * @return array
     */
    abstract public function listCollections(Database $resource, array $queries = []): array;

    /**
     * List attributes that match the given queries
     *
     * @param Collection $resource
     * @param array<QueryType> $queries
     * @return array
     */
    abstract public function listAttributes(Collection $resource, array $queries = []): array;

    /**
     * List indexes that match the given queries
     *
     * @param Collection $resource
     * @param array<QueryType> $queries
     * @return array
     */
    abstract public function listIndexes(Collection $resource, array $queries = []): array;

    /**
     * List documents that match the given queries
     *
     * @param Collection $resource
     * @param array<QueryType> $queries
     * @return array
     */
    abstract public function listDocuments(Collection $resource, array $queries = []): array;

    /**
     * Get a document by its ID in the given collection
     *
     * @param Collection $resource
     * @param string $documentId
     * @param array $queries
     * @return array
     */
    abstract public function getDocument(Collection $resource, string $documentId, array $queries = []): array;

    /**
     * Return a query to select the given attributes
     *
     * @param array $attributes
     * @return QueryType|string
     */
    abstract public function querySelect(array $attributes): mixed;

    /**
     * Return a query to filter the given attributes
     *
     * @param string $attribute
     * @param array $values
     * @return QueryType|string
     */
    abstract public function queryEqual(string $attribute, array $values): mixed;

    /**
     * Return a query to paginate after the given resource
     *
     * @param Resource|string $resource
     * @return QueryType|string
     */
    abstract public function queryCursorAfter(Resource|string $resource): mixed;

    /**
     * Return a query to limit the number of results
     *
     * @param int $limit
     * @return QueryType|string
     */
    abstract public function queryLimit(int $limit): mixed;
}
