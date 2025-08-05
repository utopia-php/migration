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
use Utopia\Database\Database as UtopiaDatabase;
use Utopia\Database\DateTime as UtopiaDateTime;
use Utopia\Migration\Exception;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Auth\Hash;
use Utopia\Migration\Resources\Auth\Membership;
use Utopia\Migration\Resources\Auth\Team;
use Utopia\Migration\Resources\Auth\User;
use Utopia\Migration\Resources\Database\Column;
use Utopia\Migration\Resources\Database\Columns\Boolean;
use Utopia\Migration\Resources\Database\Columns\DateTime;
use Utopia\Migration\Resources\Database\Columns\Decimal;
use Utopia\Migration\Resources\Database\Columns\Email;
use Utopia\Migration\Resources\Database\Columns\Enum;
use Utopia\Migration\Resources\Database\Columns\Integer;
use Utopia\Migration\Resources\Database\Columns\IP;
use Utopia\Migration\Resources\Database\Columns\Relationship;
use Utopia\Migration\Resources\Database\Columns\Text;
use Utopia\Migration\Resources\Database\Columns\URL;
use Utopia\Migration\Resources\Database\Database;
use Utopia\Migration\Resources\Database\Index;
use Utopia\Migration\Resources\Database\Row;
use Utopia\Migration\Resources\Database\Table;
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

    protected Client $client;

    private Users $users;

    private Teams $teams;

    private Storage $storage;

    private Functions $functions;

    private Reader $database;

    /**
     * @throws \Exception
     */
    public function __construct(
        protected string $project,
        protected string $endpoint,
        protected string $key,
        protected string $source = self::SOURCE_API,
        protected ?UtopiaDatabase $dbForProject = null
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

        switch ($this->source) {
            case static::SOURCE_API:
                $this->database = new APIReader(new Databases($this->client));
                break;
            case static::SOURCE_DATABASE:
                if (\is_null($dbForProject)) {
                    throw new \Exception('Database is required for database source');
                }
                $this->database = new DatabaseReader($dbForProject);
                break;
            default:
                throw new \Exception('Unknown source');
        }
    }

    public static function getName(): string
    {
        return 'Appwrite';
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
     * @return array<string, mixed>
     *
     * @throws \Exception
     */
    public function report(array $resources = []): array
    {
        $report = [];

        if (empty($resources)) {
            $resources = $this->getSupportedResources();
        }

        try {
            $this->reportAuth($resources, $report);
            $this->reportDatabases($resources, $report);
            $this->reportStorage($resources, $report);
            $this->reportFunctions($resources, $report);

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
     * @throws AppwriteException
     */
    private function reportAuth(array $resources, array &$report): void
    {
        // check if we need to fetch teams!
        $needTeams = !empty(array_intersect(
            [Resource::TYPE_TEAM, Resource::TYPE_MEMBERSHIP],
            $resources
        ));

        $pageLimit = 25;
        $teams = ['total' => 0, 'teams' => []];

        if (\in_array(Resource::TYPE_USER, $resources)) {
            $report[Resource::TYPE_USER] = $this->users->list(
                [Query::limit(1)]
            )['total'];
        }

        if ($needTeams) {
            if (\in_array(Resource::TYPE_MEMBERSHIP, $resources)) {
                $allTeams = [];
                $lastTeam = null;

                while (true) {
                    $params = $lastTeam
                        // TODO: should we use offset here?
                        // this, realistically, shouldn't be too much ig
                        ? [Query::cursorAfter($lastTeam)]
                        : [Query::limit($pageLimit)];

                    $teamList = $this->teams->list($params);

                    $totalTeams = $teamList['total'];
                    $currentTeams = $teamList['teams'];

                    $allTeams = array_merge($allTeams, $currentTeams);
                    $lastTeam = $currentTeams[count($currentTeams) - 1]['$id'] ?? null;

                    if (count($currentTeams) < $pageLimit) {
                        break;
                    }
                }
                $teams = ['total' => $totalTeams, 'teams' => $allTeams];
            } else {
                $teamList = $this->teams->list([Query::limit(1)]);
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
    private function reportDatabases(array $resources, array &$report): void
    {
        $this->database->report($resources, $report);
    }

    /**
     * @param array $resources
     * @param array $report
     * @throws AppwriteException
     */
    private function reportStorage(array $resources, array &$report): void
    {
        if (\in_array(Resource::TYPE_BUCKET, $resources)) {
            // just fetch one bucket for the `total`
            $report[Resource::TYPE_BUCKET] = $this->storage->listBuckets([
                Query::limit(1)
            ])['total'];
        }

        $pageLimit = 25;

        if (\in_array(Resource::TYPE_FILE, $resources)) {
            $report[Resource::TYPE_FILE] = 0;
            $report['size'] = 0;
            $buckets = [];
            $lastBucket = null;

            while (true) {
                $currentBuckets = $this->storage->listBuckets(
                    $lastBucket
                        ? [Query::cursorAfter($lastBucket)]
                        : [Query::limit($pageLimit)]
                )['buckets'];

                $buckets = array_merge($buckets, $currentBuckets);
                $lastBucket = $buckets[count($buckets) - 1]['$id'] ?? null;

                if (count($currentBuckets) < $pageLimit) {
                    break;
                }
            }

            foreach ($buckets as $bucket) {
                $lastFile = null;
                while (true) {
                    $files = $this->storage->listFiles(
                        $bucket['$id'],
                        $lastFile
                            ? [Query::cursorAfter($lastFile)]
                            : [Query::limit($pageLimit)]
                    )['files'];

                    $report[Resource::TYPE_FILE] += count($files);
                    foreach ($files as $file) {
                        // already includes the `sizeOriginal`
                        $report['size'] += $file['sizeOriginal'] ?? 0;
                    }

                    $lastFile = $files[count($files) - 1]['$id'] ?? null;

                    if (count($files) < $pageLimit) {
                        break;
                    }
                }
            }

            $report['size'] = $report['size'] / 1000 / 1000; // MB
        }
    }

    private function reportFunctions(array $resources, array &$report): void
    {
        $pageLimit = 25;
        $needVarsOrDeployments = (
            \in_array(Resource::TYPE_DEPLOYMENT, $resources) ||
            \in_array(Resource::TYPE_ENVIRONMENT_VARIABLE, $resources)
        );

        $functions = [];
        $totalFunctions = 0;

        if (!$needVarsOrDeployments && \in_array(Resource::TYPE_FUNCTION, $resources)) {
            // Only function count needed, short-circuit
            $funcList = $this->functions->list([Query::limit(1)]);
            $report[Resource::TYPE_FUNCTION] = $funcList['total'];
            return;
        }

        if ($needVarsOrDeployments) {
            $lastFunction = null;
            while (true) {
                $params = $lastFunction
                    ? [Query::cursorAfter($lastFunction)]
                    : [Query::limit($pageLimit)];

                $funcList = $this->functions->list($params);

                $totalFunctions = $funcList['total'];
                $currentFunctions = $funcList['functions'];
                $functions = array_merge($functions, $currentFunctions);

                $lastFunction = $currentFunctions[count($currentFunctions) - 1]['$id'] ?? null;
                if (count($currentFunctions) < $pageLimit) {
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
        try {
            if (\in_array(Resource::TYPE_DATABASE, $resources)) {
                $this->exportDatabases($batchSize);
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

        try {
            if (Resource::isSupported(Resource::TYPE_TABLE, $resources)) {
                $this->exportTables($batchSize);
            }
        } catch (\Throwable $e) {
            $this->addError(
                new Exception(
                    Resource::TYPE_TABLE,
                    Transfer::GROUP_DATABASES,
                    message: $e->getMessage(),
                    code: $e->getCode(),
                    previous: $e
                )
            );

            return;
        }

        try {
            if (Resource::isSupported(Resource::TYPE_COLUMN, $resources)) {
                $this->exportColumns($batchSize);
            }
        } catch (\Throwable $e) {
            $this->addError(
                new Exception(
                    Resource::TYPE_COLUMN,
                    Transfer::GROUP_DATABASES,
                    message: $e->getMessage(),
                    code: $e->getCode(),
                    previous: $e
                )
            );

            return;
        }

        try {
            if (\in_array(Resource::TYPE_INDEX, $resources)) {
                $this->exportIndexes($batchSize);
            }
        } catch (\Throwable $e) {
            $this->addError(
                new Exception(
                    Resource::TYPE_INDEX,
                    Transfer::GROUP_DATABASES,
                    message: $e->getMessage(),
                    code: $e->getCode(),
                    previous: $e
                )
            );

            return;
        }

        try {
            if (Resource::isSupported(Resource::TYPE_ROW, $resources)) {
                $this->exportRows($batchSize);
            }
        } catch (\Throwable $e) {
            $this->addError(
                new Exception(
                    Resource::TYPE_ROW,
                    Transfer::GROUP_DATABASES,
                    message: $e->getMessage(),
                    code: $e->getCode(),
                    previous: $e
                )
            );

            return;
        }
    }

    /**
     * @param int $batchSize
     * @throws Exception
     */
    private function exportDatabases(int $batchSize): void
    {
        $lastDatabase = null;

        while (true) {
            $queries = [$this->database->queryLimit($batchSize)];

            if ($this->rootResourceId !== '' && $this->rootResourceType === Resource::TYPE_DATABASE) {
                $queries[] = $this->database->queryEqual('$id', [$this->rootResourceId]);
                $queries[] = $this->database->queryLimit(1);
            }

            $databases = [];

            if ($lastDatabase) {
                $queries[] = $this->database->queryCursorAfter($lastDatabase);
            }

            $response = $this->database->listDatabases($queries);

            foreach ($response as $database) {
                $newDatabase = new Database(
                    $database['$id'],
                    $database['name'],
                    $database['$createdAt'],
                    $database['$updatedAt'],
                    type:$database['type']
                );

                $databases[] = $newDatabase;
            }

            if (empty($databases)) {
                break;
            }

            $lastDatabase = $databases[count($databases) - 1];

            $this->callback($databases);

            if (count($databases) < $batchSize) {
                break;
            }
        }
    }

    /**
     * @param int $batchSize
     * @throws Exception
     */
    private function exportTables(int $batchSize): void
    {
        $databases = $this->cache->get(Database::getName());

        foreach ($databases as $database) {
            $lastTable = null;

            /** @var Database $database */
            while (true) {
                $queries = [$this->database->queryLimit($batchSize)];
                $tables = [];

                if ($lastTable) {
                    $queries[] = $this->database->queryCursorAfter($lastTable);
                }

                $response = $this->database->listTables($database, $queries);

                foreach ($response as $table) {
                    $newTable = new Table(
                        $database,
                        $table['name'],
                        $table['$id'],
                        $table['documentSecurity'],
                        $table['$permissions'],
                        $table['$createdAt'],
                        $table['$updatedAt'],
                    );

                    $tables[] = $newTable;
                }

                if (empty($tables)) {
                    break;
                }

                $this->callback($tables);

                $lastTable = $tables[count($tables) - 1];

                if (count($tables) < $batchSize) {
                    break;
                }
            }
        }
    }

    /**
     * @param int $batchSize
     * @throws Exception
     */
    private function exportColumns(int $batchSize): void
    {
        $tables = $this->cache->get(Table::getName());

        /** @var Table[] $tables */
        foreach ($tables as $table) {
            $lastColumn = null;

            while (true) {
                $queries = [$this->database->queryLimit($batchSize)];
                $columns = [];

                if ($lastColumn) {
                    $queries[] = $this->database->queryCursorAfter($lastColumn);
                }

                $response = $this->database->listColumns($table, $queries);

                foreach ($response as $column) {
                    if (
                        $column['type'] === UtopiaDatabase::VAR_RELATIONSHIP
                        && $column['side'] === UtopiaDatabase::RELATION_SIDE_CHILD
                    ) {
                        continue;
                    }

                    switch ($column['type']) {
                        case Column::TYPE_STRING:
                            $col = match ($column['format'] ?? '') {
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
                            };

                            break;
                        case Column::TYPE_BOOLEAN:
                            $col = new Boolean(
                                $column['key'],
                                $table,
                                required: $column['required'],
                                default: $column['default'],
                                array: $column['array'],
                                createdAt: $column['$createdAt'] ?? '',
                                updatedAt: $column['$updatedAt'] ?? '',
                            );
                            break;
                        case Column::TYPE_INTEGER:
                            $col = new Integer(
                                $column['key'],
                                $table,
                                required: $column['required'],
                                default: $column['default'],
                                array: $column['array'],
                                min: $column['min'] ?? null,
                                max: $column['max'] ?? null,
                                createdAt: $column['$createdAt'] ?? '',
                                updatedAt: $column['$updatedAt'] ?? '',
                            );
                            break;
                        case Column::TYPE_FLOAT:
                            $col = new Decimal(
                                $column['key'],
                                $table,
                                required: $column['required'],
                                default: $column['default'],
                                array: $column['array'],
                                min: $column['min'] ?? null,
                                max: $column['max'] ?? null,
                                createdAt: $column['$createdAt'] ?? '',
                                updatedAt: $column['$updatedAt'] ?? '',
                            );
                            break;
                        case Column::TYPE_RELATIONSHIP:
                            $col = new Relationship(
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
                            );
                            break;
                        case Column::TYPE_DATETIME:
                            $col = new DateTime(
                                $column['key'],
                                $table,
                                required: $column['required'],
                                default: $column['default'],
                                array: $column['array'],
                                createdAt: $column['$createdAt'] ?? '',
                                updatedAt: $column['$updatedAt'] ?? '',
                            );
                            break;
                    }

                    if (!isset($col)) {
                        throw new Exception(
                            resourceName: Resource::TYPE_COLUMN,
                            resourceGroup: Transfer::GROUP_DATABASES,
                            resourceId: $column['$id'],
                            message: 'Unknown column type: ' . $column['type']
                        );
                    }

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
     * @param int $batchSize
     * @throws Exception
     */
    private function exportIndexes(int $batchSize): void
    {
        $tables = $this->cache->get(Resource::TYPE_TABLE);

        // Transfer Indexes
        foreach ($tables as $table) {
            /** @var Table $table */
            $lastIndex = null;

            while (true) {
                $queries = [$this->database->queryLimit($batchSize)];
                $indexes = [];

                if ($lastIndex) {
                    $queries[] = $this->database->queryCursorAfter($lastIndex);
                }

                $response = $this->database->listIndexes($table, $queries);

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
     * @throws Exception
     */
    private function exportRows(int $batchSize): void
    {
        $tables = $this->cache->get(Table::getName());

        foreach ($tables as $table) {
            /** @var Table $table */
            $lastRow = null;

            while (true) {
                $queries = [$this->database->queryLimit($batchSize)];

                $rows = [];

                if ($lastRow) {
                    $queries[] = $this->database->queryCursorAfter($lastRow);
                }

                $selects = ['*', '$id', '$permissions', '$updatedAt', '$createdAt']; // We want relations flat!
                $manyToMany = [];

                $attributes = $this->cache->get(Column::getName());
                foreach ($attributes as $attribute) {
                    /** @var Relationship $attribute */
                    if (
                        $attribute->getTable()->getId() === $table->getId() &&
                        $attribute->getType() === Column::TYPE_RELATIONSHIP &&
                        $attribute->getSide() === 'parent' &&
                        $attribute->getRelationType() == 'manyToMany'
                    ) {
                        /**
                         * Blockers:
                         * we should use but Does not work properly:
                         * $selects[] = $attribute->getKey() . '.$id';
                         * when selecting for a relation we get all relations not just the one we were asking.
                         * when selecting for a relation like select(*, relation.$id) , all relations get resolve
                         */
                        $manyToMany[] = $attribute->getKey();
                    }
                }
                /** @var Column|Relationship $attribute */

                $queries[] = $this->database->querySelect($selects);

                $response = $this->database->listRows($table, $queries);

                foreach ($response as $row) {
                    // HACK: Handle many to many
                    if (!empty($manyToMany)) {
                        $stack = ['$id']; // Adding $id because we can't select only relations
                        foreach ($manyToMany as $relation) {
                            $stack[] = $relation . '.$id';
                        }

                        $rowItem = $this->database->getRow(
                            $table,
                            $row['$id'],
                            [$this->database->querySelect($stack)]
                        );

                        foreach ($manyToMany as $key) {
                            $row[$key] = [];
                            foreach ($rowItem[$key] as $relatedRowItem) {
                                $row[$key][] = $relatedRowItem['$id'];
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

                    $row = new Row(
                        $id,
                        $table,
                        $row,
                        $permissions
                    );

                    $rows[] = $row;
                    $lastRow = $row;
                }

                $this->callback($rows);

                if (count($response) < $batchSize) {
                    break;
                }
            }
        }
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

        try {
            if (in_array(Resource::TYPE_BUCKET, $resources)) {
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

        $queries = [];

        if ($this->rootResourceId !== '' && $this->rootResourceType === Resource::TYPE_FUNCTION) {
            $queries[] = Query::equal('$id', $this->rootResourceId);
            $queries[] = Query::limit(1);
        }

        $functions = $this->functions->list($queries);

        if ($functions['total'] === 0) {
            return;
        }

        $convertedResources = [];

        foreach ($functions['functions'] as $function) {
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

        $this->callback($convertedResources);
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
}
