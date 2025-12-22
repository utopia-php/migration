<?php

namespace Utopia\Migration\Sources\Appwrite\Reader;

use Appwrite\AppwriteException;
use Appwrite\Query;
use Appwrite\Services\Databases;
/* use Appwrite\Services\Tables; */
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Database\Database;
use Utopia\Migration\Resources\Database\Table;
use Utopia\Migration\Sources\Appwrite\Reader;

/**
 * @implements Reader<Query>
 */
class API implements Reader
{
    public function __construct(
        private readonly Databases $database,
        /* private readonly Tables $table, */
    ) {
    }

    /**
     * @throws AppwriteException
     */
    public function report(array $resources, array &$report): mixed
    {
        $relevantResources = [
            Resource::TYPE_DATABASE,
            Resource::TYPE_TABLE,
            Resource::TYPE_ROW,
            Resource::TYPE_COLUMN,
            Resource::TYPE_INDEX
        ];

        if (!Resource::isSupported($relevantResources, $resources)) {
            return null;
        }

        foreach ($relevantResources as $resourceType) {
            if (Resource::isSupported($resourceType, $resources)) {
                $report[$resourceType] = 0;
            }
        }

        $databasesResponse = $this->database->list();
        $databases = $databasesResponse['databases'];

        if (in_array(Resource::TYPE_DATABASE, $resources)) {
            $report[Resource::TYPE_DATABASE] = $databasesResponse['total'];
        }

        if (count(array_intersect($resources, $relevantResources)) === 1 &&
            in_array(Resource::TYPE_DATABASE, $resources)) {
            return null;
        }

        // Process each database
        foreach ($databases as $database) {
            $databaseId = $database['$id'];

            $tables = [];
            $pageLimit = 25;
            $lastTable = null;

            while (true) {
                /* $currentTables = $this->tables->list(...); */
                $currentTables = $this->database->listCollections(
                    $databaseId,
                    $lastTable
                        ? [Query::cursorAfter($lastTable)]
                        : [Query::limit($pageLimit)]
                )['collections']; /* ['tables'] */

                $tables = \array_merge($tables, $currentTables);
                $lastTable = $tables[count($tables) - 1]['$id'] ?? null;

                if (\count($currentTables) < $pageLimit) {
                    break;
                }
            }

            if (Resource::isSupported(Resource::TYPE_TABLE, $resources)) {
                $report[Resource::TYPE_TABLE] += \count($tables);
            }

            if (Resource::isSupported([Resource::TYPE_ROW, Resource::TYPE_COLUMN, Resource::TYPE_INDEX], $resources)) {
                foreach ($tables as $table) {
                    $tableId = $table['$id'];

                    if (Resource::isSupported(Resource::TYPE_COLUMN, $resources)) {
                        // a table already returns a list of attributes
                        $report[Resource::TYPE_COLUMN] += count($table['columns'] ?? $table['attributes'] ?? []);
                    }

                    if (\in_array(Resource::TYPE_INDEX, $resources)) {
                        // A table already returns a list of indexes
                        $report[Resource::TYPE_INDEX] += \count($table['indexes'] ?? []);
                    }

                    if (Resource::isSupported(Resource::TYPE_ROW, $resources)) {
                        /* $rowsResponse = $this->tables->listRows(...) */
                        $rowsResponse = $this->database->listDocuments(
                            $databaseId,
                            $tableId,
                            [Query::limit(1)]
                        );

                        $report[Resource::TYPE_ROW] += $rowsResponse['total'];
                    }
                }
            }
        }

        return null;
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
    public function listTables(Database $resource, array $queries = []): array
    {
        /* $this->tables->list(...)['tables'] */
        return $this->database->listCollections(
            $resource->getId(),
            $queries
        )['collections'];
    }

    /**
     * @param Table $resource
     * @param array $queries
     * @return array
     * @throws AppwriteException
     */
    public function listColumns(Table $resource, array $queries = []): array
    {
        /* $this->tables->listColumns(...)['columns'] */
        return $this->database->listAttributes(
            $resource->getDatabase()->getId(),
            $resource->getId(),
            $queries
        )['attributes'];
    }

    /**
     * @param Table $resource
     * @param array $queries
     * @return array
     * @throws AppwriteException
     */
    public function listIndexes(Table $resource, array $queries = []): array
    {
        /* $this->tables->listIndexes(...)['indexes'] */
        return $this->database->listIndexes(
            $resource->getDatabase()->getId(),
            $resource->getId(),
            $queries
        )['indexes'];
    }


    /**
     * @param Table $resource
     * @param array $queries
     * @return array
     * @throws AppwriteException
     */
    public function listRows(Table $resource, array $queries = []): array
    {
        /* $this->tables->listRows(...)['rows'] */
        return $this->database->listDocuments(
            $resource->getDatabase()->getId(),
            $resource->getId(),
            $queries
        )['documents'];
    }

    /**
     * @param Table $resource
     * @param string $rowId
     * @param array $queries
     * @return array
     * @throws AppwriteException
     */
    public function getRow(Table $resource, string $rowId, array $queries = []): array
    {
        /* $this->tables->getRow(...) */
        return $this->database->getDocument(
            $resource->getDatabase()->getId(),
            $resource->getId(),
            $rowId,
            $queries
        );
    }

    /**
     * @param string $column
     * @return Query
     */
    public function querySelect(string $column): string
    {
        /**
         * Hack turn to array until fix
         */
        return Query::select([$column]);
    }

    /**
     * @param string $column
     * @param array $values
     * @return string
     */
    public function queryEqual(string $column, array $values): string
    {
        return Query::equal($column, $values);
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
