<?php

namespace Utopia\Migration\Sources\Appwrite\Reader;

use Utopia\Database\Database as UtopiaDatabase;
use Utopia\Database\Document as UtopiaDocument;
use Utopia\Database\Exception as DatabaseException;
use Utopia\Database\Query;
use Utopia\Migration\Exception;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Database\Attribute as AttributeResource;
use Utopia\Migration\Resources\Database\Collection as CollectionResource;
use Utopia\Migration\Resources\Database\Database as DatabaseResource;
use Utopia\Migration\Resources\Database\Document as DocumentResource;
use Utopia\Migration\Resources\Database\Index as IndexResource;
use Utopia\Migration\Sources\Appwrite\Reader;

/**
 * @extends Reader<Query>
 */
class Database extends Reader
{
    public function __construct(private readonly UtopiaDatabase $dbForProject)
    {
    }

    public function report(array $resources, array &$report): void
    {
        if (in_array(Resource::TYPE_DATABASE, $resources)) {
            $report[Resource::TYPE_DATABASE] = $this->countResources('databases');
        }

        // Determine if we need to fetch databases (for collection, document, attribute, or index resources).
        $needsDatabases = \in_array(Resource::TYPE_COLLECTION, $resources)
            || in_array(Resource::TYPE_DOCUMENT, $resources)
            || in_array(Resource::TYPE_ATTRIBUTE, $resources)
            || in_array(Resource::TYPE_INDEX, $resources);

        $databases = [];
        if ($needsDatabases) {
            $this->foreachDatabase(function($db) use (&$databases) {
                $databases[] = $db;
            });
        }

        // Process collections: count resources per database.
        if (\in_array(Resource::TYPE_COLLECTION, $resources)) {
            $report[Resource::TYPE_COLLECTION] = 0;

            foreach ($databases as $database) {
                $report[Resource::TYPE_COLLECTION] += $this->countResources("database_{$database->getInternalId()}");
            }
        }

        // If any of the document, attribute, or index reports are required, we need to retrieve collections.
        $needsCollections = \in_array(Resource::TYPE_DOCUMENT, $resources)
            || \in_array(Resource::TYPE_ATTRIBUTE, $resources)
            || \in_array(Resource::TYPE_INDEX, $resources);

        if ($needsCollections) {
            if (in_array(Resource::TYPE_DOCUMENT, $resources)) {
                $report[Resource::TYPE_DOCUMENT] = 0;
            }
            if (in_array(Resource::TYPE_ATTRIBUTE, $resources)) {
                $report[Resource::TYPE_ATTRIBUTE] = 0;
            }
            if (in_array(Resource::TYPE_INDEX, $resources)) {
                $report[Resource::TYPE_INDEX] = 0;
            }

            foreach ($databases as $database) {
                $dbResource = new DatabaseResource(
                    $database->getId(),
                    $database->getAttribute('name'),
                    $database->getCreatedAt(),
                    $database->getUpdatedAt()
                );

                // Retrieve all collections for this database
                $collections = [];
                $this->foreachCollection($dbResource, function($collection) use (&$collections) {
                    $collections[] = $collection;
                });

                if (in_array(Resource::TYPE_DOCUMENT, $resources)) {
                    foreach ($collections as $collection) {
                        $collectionId = "database_{$database->getInternalId()}_collection_{$collection->getInternalId()}";
                        $report[Resource::TYPE_DOCUMENT] += $this->countResources($collectionId);
                    }
                }

                if (in_array(Resource::TYPE_ATTRIBUTE, $resources)) {
                    foreach ($collections as $collection) {
                        $report[Resource::TYPE_ATTRIBUTE] += $this->countResources('attributes', [
                            Query::equal('databaseInternalId', [$database->getInternalId()]),
                            Query::equal('collectionInternalId', [$collection->getInternalId()]),
                        ]);
                    }
                }

                if (in_array(Resource::TYPE_INDEX, $resources)) {
                    foreach ($collections as $collection) {
                        $report[Resource::TYPE_INDEX] += $this->countResources('indexes', [
                            Query::equal('databaseInternalId', [$database->getInternalId()]),
                            Query::equal('collectionInternalId', [$collection->getInternalId()]),
                        ]);
                    }
                }
            }
        }
    }

    public function listDatabases(array $queries = []): array
    {
        return $this->dbForProject->find('databases', $queries);
    }

    public function listCollections(DatabaseResource $resource, array $queries = []): array
    {
        try {
            $database = $this->dbForProject->getDocument('databases', $resource->getId());
        } catch (DatabaseException $e) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: $e->getMessage(),
                code: $e->getCode(),
                previous: $e
            );
        }

        if ($database->isEmpty()) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Database not found',
            );
        }

        try {
            return $this->dbForProject->find(
                'database_' . $database->getInternalId(),
                $queries
            );
        } catch (DatabaseException $e) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: $e->getMessage(),
                code: $e->getCode(),
                previous: $e
            );
        }
    }

    public function listAttributes(CollectionResource $resource, array $queries = []): array
    {
        $database = $this->dbForProject->getDocument(
            'databases',
            $resource->getDatabase()->getId(),
        );

        if ($database->isEmpty()) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Database not found',
            );
        }

        $collection = $this->dbForProject->getDocument(
            'database_' . $database->getInternalId(),
            $resource->getId(),
        );

        if ($collection->isEmpty()) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Collection not found',
            );
        }

        $queries[] = Query::equal('databaseInternalId', [$database->getInternalId()]);
        $queries[] = Query::equal('collectionInternalId', [$collection->getInternalId()]);

        try {
            $attributes = $this->dbForProject->find('attributes', $queries);
        } catch (DatabaseException $e) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: $e->getMessage(),
                code: $e->getCode(),
                previous: $e
            );
        }

        foreach ($attributes as $attribute) {
            if ($attribute['type'] !== UtopiaDatabase::VAR_RELATIONSHIP) {
                continue;
            }

            $options = $attribute['options'];
            foreach ($options as $key => $value) {
                $attribute[$key] = $value;
            }

            unset($attribute['options']);
        }

        return $attributes;
    }

    public function listIndexes(CollectionResource $resource, array $queries = []): array
    {
        $database = $this->dbForProject->getDocument(
            'databases',
            $resource->getDatabase()->getId(),
        );

        if ($database->isEmpty()) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Database not found',
            );
        }

        $collection = $this->dbForProject->getDocument(
            'database_' . $database->getInternalId(),
            $resource->getId(),
        );

        if ($collection->isEmpty()) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Collection not found',
            );
        }

        $queries[] = Query::equal('databaseInternalId', [$database->getInternalId()]);
        $queries[] = Query::equal('collectionInternalId', [$collection->getInternalId()]);

        try {
            return $this->dbForProject->find('indexes', $queries);
        } catch (DatabaseException $e) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: $e->getMessage(),
                code: $e->getCode(),
                previous: $e
            );
        }
    }

    public function listDocuments(CollectionResource $resource, array $queries = []): array
    {
        $database = $this->dbForProject->getDocument(
            'databases',
            $resource->getDatabase()->getId(),
        );

        if ($database->isEmpty()) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Database not found',
            );
        }

        $collection = $this->dbForProject->getDocument(
            'database_' . $database->getInternalId(),
            $resource->getId(),
        );

        if ($collection->isEmpty()) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Collection not found',
            );
        }

        $collectionId = "database_{$database->getInternalId()}_collection_{$collection->getInternalId()}";

        try {
            $documents = $this->dbForProject->find($collectionId, $queries);
        } catch (DatabaseException $e) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: $e->getMessage(),
                code: $e->getCode(),
                previous: $e
            );
        }

        return \array_map(function ($document) {
            return $document->getArrayCopy();
        }, $documents);
    }

    public function getDocument(CollectionResource $resource, string $documentId, array $queries = []): array
    {
        $database = $this->dbForProject->getDocument(
            'databases',
            $resource->getDatabase()->getId(),
        );

        if ($database->isEmpty()) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Database not found',
            );
        }

        $collection = $this->dbForProject->getDocument(
            'database_' . $database->getInternalId(),
            $resource->getId(),
        );

        if ($collection->isEmpty()) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Collection not found',
            );
        }

        $collectionId = "database_{$database->getInternalId()}_collection_{$collection->getInternalId()}";

        return $this->dbForProject->getDocument(
            $collectionId,
            $documentId,
            $queries
        )->getArrayCopy();
    }

    /**
     * @param array $attributes
     * @return Query
     */
    public function querySelect(array $attributes): Query
    {
        return Query::select($attributes);
    }

    /**
     * @param string $attribute
     * @param array $values
     * @return Query
     */
    public function queryEqual(string $attribute, array $values): Query
    {
        return Query::equal($attribute, $values);
    }

    /**
     * @param Resource|string $resource
     * @return Query
     * @throws DatabaseException
     * @throws Exception
     */
    public function queryCursorAfter(mixed $resource): Query
    {
        if (\is_string($resource)) {
            throw new \InvalidArgumentException('Querying with a cursor through the database requires a resource reference');
        }

        switch ($resource::class) {
            case DatabaseResource::class:
                $document = $this->dbForProject->getDocument('databases', $resource->getId());
                break;
            case CollectionResource::class:
                $database = $this->dbForProject->getDocument('databases', $resource->getDatabase()->getId());
                $document = $this->dbForProject->getDocument('database_' . $database->getInternalId(), $resource->getId());
                break;
            case AttributeResource::class:
                $document = $this->dbForProject->getDocument('attributes', $resource->getId());
                break;
            case IndexResource::class:
                $document = $this->dbForProject->getDocument('indexes', $resource->getId());
                break;
            case DocumentResource::class:
                $document = $this->getDocument($resource->getCollection(), $resource->getId());
                $document = new UtopiaDocument($document);
                break;
            default:
                throw new \InvalidArgumentException('Unsupported resource type');
        }

        return Query::cursorAfter($document);
    }

    public function queryLimit(int $limit): Query
    {
        return Query::limit($limit);
    }

    /**
     * @param string $collection
     * @param array $queries
     * @return int
     * @throws DatabaseException
     */
    private function countResources(string $collection, array $queries = []): int
    {
        return $this->dbForProject->count($collection, $queries);
    }
}
