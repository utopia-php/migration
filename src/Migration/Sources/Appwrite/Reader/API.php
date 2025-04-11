<?php

namespace Utopia\Migration\Sources\Appwrite\Reader;

use Appwrite\AppwriteException;
use Appwrite\Query;
use Appwrite\Services\Databases;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Database\Collection;
use Utopia\Migration\Resources\Database\Database;
use Utopia\Migration\Sources\Appwrite\Reader;

/**
 * @extends Reader<Query>
 */
class API extends Reader
{
    public function __construct(private readonly Databases $database)
    {
    }

    public function getBatchSize(): int
    {
        return 500;
    }

    public function report(array $resources, array &$report): void
    {
        $report[Resource::TYPE_DATABASE] = 0;
        $report[Resource::TYPE_COLLECTION] = 0;
        $report[Resource::TYPE_DOCUMENT] = 0;
        $report[Resource::TYPE_ATTRIBUTE] = 0;
        $report[Resource::TYPE_INDEX] = 0;

        $databaseCount = 0;
        $this->foreachDatabase(function($database) use ($resources, &$report, &$databaseCount) {
            $databaseCount++;

            $databaseId = $database['$id'];

            // Determine if we should use the fast count for collections
            $onlyCollectionNeeded = \in_array(Resource::TYPE_COLLECTION, $resources)
                && !\in_array(Resource::TYPE_DOCUMENT, $resources)
                && !\in_array(Resource::TYPE_ATTRIBUTE, $resources)
                && !\in_array(Resource::TYPE_INDEX, $resources);

            if ($onlyCollectionNeeded) {
                // Fast count without fetching all collections
                $report[Resource::TYPE_COLLECTION] += $this->database->listCollections(
                    $databaseId,
                    [Query::limit(1)]
                )['total'];
            } else {
                $dbResource = new Database(
                    $database->getId(),
                    $database->getAttribute('name'),
                    $database->getCreatedAt(),
                    $database->getUpdatedAt(),
                );

                // For full details, iterate collections once per database
                $collectionCount = 0;
                $this->foreachCollection($dbResource, function($collection) use ($databaseId, $resources, &$report, &$collectionCount) {
                    $collectionCount++;

                    if (\in_array(Resource::TYPE_DOCUMENT, $resources)) {
                        $report[Resource::TYPE_DOCUMENT] += $this->database->listDocuments(
                            $databaseId,
                            $collection['$id'],
                            [Query::limit(1)]
                        )['total'];
                    }

                    if (\in_array(Resource::TYPE_ATTRIBUTE, $resources)) {
                        $report[Resource::TYPE_ATTRIBUTE] += $this->database->listAttributes(
                            $databaseId,
                            $collection['$id'],
                            [Query::limit(1)]
                        )['total'];
                    }

                    if (\in_array(Resource::TYPE_INDEX, $resources)) {
                        $report[Resource::TYPE_INDEX] += $this->database->listIndexes(
                            $databaseId,
                            $collection['$id'],
                            [Query::limit(1)]
                        )['total'];
                    }
                });

                if (\in_array(Resource::TYPE_COLLECTION, $resources)) {
                    $report[Resource::TYPE_COLLECTION] += $collectionCount;
                }
            }
        });

        if (\in_array(Resource::TYPE_DATABASE, $resources)) {
            $report[Resource::TYPE_DATABASE] = $databaseCount;
        }
    }

    /**
     * @throws AppwriteException
     */
    public function listDatabases(array $queries = []): array
    {
        return $this->database->list($queries)['databases'];
    }

    /**
     * @throws AppwriteException
     */
    public function listCollections(Database $resource, array $queries = []): array
    {
        return $this->database->listCollections(
            $resource->getId(),
            $queries
        )['collections'];
    }

    /**
     * @param Collection $resource
     * @param array $queries
     * @return array
     * @throws AppwriteException
     */
    public function listAttributes(Collection $resource, array $queries = []): array
    {
        return $this->database->listAttributes(
            $resource->getDatabase()->getId(),
            $resource->getId(),
            $queries
        )['attributes'];
    }

    /**
     * @param Collection $resource
     * @param array $queries
     * @return array
     * @throws AppwriteException
     */
    public function listIndexes(Collection $resource, array $queries = []): array
    {
        return $this->database->listIndexes(
            $resource->getDatabase()->getId(),
            $resource->getId(),
            $queries
        )['indexes'];
    }


    /**
     * @param Collection $resource
     * @param array $queries
     * @return array
     * @throws AppwriteException
     */
    public function listDocuments(Collection $resource, array $queries = []): array
    {
        return $this->database->listDocuments(
            $resource->getDatabase()->getId(),
            $resource->getId(),
            $queries
        )['documents'];
    }

    /**
     * @param Collection $resource
     * @param string $documentId
     * @param array $queries
     * @return array
     * @throws AppwriteException
     */
    public function getDocument(Collection $resource, string $documentId, array $queries = []): array
    {
        return $this->database->getDocument(
            $resource->getDatabase()->getId(),
            $resource->getId(),
            $documentId,
            $queries
        );
    }

    /**
     * @param array $attributes
     * @return string
     */
    public function querySelect(array $attributes): string
    {
        return Query::select($attributes);
    }

    /**
     * @param string $attribute
     * @param array $values
     * @return string
     */
    public function queryEqual(string $attribute, array $values): string
    {
        return Query::equal($attribute, $values);
    }

    /**
     * @param Resource|string $resource
     * @return string
     */
    public function queryCursorAfter(Resource|string $resource): string
    {
        if ($resource instanceof Resource) {
            $resource = $resource->getId();
        }

        return Query::cursorAfter($resource);
    }

    public function queryLimit(int $limit): string
    {
        return Query::limit($limit);
    }
}
