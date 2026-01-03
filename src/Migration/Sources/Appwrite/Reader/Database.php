<?php

namespace Utopia\Migration\Sources\Appwrite\Reader;

use Utopia\Database\Database as UtopiaDatabase;
use Utopia\Database\Document as UtopiaDocument;
use Utopia\Database\Exception as DatabaseException;
use Utopia\Database\Query;
use Utopia\Migration\Exception;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Database\Column as ColumnResource;
use Utopia\Migration\Resources\Database\Database as DatabaseResource;
use Utopia\Migration\Resources\Database\Index as IndexResource;
use Utopia\Migration\Resources\Database\Row as RowResource;
use Utopia\Migration\Resources\Database\Table as TableResource;
use Utopia\Migration\Sources\Appwrite\Reader;

/**
 * @implements Reader<Query>
 */
class Database implements Reader
{
    public function __construct(private readonly UtopiaDatabase $dbForProject)
    {
    }

    public function report(array $resources, array &$report, array $resourceIds = []): mixed
    {
        $relevantResources = [
            Resource::TYPE_DATABASE,
            Resource::TYPE_TABLE,
            Resource::TYPE_ROW,
            Resource::TYPE_COLUMN,
            Resource::TYPE_INDEX,
        ];

        if (!Resource::isSupported($relevantResources, $resources)) {
            return null;
        }

        foreach ($relevantResources as $resourceType) {
            if (Resource::isSupported($resourceType, $resources)) {
                $report[$resourceType] = 0;
            }
        }

        $databaseQueries = [];
        if (!empty($resourceIds[Resource::TYPE_DATABASE])) {
            $databaseIds = is_array($resourceIds[Resource::TYPE_DATABASE])
                ? $resourceIds[Resource::TYPE_DATABASE]
                : [$resourceIds[Resource::TYPE_DATABASE]];

            $databaseQueries[] = Query::equal('$id', $databaseIds);
        }

        if (in_array(Resource::TYPE_DATABASE, $resources)) {
            $report[Resource::TYPE_DATABASE] = $this->countResources('databases', $databaseQueries);
        }

        if (count(array_intersect($resources, $relevantResources)) === 1 &&
            in_array(Resource::TYPE_DATABASE, $resources)) {
            return null;
        }

        $dbResources = [];
        $databases = $this->listDatabases($databaseQueries);

        // Process each database
        foreach ($databases as $database) {
            $databaseSequence = $database->getSequence();
            $tableId = "database_{$databaseSequence}";

            if (Resource::isSupported(Resource::TYPE_TABLE, $resources)) {
                $report[Resource::TYPE_TABLE] += $this->countResources($tableId);
            }

            if (!Resource::isSupported([Resource::TYPE_ROW, Resource::TYPE_COLUMN, Resource::TYPE_INDEX], $resources)) {
                continue;
            }

            if (!isset($dbResources[$database->getId()])) {
                $dbResources[$database->getId()] = new DatabaseResource(
                    $database->getId(),
                    $database->getAttribute('name'),
                    $database->getCreatedAt(),
                    $database->getUpdatedAt(),
                );
            }

            $dbResource = $dbResources[$database->getId()];

            $tables = $this->listTables($dbResource);

            foreach ($tables as $table) {
                $tableSequence = $table->getSequence();

                if (Resource::isSupported(Resource::TYPE_ROW, $resources)) {
                    $rowTableId = "database_{$databaseSequence}_collection_{$tableSequence}";
                    $report[Resource::TYPE_ROW] += $this->countResources($rowTableId);
                }

                $commonQueries = [
                    Query::equal('databaseInternalId', [$databaseSequence]),
                    Query::equal('collectionInternalId', [$tableSequence]),
                ];

                if (Resource::isSupported(Resource::TYPE_COLUMN, $resources)) {
                    $report[Resource::TYPE_COLUMN] += $this->countResources('attributes', $commonQueries);
                }

                if (in_array(Resource::TYPE_INDEX, $resources)) {
                    $report[Resource::TYPE_INDEX] += $this->countResources('indexes', $commonQueries);
                }
            }
        }

        return null;
    }

    public function listDatabases(array $queries = []): array
    {
        return $this->dbForProject->find('databases', $queries);
    }

    public function listTables(DatabaseResource $resource, array $queries = []): array
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
                'database_' . $database->getSequence(),
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

    public function listColumns(TableResource $resource, array $queries = []): array
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

        $table = $this->dbForProject->getDocument(
            'database_' . $database->getSequence(),
            $resource->getId(),
        );

        if ($table->isEmpty()) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Table not found',
            );
        }

        $queries[] = Query::equal('databaseInternalId', [$database->getSequence()]);
        $queries[] = Query::equal('collectionInternalId', [$table->getSequence()]);

        try {
            $columns = $this->dbForProject->find('attributes', $queries);
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

        foreach ($columns as $column) {
            if ($column['type'] !== UtopiaDatabase::VAR_RELATIONSHIP) {
                continue;
            }

            $options = $column['options'];
            foreach ($options as $key => $value) {
                $column[$key] = $value;
            }

            unset($column['options']);
        }

        return $columns;
    }

    public function listIndexes(TableResource $resource, array $queries = []): array
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

        $table = $this->dbForProject->getDocument(
            'database_' . $database->getSequence(),
            $resource->getId(),
        );

        if ($table->isEmpty()) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Table not found',
            );
        }

        $queries[] = Query::equal('databaseInternalId', [$database->getSequence()]);
        $queries[] = Query::equal('collectionInternalId', [$table->getSequence()]);

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

    public function listRows(TableResource $resource, array $queries = []): array
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

        $table = $this->dbForProject->getDocument(
            'database_' . $database->getSequence(),
            $resource->getId(),
        );

        if ($table->isEmpty()) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Table not found',
            );
        }

        $tableId = "database_{$database->getSequence()}_collection_{$table->getSequence()}";

        try {
            $rows = $this->dbForProject->find($tableId, $queries);
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

        return \array_map(function ($row) {
            return $row->getArrayCopy();
        }, $rows);
    }

    public function getRow(TableResource $resource, string $rowId, array $queries = []): array
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

        $table = $this->dbForProject->getDocument(
            'database_' . $database->getSequence(),
            $resource->getId(),
        );

        if ($table->isEmpty()) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Table not found',
            );
        }

        $tableId = "database_{$database->getSequence()}_collection_{$table->getSequence()}";

        return $this->dbForProject->getDocument(
            $tableId,
            $rowId,
            $queries
        )->getArrayCopy();
    }

    /**
     * @param array $columns
     * @return Query
     */
    public function querySelect(array $columns): Query
    {
        return Query::select($columns);
    }

    /**
     * @param string $column
     * @param array $values
     * @return Query
     */
    public function queryEqual(string $column, array $values): Query
    {
        return Query::equal($column, $values);
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
            case TableResource::class:
                $database = $this->dbForProject->getDocument('databases', $resource->getDatabase()->getId());
                $document = $this->dbForProject->getDocument('database_' . $database->getSequence(), $resource->getId());
                break;
            case ColumnResource::class:
                $document = $this->dbForProject->getDocument('attributes', $resource->getId());
                break;
            case IndexResource::class:
                $document = $this->dbForProject->getDocument('indexes', $resource->getId());
                break;
            case RowResource::class:
                $document = $this->getRow($resource->getTable(), $resource->getId());
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
     * @param string $table
     * @param array $queries
     * @return int
     * @throws DatabaseException
     */
    private function countResources(string $table, array $queries = []): int
    {
        return $this->dbForProject->count($table, $queries);
    }
}
