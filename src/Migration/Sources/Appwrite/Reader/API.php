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
 * @implements Reader<Query>
 */
class API implements Reader
{
    public function __construct(private readonly Databases $database)
    {
    }

    /**
     * @throws AppwriteException
     */
    public function report(array $resources, array &$report): void
    {
        if (\in_array(Resource::TYPE_DATABASE, $resources)) {
            $report[Resource::TYPE_DATABASE] = $this->database->list()['total'];
        }

        if (\in_array(Resource::TYPE_COLLECTION, $resources)) {
            $report[Resource::TYPE_COLLECTION] = 0;
            $databases = $this->database->list()['databases'];
            foreach ($databases as $database) {
                $report[Resource::TYPE_COLLECTION] += $this->database->listCollections(
                    $database['$id'],
                    [Query::limit(1)]
                )['total'];
            }
        }

        if (\in_array(Resource::TYPE_DOCUMENT, $resources)) {
            $report[Resource::TYPE_DOCUMENT] = 0;
            $databases = $this->database->list()['databases'];
            foreach ($databases as $database) {
                $collections = $this->database->listCollections($database['$id'])['collections'];
                foreach ($collections as $collection) {
                    $report[Resource::TYPE_DOCUMENT] += $this->database->listDocuments(
                        $database['$id'],
                        $collection['$id'],
                        [Query::limit(1)]
                    )['total'];
                }
            }
        }

        if (\in_array(Resource::TYPE_ATTRIBUTE, $resources)) {
            $report[Resource::TYPE_ATTRIBUTE] = 0;
            $databases = $this->database->list()['databases'];
            foreach ($databases as $database) {
                $collections = $this->database->listCollections($database['$id'])['collections'];
                foreach ($collections as $collection) {
                    $report[Resource::TYPE_ATTRIBUTE] += $this->database->listAttributes(
                        $database['$id'],
                        $collection['$id']
                    )['total'];
                }
            }
        }

        if (\in_array(Resource::TYPE_INDEX, $resources)) {
            $report[Resource::TYPE_INDEX] = 0;
            $databases = $this->database->list()['databases'];
            foreach ($databases as $database) {
                $collections = $this->database->listCollections($database['$id'])['collections'];
                foreach ($collections as $collection) {
                    $report[Resource::TYPE_INDEX] += $this->database->listIndexes(
                        $database['$id'],
                        $collection['$id']
                    )['total'];
                }
            }
        }
    }

    /**
     * @throws AppwriteException
     */
    public function listDatabases(array $queries = []): array
    {
        return $this->database->list($queries);
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
        if (!\is_string($resource)) {
            throw new \InvalidArgumentException('Querying with a cursor through the API requires a string resource ID');
        }

        return Query::cursorAfter($resource);
    }

    public function queryLimit(int $limit): string
    {
        return Query::limit($limit);
    }
}
