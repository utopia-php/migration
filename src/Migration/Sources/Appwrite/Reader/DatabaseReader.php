<?php

namespace Utopia\Migration\Sources\Appwrite\Reader;

use Appwrite\Query;
use Utopia\Database\Database as UtopiaDatabase;
use Utopia\Database\Exception as DatabaseException;
use Utopia\Migration\Exception;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Database\Collection;
use Utopia\Migration\Resources\Database\Database;
use Utopia\Migration\Sources\Appwrite\Reader;

class DatabaseReader implements Reader
{
    public function __construct(private readonly UtopiaDatabase $dbForProject)
    {
    }

    public function report(array $resources, array &$report): void
    {
        if (\in_array(Resource::TYPE_DATABASE, $resources)) {
            $report[Resource::TYPE_DATABASE] = $this->countResources('databases');
        }

        if (\in_array(Resource::TYPE_COLLECTION, $resources)) {
            $report[Resource::TYPE_COLLECTION] = 0;
            $databases = $this->listDatabases();
            foreach ($databases as $database) {
                $collectionId = "database_{$database->getInternalId()}";

                $report[Resource::TYPE_COLLECTION] += $this->countResources($collectionId);
            }
        }

        if (\in_array(Resource::TYPE_DOCUMENT, $resources)) {
            $report[Resource::TYPE_DOCUMENT] = 0;
            $databases = $this->listDatabases();
            foreach ($databases as $database) {
                $dbResource = new Database(
                    $database->getId(),
                    $database->getAttribute('name'),
                    $database->getCreatedAt(),
                    $database->getUpdatedAt(),
                );

                $collections = $this->listCollections($dbResource);

                foreach ($collections as $collection) {
                    $collectionId = "database_{$database->getInternal()}_collection_{$collection->getInternalId()}";

                    $report[Resource::TYPE_DOCUMENT] += $this->countResources($collectionId);
                }
            }
        }

        if (\in_array(Resource::TYPE_ATTRIBUTE, $resources)) {
            $report[Resource::TYPE_ATTRIBUTE] = 0;
            $databases = $this->listDatabases();
            foreach ($databases as $database) {
                $dbResource = new Database(
                    $database->getId(),
                    $database->getAttribute('name'),
                    $database->getCreatedAt(),
                    $database->getUpdatedAt(),
                );

                $collections = $this->listCollections($dbResource);

                foreach ($collections as $collection) {
                    $report[Resource::TYPE_ATTRIBUTE] += $this->countResources('attributes', [
                        Query::equal('databaseInternalId', [$database->getInternalId()]),
                        Query::equal('collectionInternalId', [$collection->getInternalId()]),
                    ]);
                }
            }
        }

        if (\in_array(Resource::TYPE_INDEX, $resources)) {
            $report[Resource::TYPE_INDEX] = 0;
            $databases = $this->listDatabases();
            foreach ($databases as $database) {
                $dbResource = new Database(
                    $database->getId(),
                    $database->getAttribute('name'),
                    $database->getCreatedAt(),
                    $database->getUpdatedAt(),
                );

                $collections = $this->listCollections($dbResource);

                foreach ($collections as $collection) {
                    $report[Resource::TYPE_INDEX] += $this->countResources('indexes', [
                        Query::equal('databaseInternalId', [$database->getInternalId()]),
                        Query::equal('collectionInternalId', [$collection->getInternalId()]),
                    ]);
                }
            }
        }
    }

    public function listDatabases(array $queries = []): array
    {
        return $this->dbForProject->find('databases', $queries);
    }

    public function listCollections(Database $resource, array $queries = []): array
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

    public function listAttributes(Collection $resource, array $queries = []): array
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
            return $this->dbForProject->find('attributes', $queries);
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

    public function listIndexes(Collection $resource, array $queries = []): array
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

    public function listDocuments(Collection $resource, array $queries = []): array
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
            return $this->dbForProject->find($collectionId, $queries);
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

    public function getDocument(Collection $resource, string $documentId, array $queries = []): array
    {
        return $this->dbForProject->getDocument(
            $resource->getId(),
            $documentId,
            $queries
        )->getArrayCopy();
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