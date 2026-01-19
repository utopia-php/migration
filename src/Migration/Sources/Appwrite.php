<?php

namespace Utopia\Migration\Sources;

use Appwrite\AppwriteException;
use Appwrite\Client;
use Appwrite\Query;
use Appwrite\Services\Databases;
use Appwrite\Services\Functions;
use Appwrite\Services\Storage;
use Appwrite\Services\Teams;
use Appwrite\Services\Users;
use Utopia\Console;
use Utopia\Database\Database as UtopiaDatabase;
use Utopia\Database\DateTime as UtopiaDateTime;
use Utopia\Migration\Exception;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Auth\Hash;
use Utopia\Migration\Resources\Auth\Membership;
use Utopia\Migration\Resources\Auth\Team;
use Utopia\Migration\Resources\Auth\User;
use Utopia\Migration\Resources\Database\Attribute;
use Utopia\Migration\Resources\Database\Attribute\Boolean as AttributeBoolean;
use Utopia\Migration\Resources\Database\Attribute\DateTime as AttributeDateTime;
use Utopia\Migration\Resources\Database\Attribute\Decimal as AttributeDecimal;
use Utopia\Migration\Resources\Database\Attribute\Email as AttributeEmail;
use Utopia\Migration\Resources\Database\Attribute\Enum as AttributeEnum;
use Utopia\Migration\Resources\Database\Attribute\Integer as AttributeInteger;
use Utopia\Migration\Resources\Database\Attribute\IP as AttributeIP;
use Utopia\Migration\Resources\Database\Attribute\Line as AttributeLine;
use Utopia\Migration\Resources\Database\Attribute\ObjectType as AttributeObjectType;
use Utopia\Migration\Resources\Database\Attribute\Point as AttributePoint;
use Utopia\Migration\Resources\Database\Attribute\Polygon as AttributePolygon;
use Utopia\Migration\Resources\Database\Attribute\Relationship as AttributeRelationship;
use Utopia\Migration\Resources\Database\Attribute\Text as AttributeText;
use Utopia\Migration\Resources\Database\Attribute\URL as AttributeURL;
use Utopia\Migration\Resources\Database\Attribute\Vector as AttributeVector;
use Utopia\Migration\Resources\Database\Collection;
use Utopia\Migration\Resources\Database\Column;
use Utopia\Migration\Resources\Database\Columns\Boolean;
use Utopia\Migration\Resources\Database\Columns\DateTime;
use Utopia\Migration\Resources\Database\Columns\Decimal;
use Utopia\Migration\Resources\Database\Columns\Email;
use Utopia\Migration\Resources\Database\Columns\Enum;
use Utopia\Migration\Resources\Database\Columns\Integer;
use Utopia\Migration\Resources\Database\Columns\IP;
use Utopia\Migration\Resources\Database\Columns\Line;
use Utopia\Migration\Resources\Database\Columns\ObjectType;
use Utopia\Migration\Resources\Database\Columns\Point;
use Utopia\Migration\Resources\Database\Columns\Polygon;
use Utopia\Migration\Resources\Database\Columns\Relationship;
use Utopia\Migration\Resources\Database\Columns\Text;
use Utopia\Migration\Resources\Database\Columns\URL;
use Utopia\Migration\Resources\Database\Columns\Vector;
use Utopia\Migration\Resources\Database\Database;
use Utopia\Migration\Resources\Database\Document;
use Utopia\Migration\Resources\Database\DocumentsDB;
use Utopia\Migration\Resources\Database\Index;
use Utopia\Migration\Resources\Database\Row;
use Utopia\Migration\Resources\Database\Table;
use Utopia\Migration\Resources\Database\VectorDB;
use Utopia\Migration\Resources\Functions\Deployment;
use Utopia\Migration\Resources\Functions\EnvVar;
use Utopia\Migration\Resources\Functions\Func;
use Utopia\Migration\Resources\Storage\Bucket;
use Utopia\Migration\Resources\Storage\File;
use Utopia\Migration\Source;
use Utopia\Migration\Sources\Appwrite\Reader;
use Utopia\Migration\Sources\Appwrite\Reader\API as APIReader;
use Utopia\Migration\Sources\Appwrite\Reader\Database as DatabaseReader;
use Utopia\Migration\Transfer;

class Appwrite extends Source
{
    public const SOURCE_API = 'api';
    public const SOURCE_DATABASE = 'database';

    private const DEFAULT_PAGE_LIMIT = 25;

    protected Client $client;

    private Reader $reader;

    private Users $users;

    private Teams $teams;

    private Storage $storage;

    private Functions $functions;

    /**
     * @var callable(UtopiaDocument $database|null): UtopiaDatabase
     */
    protected mixed $getDatabasesDB;

    /**
     * @throws \Exception
     */
    public function __construct(
        protected string $project,
        protected string $endpoint,
        protected string $key,
        callable $getDatabasesDB,
        protected string $source = self::SOURCE_API,
        protected ?UtopiaDatabase $dbForProject = null,
        protected array $queries = [],
    ) {
        $this->client = (new Client())
            ->setEndpoint($endpoint)
            ->setProject($project)
            ->setKey($key);

        $this->users = new Users($this->client);
        $this->teams = new Teams($this->client);
        $this->storage = new Storage($this->client);
        $this->functions = new Functions($this->client);

        $this->headers['X-Appwrite-Project'] = $this->project;
        $this->headers['X-Appwrite-Key'] = $this->key;

        $this->getDatabasesDB = $getDatabasesDB;

        $this->reader = match ($this->source) {
            static::SOURCE_API => new APIReader(new Databases($this->client)),
            static::SOURCE_DATABASE => new DatabaseReader($this->dbForProject, $this->getDatabasesDB, $this->project),
            default => throw new \Exception('Unknown source'),
        };

    }

    public static function getName(): string
    {
        return 'Appwrite';
    }

    /**
     * Log migration debug info for tracked projects
     */
    private function logDebugTrackedProject(string $message): void
    {
        $projectTag = self::$debugProjects[$this->project] ?? null;
        if ($projectTag === null) {
            return;
        }

        Console::info("MIGRATIONS-SOURCE-$projectTag: $message");
    }

    /**
     * @return array<string>
     */
    public static function getSupportedResources(): array
    {
        return [
            // Auth
            Resource::TYPE_USER,
            Resource::TYPE_TEAM,
            Resource::TYPE_MEMBERSHIP,

            // Database
            Resource::TYPE_DATABASE,
            Resource::TYPE_TABLE,
            Resource::TYPE_COLUMN,
            Resource::TYPE_INDEX,
            Resource::TYPE_ROW,

            // legacy
            Resource::TYPE_DOCUMENT,
            Resource::TYPE_ATTRIBUTE,
            Resource::TYPE_COLLECTION,

            // documentsdb
            Resource::TYPE_DATABASE_DOCUMENTSDB,
            // vectordb
            Resource::TYPE_DATABASE_VECTORDB,

            // Storage
            Resource::TYPE_BUCKET,
            Resource::TYPE_FILE,

            // Functions
            Resource::TYPE_FUNCTION,
            Resource::TYPE_DEPLOYMENT,
            Resource::TYPE_ENVIRONMENT_VARIABLE,

            // Settings
        ];
    }

    /**
     * @return int
     */
    public function getDatabasesBatchSize(): int
    {
        return match ($this->source) {
            static::SOURCE_API => 500,
            static::SOURCE_DATABASE => 1000,
        };
    }

    /**
     * @param array<string> $resources
     * @param array<string, array<string>> $resourceIds
     * @return array<string, mixed>
     *
     * @throws \Exception
     */
    public function report(array $resources = [], array $resourceIds = []): array
    {
        $this->validateResourceIds($resourceIds);

        $report = [];

        if (empty($resources)) {
            $resources = $this->getSupportedResources();
        }

        try {
            $this->reportAuth($resources, $report, $resourceIds);
            $this->reportDatabases($resources, $report, $resourceIds);
            $this->reportStorage($resources, $report, $resourceIds);
            $this->reportFunctions($resources, $report, $resourceIds);

            $report['version'] = $this->call(
                'GET',
                '/health/version',
                [
                    'X-Appwrite-Key' => '',
                    'X-Appwrite-Project' => '',
                ]
            )['version'];
        } catch (\Throwable $e) {
            if ($e->getCode() === 403) {
                throw new \Exception("Missing required scopes.");
            } else {
                throw new \Exception($e->getMessage(), previous: $e);
            }
        }

        $this->previousReport = $report;

        return $report;
    }

    /**
     * @param array $resources
     * @param array $report
     * @param array<string, array<string>> $resourceIds
     * @throws AppwriteException
     */
    private function reportAuth(array $resources, array &$report, array $resourceIds = []): void
    {
        // check if we need to fetch teams!
        $needTeams = !empty(array_intersect(
            [Resource::TYPE_TEAM, Resource::TYPE_MEMBERSHIP],
            $resources
        ));

        $teams = ['total' => 0, 'teams' => []];

        if (\in_array(Resource::TYPE_USER, $resources)) {
            $userQueries = $this->buildQueries(
                resourceType: Resource::TYPE_USER,
                resourceIds: $resourceIds,
                limit: 1
            );
            $userList = $this->users->list($userQueries);
            $report[Resource::TYPE_USER] = $userList['total'];
        }

        if ($needTeams) {
            if (\in_array(Resource::TYPE_MEMBERSHIP, $resources)) {
                $allTeams = [];
                $lastTeam = null;

                while (true) {
                    $params = $this->buildQueries(
                        resourceType: Resource::TYPE_TEAM,
                        resourceIds: $resourceIds,
                        cursor: $lastTeam
                    );
                    $teamList = $this->teams->list($params);

                    $totalTeams = $teamList['total'];
                    $currentTeams = $teamList['teams'];

                    $allTeams = array_merge($allTeams, $currentTeams);
                    $lastTeam = $currentTeams[count($currentTeams) - 1]['$id'] ?? null;

                    if (count($currentTeams) < self::DEFAULT_PAGE_LIMIT) {
                        break;
                    }
                }
                $teams = ['total' => $totalTeams, 'teams' => $allTeams];
            } else {
                $params = $this->buildQueries(
                    resourceType: Resource::TYPE_TEAM,
                    resourceIds: $resourceIds,
                    limit: 1
                );
                $teamList = $this->teams->list($params);
                $teams = ['total' => $teamList['total'], 'teams' => []];
            }
        }

        if (\in_array(Resource::TYPE_TEAM, $resources)) {
            $report[Resource::TYPE_TEAM] = $teams['total'];
        }

        if (\in_array(Resource::TYPE_MEMBERSHIP, $resources)) {
            $report[Resource::TYPE_MEMBERSHIP] = 0;
            foreach ($teams['teams'] as $team) {
                $report[Resource::TYPE_MEMBERSHIP] += $this->teams->listMemberships(
                    $team['$id'],
                    [Query::limit(1)]
                )['total'];
            }
        }
    }

    /**
     * @throws Exception
     * @throws AppwriteException
     */
    private function reportDatabases(array $resources, array &$report, array $resourceIds = []): void
    {
        $this->reader->report($resources, $report, $resourceIds);
    }

    /**
     * @param array $resources
     * @param array $report
     * @param array<string, array<string>> $resourceIds
     * @throws AppwriteException
     */
    private function reportStorage(array $resources, array &$report, array $resourceIds = []): void
    {

        if (\in_array(Resource::TYPE_BUCKET, $resources)) {
            $bucketQueries = $this->buildQueries(
                resourceType: Resource::TYPE_BUCKET,
                resourceIds: $resourceIds,
                limit: 1
            );
            $report[Resource::TYPE_BUCKET] = $this->storage->listBuckets($bucketQueries)['total'];
        }

        if (\in_array(Resource::TYPE_FILE, $resources)) {
            $report[Resource::TYPE_FILE] = 0;
            $report['size'] = 0;
            $buckets = [];
            $lastBucket = null;

            while (true) {
                $queries = $this->buildQueries(
                    resourceType: Resource::TYPE_BUCKET,
                    resourceIds: $resourceIds,
                    cursor: $lastBucket,
                );
                $currentBuckets = $this->storage->listBuckets($queries)['buckets'];

                $buckets = array_merge($buckets, $currentBuckets);
                $lastBucket = $buckets[count($buckets) - 1]['$id'] ?? null;

                if (count($currentBuckets) < self::DEFAULT_PAGE_LIMIT) {
                    break;
                }
            }

            foreach ($buckets as $bucket) {
                $filesResponse = $this->storage->listFiles(
                    $bucket['$id'],
                    [Query::limit(1)]
                );

                $report['size'] += $bucket['totalSize'] ?? 0;
                $report[Resource::TYPE_FILE] += $filesResponse['total'];
            }

            $report['size'] = $report['size'] / 1000 / 1000; // MB
        }
    }

    private function reportFunctions(array $resources, array &$report, array $resourceIds = []): void
    {
        $needVarsOrDeployments = (
            \in_array(Resource::TYPE_DEPLOYMENT, $resources) ||
            \in_array(Resource::TYPE_ENVIRONMENT_VARIABLE, $resources)
        );

        $functions = [];
        $totalFunctions = 0;

        if (!$needVarsOrDeployments && \in_array(Resource::TYPE_FUNCTION, $resources)) {
            $functionQueries = $this->buildQueries(
                resourceType: Resource::TYPE_FUNCTION,
                resourceIds: $resourceIds,
                limit: 1
            );
            $report[Resource::TYPE_FUNCTION] = $this->functions->list($functionQueries)['total'];
            return;
        }

        if ($needVarsOrDeployments) {
            $lastFunction = null;
            while (true) {
                $params = $this->buildQueries(
                    resourceType: Resource::TYPE_FUNCTION,
                    resourceIds: $resourceIds,
                    cursor: $lastFunction,
                );
                $funcList = $this->functions->list($params);

                $totalFunctions = $funcList['total'];
                $currentFunctions = $funcList['functions'];
                $functions = array_merge($functions, $currentFunctions);

                $lastFunction = $currentFunctions[count($currentFunctions) - 1]['$id'] ?? null;
                if (count($currentFunctions) < self::DEFAULT_PAGE_LIMIT) {
                    break;
                }
            }
        }

        if (\in_array(Resource::TYPE_FUNCTION, $resources)) {
            $report[Resource::TYPE_FUNCTION] = $totalFunctions;
        }

        if (\in_array(Resource::TYPE_DEPLOYMENT, $resources)) {
            $report[Resource::TYPE_DEPLOYMENT] = 0;
            foreach ($functions as $function) {
                if (!empty($function['deploymentId'])) {
                    $report[Resource::TYPE_DEPLOYMENT] += 1;
                }
            }
        }

        if (\in_array(Resource::TYPE_ENVIRONMENT_VARIABLE, $resources)) {
            $report[Resource::TYPE_ENVIRONMENT_VARIABLE] = 0;
            foreach ($functions as $function) {
                // function model contains `vars`, we don't need to fetch the list again.
                $report[Resource::TYPE_ENVIRONMENT_VARIABLE] += count($function['vars'] ?? []);
            }
        }
    }

    /**
     * Export Auth Resources
     *
     * @param int $batchSize Max 100
     * @param array<string> $resources
     */
    protected function exportGroupAuth(int $batchSize, array $resources): void
    {
        try {
            if (\in_array(Resource::TYPE_USER, $resources)) {
                $this->exportUsers($batchSize);
            }
        } catch (\Throwable $e) {
            $this->addError(new Exception(
                Resource::TYPE_USER,
                Transfer::GROUP_AUTH,
                message: $e->getMessage(),
                code: $e->getCode(),
                previous: $e
            ));
        }

        try {
            if (\in_array(Resource::TYPE_TEAM, $resources)) {
                $this->exportTeams($batchSize);
            }
        } catch (\Throwable $e) {
            $this->addError(new Exception(
                Resource::TYPE_TEAM,
                Transfer::GROUP_AUTH,
                message: $e->getMessage(),
                code: $e->getCode(),
                previous: $e
            ));
        }

        try {
            if (\in_array(Resource::TYPE_MEMBERSHIP, $resources)) {
                $this->exportMemberships($batchSize);
            }
        } catch (\Throwable $e) {
            $this->addError(new Exception(
                Resource::TYPE_MEMBERSHIP,
                Transfer::GROUP_AUTH,
                message: $e->getMessage(),
                code: $e->getCode(),
                previous: $e
            ));
        }
    }

    /**
     * @throws AppwriteException
     */
    private function exportUsers(int $batchSize): void
    {
        $lastDocument = null;

        while (true) {
            $users = [];

            $queries = [Query::limit($batchSize)];

            if ($this->rootResourceId !== '' && $this->rootResourceType === Resource::TYPE_USER) {
                $queries[] = Query::equal('$id', $this->rootResourceId);
                $queries[] = Query::limit(1);
            }

            if ($lastDocument) {
                $queries[] = Query::cursorAfter($lastDocument);
            }

            $response = $this->users->list($queries);
            if ($response['total'] == 0) {
                break;
            }

            foreach ($response['users'] as $user) {
                $users[] = new User(
                    $user['$id'],
                    empty($user['email']) ? null : $user['email'],
                    empty($user['name']) ? null : $user['name'],
                    $user['password'] ? new Hash($user['password'], algorithm: $user['hash']) : null,
                    empty($user['phone']) ? null : $user['phone'],
                    $user['labels'] ?? [],
                    '',
                    $user['emailVerification'] ?? false,
                    $user['phoneVerification'] ?? false,
                    !$user['status'],
                    $user['prefs'] ?? [],
                );

                $lastDocument = $user['$id'];
            }

            $this->callback($users);

            if (count($users) < $batchSize) {
                break;
            }
        }
    }

    /**
     * @throws AppwriteException
     */
    private function exportTeams(int $batchSize): void
    {
        $this->teams = new Teams($this->client);
        $lastDocument = null;

        while (true) {
            $teams = [];

            $queries = [Query::limit($batchSize)];

            if ($this->rootResourceId !== '' && $this->rootResourceType === Resource::TYPE_TEAM) {
                $queries[] = Query::equal('$id', $this->rootResourceId);
                $queries[] = Query::limit(1);
            }

            if ($lastDocument) {
                $queries[] = Query::cursorAfter($lastDocument);
            }

            $response = $this->teams->list($queries);
            if ($response['total'] == 0) {
                break;
            }

            foreach ($response['teams'] as $team) {
                $teams[] = new Team(
                    $team['$id'],
                    $team['name'],
                    $team['prefs'],
                );

                $lastDocument = $team['$id'];
            }

            $this->callback($teams);

            if (count($teams) < $batchSize) {
                break;
            }
        }
    }

    /**
     * @throws AppwriteException
     * @throws \Exception
     */
    private function exportMemberships(int $batchSize): void
    {
        $cacheTeams = $this->cache->get(Team::getName());

        /** @var array<string, User> $cacheUsers */
        $cacheUsers = [];

        foreach ($this->cache->get(User::getName()) as $cacheUser) {
            $cacheUsers[$cacheUser->getId()] = $cacheUser;
        }

        foreach ($cacheTeams as $team) {
            /** @var Team $team */
            $lastDocument = null;

            while (true) {
                $memberships = [];

                $queries = [Query::limit($batchSize)];

                if ($lastDocument) {
                    $queries[] = Query::cursorAfter($lastDocument);
                }

                $response = $this->teams->listMemberships($team->getId(), $queries);

                if ($response['total'] == 0) {
                    break;
                }

                foreach ($response['memberships'] as $membership) {
                    $user = $cacheUsers[$membership['userId']] ?? null;
                    if ($user === null) {
                        throw new \Exception('User not found');
                    }

                    $memberships[] = new Membership(
                        $membership['$id'],
                        $team,
                        $user,
                        $membership['roles'],
                        $membership['confirm']
                    );

                    $lastDocument = $membership['$id'];
                }

                $this->callback($memberships);

                if (count($memberships) < $batchSize) {
                    break;
                }
            }
        }
    }

    protected function exportGroupDatabases(int $batchSize, array $resources): void
    {
        $handleExportEntityScopedResources = function (string $resourceKey, callable $callback) use ($resources) {
            foreach (Resource::ENTITY_TYPE_RESOURCE_MAP as $entityKey => $entityResource) {
                try {
                    if (\in_array($entityResource[$resourceKey], $resources)) {
                        $callback($entityKey, $entityResource);
                    }
                } catch (\Throwable $e) {
                    $this->addError(
                        new Exception(
                            $resourceKey,
                            Transfer::GROUP_DATABASES,
                            message: $e->getMessage(),
                            code: $e->getCode(),
                            previous: $e
                        )
                    );

                    return false;
                }
            }
            return true;
        };

        try {
            if (Resource::isSupported(array_keys(Resource::DATABASE_TYPE_RESOURCE_MAP), $resources)) {
                $this->exportDatabases($batchSize, $resources);
            }
        } catch (\Throwable $e) {
            $this->addError(
                new Exception(
                    Resource::TYPE_DATABASE,
                    Transfer::GROUP_DATABASES,
                    message: $e->getMessage(),
                    code: $e->getCode(),
                    previous: $e
                )
            );

            return;
        }

        foreach (Resource::DATABASE_TYPE_RESOURCE_MAP as $databaseKey => $databaseResource) {
            try {
                if (\in_array($databaseResource['entity'], $resources)) {
                    $this->exportEntities($databaseKey, $batchSize);
                }
            } catch (\Throwable $e) {
                $this->addError(
                    new Exception(
                        $databaseResource['entity'],
                        Transfer::GROUP_DATABASES,
                        message: $e->getMessage(),
                        code: $e->getCode(),
                        previous: $e
                    )
                );

                return;
            }
        }

        // field
        if (!$handleExportEntityScopedResources('field', fn ($entityKey) => $this->exportFields($entityKey, $batchSize))) {
            return;
        }

        // index
        if (!$handleExportEntityScopedResources('index', fn ($entityKey) => $this->exportIndexes($entityKey, $batchSize))) {
            return;
        }

        // record
        if (!$handleExportEntityScopedResources('record', fn ($entityKey, $entityResource) => $this->exportRecords($entityKey, $entityResource['field'], $batchSize))) {
            return;
        }
    }

    /**
     * @param int $batchSize
     * @param array $resources
     * @throws Exception
     */
    private function exportDatabases(int $batchSize, array $resources = []): void
    {
        $lastDatabase = null;

        while (true) {
            $queries = [$this->reader->queryLimit($batchSize)];

            if ($this->rootResourceId !== '' && ($this->rootResourceType === Resource::TYPE_DATABASE || $this->rootResourceType === Resource::TYPE_DATABASE_DOCUMENTSDB)) {
                $targetDatabaseId = $this->rootResourceId;

                // Handle database:collection format - extract database ID
                if (\str_contains($this->rootResourceId, ':')) {
                    $parts = \explode(':', $this->rootResourceId, 2);
                    if (\count($parts) === 2) {
                        $targetDatabaseId = $parts[0];
                    }
                }

                $queries[] = $this->reader->queryEqual('$id', [$targetDatabaseId]);
                $queries[] = $this->reader->queryLimit(1);
            }

            $databases = [];

            if ($lastDatabase) {
                $queries[] = $this->reader->queryCursorAfter($lastDatabase);
            }

            $response = $this->reader->listDatabases($queries);

            foreach ($response as $database) {
                $databaseType = $database['type'];
                if (in_array($databaseType, [Resource::TYPE_DATABASE_LEGACY,Resource::TYPE_DATABASE_TABLESDB])) {
                    $databaseType = Resource::TYPE_DATABASE;
                }
                if (Resource::isSupported($databaseType, $resources)) {
                    $newDatabase = self::getDatabase($databaseType, [
                        'id' => $database['$id'],
                        'name' => $database['name'],
                        'createdAt' => $database['$createdAt'],
                        'updatedAt' => $database['$updatedAt'],
                        'type' => $databaseType,
                        'database' => $database['database']
                    ]);
                    $databases[] = $newDatabase;

                }
            }

            if (empty($databases)) {
                break;
            }

            $lastDatabase = $databases[\count($databases) - 1];

            $this->callback($databases);

            if (\count($databases) < $batchSize) {
                break;
            }
        }
    }

    /**
     * @param string $databaseName
     * @param int $batchSize
     * @throws Exception
     */
    private function exportEntities(string $databaseName, int $batchSize): void
    {
        $databases = $this->cache->get($databaseName);
        foreach ($databases as $database) {
            /** @var Database $database */
            $lastTable = null;

            while (true) {
                $queries = [$this->reader->queryLimit($batchSize)];
                $tables = [];

                // Filter to specific table if rootResourceType is database with database:collection format
                if (
                    $this->rootResourceId !== '' &&
                    $this->rootResourceType === Resource::TYPE_DATABASE &&
                    \str_contains($this->rootResourceId, ':')
                ) {
                    $parts = \explode(':', $this->rootResourceId, 2);
                    if (\count($parts) === 2) {
                        $targetTableId = $parts[1]; // table ID
                        $queries[] = $this->reader->queryEqual('$id', [$targetTableId]);
                        $queries[] = $this->reader->queryLimit(1);
                    }
                } elseif (
                    $this->rootResourceId !== '' &&
                    $this->rootResourceType === Resource::TYPE_TABLE
                ) {
                    $targetTableId = $this->rootResourceId;
                    $queries[] = $this->reader->queryEqual('$id', [$targetTableId]);
                    $queries[] = $this->reader->queryLimit(1);
                } elseif ($lastTable) {
                    $queries[] = $this->reader->queryCursorAfter($lastTable);
                }

                $response = $this->reader->listTables($database, $queries);
                foreach ($response as $table) {
                    $newTable = self::getEntity($databaseName, [
                        'id' => $table['$id'],
                        'name' => $table['name'],
                        'documentSecurity' => $table['documentSecurity'],
                        'permissions' => $table['$permissions'],
                        'createdAt' => $table['$createdAt'],
                        'updatedAt' => $table['$updatedAt'],
                        'database' => [
                            'id' => $database->getId(),
                            'name' => $databaseName,
                            'type' => $database->getType(),
                            'database' => $database->getDatabase(),
                        ]
                    ]);

                    $tables[] = $newTable;
                }

                if (empty($tables)) {
                    break;
                }

                $this->callback($tables);

                $lastTable = $tables[\count($tables) - 1];

                if (\count($tables) < $batchSize) {
                    break;
                }
            }
        }
    }

    /**
     * @param string $entityType
     * @param int $batchSize
     * @throws Exception
     */
    private function exportFields(string $entityType, int $batchSize): void
    {
        $entities = $this->cache->get($entityType);
        // Transfer Indexes

        /** @var array<Table|Collection> $table */
        foreach ($entities as $table) {
            $lastColumn = null;

            while (true) {
                $queries = [$this->reader->queryLimit($batchSize)];
                $columns = [];

                if ($lastColumn) {
                    $queries[] = $this->reader->queryCursorAfter($lastColumn);
                }

                $response = $this->reader->listColumns($table, $queries);

                foreach ($response as $column) {
                    if (
                        $column['type'] === UtopiaDatabase::VAR_RELATIONSHIP
                        && $column['side'] === UtopiaDatabase::RELATION_SIDE_CHILD
                    ) {
                        continue;
                    }

                    /** @var Table $table */
                    $col = match($table->getDatabase()->getType()) {
                        Resource::TYPE_DATABASE_VECTORDB => self::getAttribute($table, $column),
                        default => self::getColumn($table, $column),
                    };

                    $columns[] = $col;
                }

                if (empty($columns)) {
                    break;
                }

                $this->callback($columns);

                $lastColumn = $columns[count($columns) - 1];

                if (count($columns) < $batchSize) {
                    break;
                }
            }
        }
    }

    /**
     * @param string $entityType
     * @param int $batchSize
     * @throws Exception
     */
    private function exportIndexes(string $entityType, int $batchSize): void
    {
        $entities = $this->cache->get($entityType);
        // Transfer Indexes
        foreach ($entities as $table) {
            /** @var Table $table */
            $lastIndex = null;

            while (true) {
                $queries = [$this->reader->queryLimit($batchSize)];
                $indexes = [];

                if ($lastIndex) {
                    $queries[] = $this->reader->queryCursorAfter($lastIndex);
                }

                $response = $this->reader->listIndexes($table, $queries);

                foreach ($response as $index) {
                    $indexes[] = new Index(
                        $index['$id'],
                        $index['key'],
                        $table,
                        $index['type'],
                        $index['columns'] ?? $index['attributes'],
                        $index['lengths'] ?? [],
                        $index['orders'] ?? [],
                        $index['$createdAt'] = empty($index['$createdAt']) ? UtopiaDateTime::now() : $index['$createdAt'],
                        $index['$updatedAt'] = empty($index['$updatedAt']) ? UtopiaDateTime::now() : $index['$updatedAt'],
                    );
                }

                if (empty($indexes)) {
                    break;
                }

                $this->callback($indexes);

                $lastIndex = $indexes[count($indexes) - 1];

                if (count($indexes) < $batchSize) {
                    break;
                }
            }
        }
    }

    /**
     * @param string $entityName
     * @param string $fieldName
     * @param int $batchSize
     * @throws Exception
     */
    private function exportRecords(string $entityName, string $fieldName, int $batchSize): void
    {
        $entities = $this->cache->get($entityName);

        $this->logDebugTrackedProject("exportRecords started | Entity: $entityName | Tables count: " . count($entities));

        foreach ($entities as $table) {
            /** @var Table $table */
            $lastRow = null;
            $iterationCount = 0;

            $this->logDebugTrackedProject("Starting table export | Table: {$table->getName()} | ID: {$table->getId()}");

            while (true) {
                $iterationCount++;

                $memUsage = round(memory_get_usage(true) / 1024 / 1024, 2);
                $this->logDebugTrackedProject("Table: {$table->getName()} | Iteration: $iterationCount | Memory: {$memUsage}MB | LastRow: " . ($lastRow ? $lastRow->getId() : 'null'));

                $queries = [
                    $this->reader->queryLimit($batchSize),
                    ...$this->queries,
                ];

                $rows = [];

                if ($lastRow) {
                    $queries[] = $this->reader->queryCursorAfter($lastRow);
                }

                $selects = ['*', '$id', '$permissions', '$updatedAt', '$createdAt']; // We want relations flat!
                $manyToMany = [];

                if ($this->reader->getSupportForAttributes()) {
                    $attributes = $this->cache->get($fieldName);

                    foreach ($attributes as $attribute) {
                        /** @var Relationship $attribute */
                        if (
                            $attribute->getTable()->getId() === $table->getId() &&
                            $attribute->getType() === Column::TYPE_RELATIONSHIP &&
                            $attribute->getSide() === 'parent' &&
                            $attribute->getRelationType() == 'manyToMany'
                        ) {
                            $manyToMany[] = $attribute->getKey();
                        }
                    }
                }

                $queries[] = $this->reader->querySelect($selects);

                $timestamp = microtime(true);
                $this->logDebugTrackedProject("BEFORE listRows() | Table: {$table->getName()} | Batch: $batchSize | Timestamp: {$timestamp}");

                $response = $this->reader->listRows($table, $queries);

                $timestamp = microtime(true);
                $this->logDebugTrackedProject("AFTER listRows() | Table: {$table->getName()} | Rows: " . count($response) . " | Timestamp: $timestamp");

                foreach ($response as $row) {
                    // HACK: Handle many to many (only for schema-based databases)
                    if (!empty($manyToMany)) {
                        $stack = ['$id']; // Adding $id because we can't select only relations
                        foreach ($manyToMany as $relation) {
                            $stack[] = $relation . '.$id';
                        }

                        $rowItem = $this->reader->getRow(
                            $table,
                            $row['$id'],
                            [$this->reader->querySelect($stack)]
                        );

                        foreach ($manyToMany as $key) {
                            $row[$key] = [];
                            if (isset($rowItem[$key]) && is_array($rowItem[$key])) {
                                foreach ($rowItem[$key] as $relatedRowItem) {
                                    if (is_array($relatedRowItem) && isset($relatedRowItem['$id'])) {
                                        $row[$key][] = $relatedRowItem['$id'];
                                    }
                                }
                            }
                        }
                    }

                    $id = $row['$id'];
                    $permissions = $row['$permissions'];

                    unset($row['$id']);
                    unset($row['$permissions']);
                    unset($row['$collectionId']);
                    unset($row['$databaseId']);
                    unset($row['$sequence']);
                    unset($row['$collection']);

                    $row = self::getRecord($table->getDatabase()->getDatabaseName(), [
                        'id' => $id,
                        'table' => [
                            'id' => $table->getId(),
                            'name' => $table->getTableName(),
                            'rowSecurity' => $table->getRowSecurity(),
                            'permissions' => $table->getPermissions(),
                            'database' => [
                                'id' => $table->getDatabase()->getId(),
                                'name' => $table->getDatabase()->getDatabaseName(),
                                'type' => $table->getDatabase()->getType(),
                                'database' => $table->getDatabase()->getDatabase(),
                            ]
                        ],
                        'data' => $row,
                        'permissions' => $permissions
                    ]);

                    $rows[] = $row;
                    $lastRow = $row;
                }

                $this->logDebugTrackedProject("Processed rows from response | Table: {$table->getName()} | Rows in batch: " . count($rows));

                $this->logDebugTrackedProject("BEFORE callback() | Table: {$table->getName()} | Rows: " . count($rows));

                $this->callback($rows);

                $this->logDebugTrackedProject("AFTER callback() | Table: {$table->getName()}");

                if (count($response) < $batchSize) {
                    $this->logDebugTrackedProject("Table export completed | Table: {$table->getName()} | Response count: " . count($response) . " < Batch size: $batchSize");
                    break;
                }
            }

            $this->logDebugTrackedProject("Finished table export | Table: {$table->getName()} | Total iterations: {$iterationCount}");
        }

        $this->logDebugTrackedProject("exportRecords completed | Entity: {$entityName}");
    }

    protected function exportGroupStorage(int $batchSize, array $resources): void
    {
        try {
            if (\in_array(Resource::TYPE_BUCKET, $resources)) {
                $this->exportBuckets($batchSize);
            }
        } catch (\Throwable $e) {
            $this->addError(
                new Exception(
                    Resource::TYPE_BUCKET,
                    Transfer::GROUP_STORAGE,
                    message: $e->getMessage(),
                    code: $e->getCode(),
                    previous: $e
                )
            );
        }

        try {
            if (\in_array(Resource::TYPE_FILE, $resources)) {
                $this->exportFiles($batchSize);
            }
        } catch (\Throwable $e) {
            $this->addError(
                new Exception(
                    Resource::TYPE_FILE,
                    Transfer::GROUP_STORAGE,
                    message: $e->getMessage(),
                    code: $e->getCode(),
                    previous: $e
                )
            );
        }
    }

    /**
     * @throws AppwriteException
     */
    private function exportBuckets(int $batchSize): void
    {
        $queries = [];

        if ($this->rootResourceId !== '' && $this->rootResourceType === Resource::TYPE_BUCKET) {
            $queries[] = Query::equal('$id', $this->rootResourceId);
            $queries[] = Query::limit(1);
        }

        $buckets = $this->storage->listBuckets($queries);

        $convertedBuckets = [];

        foreach ($buckets['buckets'] as $bucket) {
            $bucket = new Bucket(
                $bucket['$id'],
                $bucket['name'],
                $bucket['$permissions'],
                $bucket['fileSecurity'],
                $bucket['enabled'],
                $bucket['maximumFileSize'],
                $bucket['allowedFileExtensions'],
                $bucket['compression'],
                $bucket['encryption'],
                $bucket['antivirus'],
            );
            $convertedBuckets[] = $bucket;
        }

        if (empty($convertedBuckets)) {
            return;
        }

        $this->callback($convertedBuckets);
    }

    /**
     * @throws AppwriteException
     */
    private function exportFiles(int $batchSize): void
    {
        $buckets = $this->cache->get(Bucket::getName());

        foreach ($buckets as $bucket) {
            /** @var Bucket $bucket */
            $lastDocument = null;

            while (true) {
                $queries = [Query::limit($batchSize)];

                if ($lastDocument) {
                    $queries[] = Query::cursorAfter($lastDocument);
                }

                $response = $this->storage->listFiles(
                    $bucket->getId(),
                    $queries
                );

                foreach ($response['files'] as $file) {
                    try {
                        $this->exportFileData(new File(
                            $file['$id'],
                            $bucket,
                            $file['name'],
                            $file['signature'],
                            $file['mimeType'],
                            $file['$permissions'],
                            $file['sizeOriginal'],
                        ));
                    } catch (\Throwable $e) {
                        $this->addError(new Exception(
                            resourceName: Resource::TYPE_FILE,
                            resourceGroup: Transfer::GROUP_STORAGE,
                            resourceId: $file['$id'],
                            message: $e->getMessage(),
                            code: $e->getCode()
                        ));
                    }

                    $lastDocument = $file['$id'];
                }

                if (count($response['files']) < $batchSize) {
                    break;
                }
            }
        }
    }

    /**
     * @throws \Exception
     */
    private function exportFileData(File $file): void
    {
        // Set the chunk size (5MB)
        $start = 0;
        $end = Transfer::STORAGE_MAX_CHUNK_SIZE - 1;

        // Get the file size
        $fileSize = $file->getSize();

        if ($end > $fileSize) {
            $end = $fileSize - 1;
        }

        // Loop until the entire file is downloaded
        while ($start < $fileSize) {
            $chunkData = $this->call(
                'GET',
                "/storage/buckets/{$file->getBucket()->getId()}/files/{$file->getId()}/download",
                ['range' => "bytes=$start-$end"]
            );

            // Send the chunk to the callback function
            $file
                ->setData($chunkData)
                ->setStart($start)
                ->setEnd($end);

            $this->callback([$file]);

            // Update the range
            $start += Transfer::STORAGE_MAX_CHUNK_SIZE;
            $end += Transfer::STORAGE_MAX_CHUNK_SIZE;

            if ($end > $fileSize) {
                $end = $fileSize - 1;
            }
        }
    }

    protected function exportGroupFunctions(int $batchSize, array $resources): void
    {
        try {
            if (\in_array(Resource::TYPE_FUNCTION, $resources)) {
                $this->exportFunctions($batchSize);
            }
        } catch (\Throwable $e) {
            $this->addError(new Exception(
                Resource::TYPE_FUNCTION,
                Transfer::GROUP_FUNCTIONS,
                message: $e->getMessage(),
                code: $e->getCode(),
                previous: $e
            ));
        }

        try {
            if (\in_array(Resource::TYPE_DEPLOYMENT, $resources)) {
                $this->exportDeployments($batchSize, true);
            }
        } catch (\Throwable $e) {
            $this->addError(new Exception(
                Resource::TYPE_DEPLOYMENT,
                Transfer::GROUP_FUNCTIONS,
                message: $e->getMessage(),
                code: $e->getCode(),
                previous: $e
            ));
        }
    }

    /**
     * @throws AppwriteException
     */
    private function exportFunctions(int $batchSize): void
    {
        $this->functions = new Functions($this->client);
        /**
         * @var Func|null $lastFunction
         */
        $lastFunction = null;
        while (true) {
            $queries = [Query::limit($batchSize)];

            if ($this->rootResourceId !== '' && $this->rootResourceType === Resource::TYPE_FUNCTION) {
                $queries[] = Query::equal('$id', $this->rootResourceId);
                $queries[] = Query::limit(1);
            }

            if ($lastFunction) {
                $queries[] = Query::cursorAfter($lastFunction->getId());
            }

            $response = $this->functions->list($queries);


            if ($response['total'] === 0) {
                return;
            }

            $functions = [];
            $convertedResources = [];

            foreach ($response['functions'] as $function) {
                $convertedFunc = new Func(
                    $function['$id'],
                    $function['name'],
                    $function['runtime'],
                    $function['execute'],
                    $function['enabled'],
                    $function['events'],
                    $function['schedule'],
                    $function['timeout'],
                    $function['deploymentId'] ?? '',
                    $function['entrypoint']
                );
                $functions[] = $convertedFunc;

                $convertedResources[] = $convertedFunc;

                foreach ($function['vars'] as $var) {
                    $convertedResources[] = new EnvVar(
                        $var['$id'],
                        $convertedFunc,
                        $var['key'],
                        $var['value'],
                    );
                }
            }

            $lastFunction = $functions[count($functions) - 1];

            $this->callback($convertedResources);
            if (count($functions) < $batchSize) {
                return;
            }
        }
    }

    /**
     * @throws AppwriteException
     */
    private function exportDeployments(int $batchSize, bool $exportOnlyActive = false): void
    {
        $this->functions = new Functions($this->client);
        $functions = $this->cache->get(Func::getName());

        foreach ($functions as $func) {
            /** @var Func $func */
            $lastDocument = null;

            if ($exportOnlyActive && $func->getActiveDeployment()) {
                $deployment = $this->functions->getDeployment($func->getId(), $func->getActiveDeployment());

                try {
                    $this->exportDeploymentData($func, $deployment);
                } catch (\Throwable $e) {
                    $func->setStatus(Resource::STATUS_ERROR, $e->getMessage());
                }

                continue;
            }

            while (true) {
                $queries = [Query::limit($batchSize)];

                if ($lastDocument) {
                    $queries[] = Query::cursorAfter($lastDocument);
                }

                $response = $this->functions->listDeployments(
                    $func->getId(),
                    $queries
                );

                foreach ($response['deployments'] as $deployment) {
                    try {
                        $this->exportDeploymentData($func, $deployment);
                    } catch (\Throwable $e) {
                        $func->setStatus(Resource::STATUS_ERROR, $e->getMessage());
                    }

                    $lastDocument = $deployment['$id'];
                }

                if (count($response['deployments']) < $batchSize) {
                    break;
                }
            }
        }
    }

    /**
     * @throws \Exception
     */
    private function exportDeploymentData(Func $func, array $deployment): void
    {
        // Set the chunk size (5MB)
        $start = 0;
        $end = Transfer::STORAGE_MAX_CHUNK_SIZE - 1;

        // Get the file size
        $responseHeaders = [];

        $this->call(
            'HEAD',
            "/functions/{$func->getId()}/deployments/{$deployment['$id']}/download",
            [],
            [],
            $responseHeaders
        );

        // Content-Length header was missing, file is less than max buffer size.
        if (!array_key_exists('Content-Length', $responseHeaders)) {
            $file = $this->call(
                'GET',
                "/functions/{$func->getId()}/deployments/{$deployment['$id']}/download",
                [],
                [],
                $responseHeaders
            );

            $size = mb_strlen($file);

            if ($end > $size) {
                $end = $size - 1;
            }

            $deployment = new Deployment(
                $deployment['$id'],
                $func,
                $size,
                $deployment['entrypoint'],
                $start,
                $end,
                $file,
                $deployment['activate']
            );
            $deployment->setSequence($deployment->getId());

            $this->callback([$deployment]);

            return;
        }

        $fileSize = $responseHeaders['Content-Length'];

        $deployment = new Deployment(
            $deployment['$id'],
            $func,
            $fileSize,
            $deployment['entrypoint'],
            $start,
            $end,
            '',
            $deployment['activate']
        );

        $deployment->setSequence($deployment->getId());

        // Loop until the entire file is downloaded
        while ($start < $fileSize) {
            $chunkData = $this->call(
                'GET',
                "/functions/{$func->getId()}/deployments/{$deployment->getSequence()}/download",
                ['range' => "bytes=$start-$end"]
            );

            // Send the chunk to the callback function
            $deployment
                ->setData($chunkData)
                ->setStart($start)
                ->setEnd($end);

            $this->callback([$deployment]);

            // Update the range
            $start += Transfer::STORAGE_MAX_CHUNK_SIZE;
            $end += Transfer::STORAGE_MAX_CHUNK_SIZE;

            if ($end > $fileSize) {
                $end = $fileSize - 1;
            }
        }
    }

    /**
     * @param string $databaseType
     * @param array $database {
     *     id: string,
     *     name: string,
     *     createdAt: string,
     *     updatedAt: string,
     *     enabled: bool,
     *     originalId: string|null,
     *     database: string
     * }
    */
    public static function getDatabase(string $databaseType, array $database): Resource
    {
        switch ($databaseType) {
            case Resource::TYPE_DATABASE_DOCUMENTSDB:
                return DocumentsDB::fromArray($database);
            case Resource::TYPE_DATABASE_VECTORDB:
                return VectorDB::fromArray($database);
            default:
                return Database::fromArray($database);
        }
    }

    /**
     * eg., tables,collections
     * @param string $databaseType
     * @param array{
     *     database: array{
     *        id: string,
     *        name: string,
     *     },
     *     name: string,
     *     id: string,
     *     documentSecurity?: bool,
     *     rowSecurity?: bool,
     *     permissions: ?array<string>,
     *     createdAt: string,
     *     updatedAt: string,
     *     enabled: bool
     * } $entity
     */
    public static function getEntity(string $databaseType, array $entity): Resource
    {
        switch ($databaseType) {
            case Resource::TYPE_DATABASE_DOCUMENTSDB:
                return Collection::fromArray($entity);
            case Resource::TYPE_DATABASE_VECTORDB:
                return Collection::fromArray($entity);
            default:
                return Table::fromArray($entity);
        }
    }

    /**
     * eg.,documents/attributes
     * @param string $databaseType
     * @param array{
     *     id: string,
     *     collection?: array{
     *         database: array{
     *             id: string,
     *             name: string,
     *         },
     *         name: string,
     *         id: string,
     *         documentSecurity: bool,
     *         permissions: ?array<string>
     *     },
     *     table?: array{
     *         database: array{
     *             id: string,
     *             name: string,
     *         },
     *         name: string,
     *         id: string,
     *         rowSecurity: bool,
     *         permissions: ?array<string>
     *     },
     *     data: array<string, mixed>,
     *     permissions: ?array<string>
     * } $record
     */
    public static function getRecord(string $databaseType, array $record): Resource
    {
        switch ($databaseType) {
            case Resource::TYPE_DATABASE_DOCUMENTSDB:
                return Document::fromArray($record);
            case Resource::TYPE_DATABASE_VECTORDB:
                return Document::fromArray($record);
            default:
                return Row::fromArray($record);
        }
    }

    public static function getColumn(Table $table, mixed $column): Column
    {
        return match ($column['type']) {
            Column::TYPE_STRING => match ($column['format'] ?? '') {
                Column::TYPE_EMAIL => new Email(
                    $column['key'],
                    $table,
                    required: $column['required'],
                    default: $column['default'],
                    array: $column['array'],
                    size: $column['size'] ?? 254,
                    createdAt: $column['$createdAt'] ?? '',
                    updatedAt: $column['$updatedAt'] ?? '',
                ),
                Column::TYPE_ENUM => new Enum(
                    $column['key'],
                    $table,
                    elements: $column['elements'],
                    required: $column['required'],
                    default: $column['default'],
                    array: $column['array'],
                    size: $column['size'] ?? UtopiaDatabase::LENGTH_KEY,
                    createdAt: $column['$createdAt'] ?? '',
                    updatedAt: $column['$updatedAt'] ?? '',
                ),
                Column::TYPE_URL => new URL(
                    $column['key'],
                    $table,
                    required: $column['required'],
                    default: $column['default'],
                    array: $column['array'],
                    size: $column['size'] ?? 2000,
                    createdAt: $column['$createdAt'] ?? '',
                    updatedAt: $column['$updatedAt'] ?? '',
                ),
                Column::TYPE_IP => new IP(
                    $column['key'],
                    $table,
                    required: $column['required'],
                    default: $column['default'],
                    array: $column['array'],
                    size: $column['size'] ?? 39,
                    createdAt: $column['$createdAt'] ?? '',
                    updatedAt: $column['$updatedAt'] ?? '',
                ),
                default => new Text(
                    $column['key'],
                    $table,
                    required: $column['required'],
                    default: $column['default'],
                    array: $column['array'],
                    size: $column['size'] ?? 0,
                    createdAt: $column['$createdAt'] ?? '',
                    updatedAt: $column['$updatedAt'] ?? '',
                ),
            },

            Column::TYPE_BOOLEAN => new Boolean(
                $column['key'],
                $table,
                required: $column['required'],
                default: $column['default'],
                array: $column['array'],
                createdAt: $column['$createdAt'] ?? '',
                updatedAt: $column['$updatedAt'] ?? '',
            ),

            Column::TYPE_INTEGER => new Integer(
                $column['key'],
                $table,
                required: $column['required'],
                default: $column['default'],
                array: $column['array'],
                min: $column['min'] ?? null,
                max: $column['max'] ?? null,
                createdAt: $column['$createdAt'] ?? '',
                updatedAt: $column['$updatedAt'] ?? '',
            ),

            Column::TYPE_FLOAT => new Decimal(
                $column['key'],
                $table,
                required: $column['required'],
                default: $column['default'],
                array: $column['array'],
                min: $column['min'] ?? null,
                max: $column['max'] ?? null,
                createdAt: $column['$createdAt'] ?? '',
                updatedAt: $column['$updatedAt'] ?? '',
            ),

            Column::TYPE_RELATIONSHIP => new Relationship(
                $column['key'],
                $table,
                relatedTable: $column['relatedTable'] ?? $column['relatedCollection'],
                relationType: $column['relationType'],
                twoWay: $column['twoWay'],
                twoWayKey: $column['twoWayKey'],
                onDelete: $column['onDelete'],
                side: $column['side'],
                createdAt: $column['$createdAt'] ?? '',
                updatedAt: $column['$updatedAt'] ?? '',
            ),

            Column::TYPE_DATETIME => new DateTime(
                $column['key'],
                $table,
                required: $column['required'],
                default: $column['default'],
                array: $column['array'],
                createdAt: $column['$createdAt'] ?? '',
                updatedAt: $column['$updatedAt'] ?? '',
            ),

            Column::TYPE_POINT => new Point(
                $column['key'],
                $table,
                required: $column['required'],
                default: $column['default'],
                createdAt: $column['$createdAt'] ?? '',
                updatedAt: $column['$updatedAt'] ?? '',
            ),

            Column::TYPE_LINE => new Line(
                $column['key'],
                $table,
                required: $column['required'],
                default: $column['default'],
                createdAt: $column['$createdAt'] ?? '',
                updatedAt: $column['$updatedAt'] ?? '',
            ),

            Column::TYPE_POLYGON => new Polygon(
                $column['key'],
                $table,
                required: $column['required'],
                default: $column['default'],
                createdAt: $column['$createdAt'] ?? '',
                updatedAt: $column['$updatedAt'] ?? '',
            ),

            Column::TYPE_OBJECT => new ObjectType(
                $column['key'],
                $table,
                required: $column['required'],
                default: $column['default'],
                createdAt: $column['$createdAt'] ?? '',
                updatedAt: $column['$updatedAt'] ?? '',
            ),

            Column::TYPE_VECTOR => new Vector(
                $column['key'],
                $table,
                size: $column['size'],
                required: $column['required'],
                default: $column['default'],
                createdAt: $column['$createdAt'] ?? '',
                updatedAt: $column['$updatedAt'] ?? '',
            ),

            default => throw new \InvalidArgumentException("Unsupported column type: {$column['type']}"),
        };

    }

    public static function getAttribute(Collection $collection, mixed $attribute): Attribute
    {
        return match ($attribute['type']) {
            Attribute::TYPE_STRING => match ($attribute['format'] ?? '') {
                Attribute::TYPE_EMAIL => new AttributeEmail(
                    $attribute['key'],
                    $collection,
                    required: $attribute['required'],
                    default: $attribute['default'],
                    array: $attribute['array'],
                    size: $attribute['size'] ?? 254,
                    createdAt: $attribute['$createdAt'] ?? '',
                    updatedAt: $attribute['$updatedAt'] ?? '',
                ),
                Attribute::TYPE_ENUM => new AttributeEnum(
                    $attribute['key'],
                    $collection,
                    elements: $attribute['elements'],
                    required: $attribute['required'],
                    default: $attribute['default'],
                    array: $attribute['array'],
                    size: $attribute['size'] ?? UtopiaDatabase::LENGTH_KEY,
                    createdAt: $attribute['$createdAt'] ?? '',
                    updatedAt: $attribute['$updatedAt'] ?? '',
                ),
                Attribute::TYPE_URL => new AttributeURL(
                    $attribute['key'],
                    $collection,
                    required: $attribute['required'],
                    default: $attribute['default'],
                    array: $attribute['array'],
                    size: $attribute['size'] ?? 2000,
                    createdAt: $attribute['$createdAt'] ?? '',
                    updatedAt: $attribute['$updatedAt'] ?? '',
                ),
                Attribute::TYPE_IP => new AttributeIP(
                    $attribute['key'],
                    $collection,
                    required: $attribute['required'],
                    default: $attribute['default'],
                    array: $attribute['array'],
                    size: $attribute['size'] ?? 39,
                    createdAt: $attribute['$createdAt'] ?? '',
                    updatedAt: $attribute['$updatedAt'] ?? '',
                ),
                default => new AttributeText(
                    $attribute['key'],
                    $collection,
                    required: $attribute['required'],
                    default: $attribute['default'],
                    array: $attribute['array'],
                    size: $attribute['size'] ?? 0,
                    createdAt: $attribute['$createdAt'] ?? '',
                    updatedAt: $attribute['$updatedAt'] ?? '',
                ),
            },

            Attribute::TYPE_BOOLEAN => new AttributeBoolean(
                $attribute['key'],
                $collection,
                required: $attribute['required'],
                default: $attribute['default'],
                array: $attribute['array'],
                createdAt: $attribute['$createdAt'] ?? '',
                updatedAt: $attribute['$updatedAt'] ?? '',
            ),

            Attribute::TYPE_INTEGER => new AttributeInteger(
                $attribute['key'],
                $collection,
                required: $attribute['required'],
                default: $attribute['default'],
                array: $attribute['array'],
                min: $attribute['min'] ?? null,
                max: $attribute['max'] ?? null,
                createdAt: $attribute['$createdAt'] ?? '',
                updatedAt: $attribute['$updatedAt'] ?? '',
            ),

            Attribute::TYPE_FLOAT => new AttributeDecimal(
                $attribute['key'],
                $collection,
                required: $attribute['required'],
                default: $attribute['default'],
                array: $attribute['array'],
                min: $attribute['min'] ?? null,
                max: $attribute['max'] ?? null,
                createdAt: $attribute['$createdAt'] ?? '',
                updatedAt: $attribute['$updatedAt'] ?? '',
            ),

            Attribute::TYPE_RELATIONSHIP => new AttributeRelationship(
                $attribute['key'],
                $collection,
                relatedTable: $attribute['relatedTable'] ?? $attribute['relatedCollection'],
                relationType: $attribute['relationType'],
                twoWay: $attribute['twoWay'],
                twoWayKey: $attribute['twoWayKey'],
                onDelete: $attribute['onDelete'],
                side: $attribute['side'],
                createdAt: $attribute['$createdAt'] ?? '',
                updatedAt: $attribute['$updatedAt'] ?? '',
            ),

            Attribute::TYPE_DATETIME => new AttributeDateTime(
                $attribute['key'],
                $collection,
                required: $attribute['required'],
                default: $attribute['default'],
                array: $attribute['array'],
                createdAt: $attribute['$createdAt'] ?? '',
                updatedAt: $attribute['$updatedAt'] ?? '',
            ),

            Attribute::TYPE_POINT => new AttributePoint(
                $attribute['key'],
                $collection,
                required: $attribute['required'],
                default: $attribute['default'],
                createdAt: $attribute['$createdAt'] ?? '',
                updatedAt: $attribute['$updatedAt'] ?? '',
            ),

            Attribute::TYPE_LINE => new AttributeLine(
                $attribute['key'],
                $collection,
                required: $attribute['required'],
                default: $attribute['default'],
                createdAt: $attribute['$createdAt'] ?? '',
                updatedAt: $attribute['$updatedAt'] ?? '',
            ),

            Attribute::TYPE_POLYGON => new AttributePolygon(
                $attribute['key'],
                $collection,
                required: $attribute['required'],
                default: $attribute['default'],
                createdAt: $attribute['$createdAt'] ?? '',
                updatedAt: $attribute['$updatedAt'] ?? '',
            ),

            Attribute::TYPE_OBJECT => new AttributeObjectType(
                $attribute['key'],
                $collection,
                required: $attribute['required'],
                default: $attribute['default'],
                createdAt: $attribute['$createdAt'] ?? '',
                updatedAt: $attribute['$updatedAt'] ?? '',
            ),

            Attribute::TYPE_VECTOR => new AttributeVector(
                $attribute['key'],
                $collection,
                size: $attribute['size'],
                required: $attribute['required'],
                default: $attribute['default'],
                createdAt: $attribute['$createdAt'] ?? '',
                updatedAt: $attribute['$updatedAt'] ?? '',
            ),

            default => throw new \InvalidArgumentException("Unsupported attribute type: {$attribute['type']}"),
        };
    }

    /**
     * Build queries with optional filtering by resource IDs
     */
    private function buildQueries(
        string $resourceType,
        array $resourceIds,
        ?string $cursor = null,
        int $limit = self::DEFAULT_PAGE_LIMIT
    ): array {
        $queries = [];

        if (!empty($resourceIds[$resourceType])) {
            $ids = (array) $resourceIds[$resourceType];

            $queries[] = Query::equal('$id', $ids);
        }

        if ($cursor) {
            $queries[] = Query::cursorAfter($cursor);
        } else {
            $queries[] = Query::limit($limit);
        }

        return $queries;
    }
}
