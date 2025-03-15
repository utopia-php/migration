<?php

namespace Utopia\Migration\Sources;

use Appwrite\AppwriteException;
use Appwrite\Client;
use Appwrite\Query;
use Appwrite\Services\Functions;
use Appwrite\Services\Storage;
use Appwrite\Services\Teams;
use Appwrite\Services\Users;
use Utopia\Database\Database as UtopiaDatabase;
use Utopia\Database\Exception as DatabaseException;
use Utopia\Migration\Exception;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Auth\Hash;
use Utopia\Migration\Resources\Auth\Membership;
use Utopia\Migration\Resources\Auth\Team;
use Utopia\Migration\Resources\Auth\User;
use Utopia\Migration\Resources\Database\Attribute;
use Utopia\Migration\Resources\Database\Attributes\Boolean;
use Utopia\Migration\Resources\Database\Attributes\DateTime;
use Utopia\Migration\Resources\Database\Attributes\Decimal;
use Utopia\Migration\Resources\Database\Attributes\Email;
use Utopia\Migration\Resources\Database\Attributes\Enum;
use Utopia\Migration\Resources\Database\Attributes\Integer;
use Utopia\Migration\Resources\Database\Attributes\IP;
use Utopia\Migration\Resources\Database\Attributes\Relationship;
use Utopia\Migration\Resources\Database\Attributes\Text;
use Utopia\Migration\Resources\Database\Attributes\URL;
use Utopia\Migration\Resources\Database\Collection;
use Utopia\Migration\Resources\Database\Database;
use Utopia\Migration\Resources\Database\Document;
use Utopia\Migration\Resources\Database\Index;
use Utopia\Migration\Resources\Functions\Deployment;
use Utopia\Migration\Resources\Functions\EnvVar;
use Utopia\Migration\Resources\Functions\Func;
use Utopia\Migration\Resources\Storage\Bucket;
use Utopia\Migration\Resources\Storage\File;
use Utopia\Migration\Source;
use Utopia\Migration\Transfer;

class Appwrite extends Source
{
    protected Client $client;

    private Users $users;

    private Teams $teams;

    private Storage $storage;

    private Functions $functions;

    public function __construct(
        protected string $project,
        protected string $endpoint,
        protected string $key,
        protected DataSource $dataSource,
        protected ?UtopiaDatabase $dbForProject = null
    )
    {
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
            Resource::TYPE_COLLECTION,
            Resource::TYPE_ATTRIBUTE,
            Resource::TYPE_INDEX,
            Resource::TYPE_DOCUMENT,

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

        // Auth
        try {
            $scope = 'users.read';
            if (\in_array(Resource::TYPE_USER, $resources)) {
                $report[Resource::TYPE_USER] = $this->users->list()['total'];
            }

            $scope = 'teams.read';
            if (\in_array(Resource::TYPE_TEAM, $resources)) {
                $report[Resource::TYPE_TEAM] = $this->teams->list()['total'];
            }

            if (\in_array(Resource::TYPE_MEMBERSHIP, $resources)) {
                $report[Resource::TYPE_MEMBERSHIP] = 0;
                $teams = $this->teams->list()['teams'];
                foreach ($teams as $team) {
                    $report[Resource::TYPE_MEMBERSHIP] += $this->teams->listMemberships(
                        $team['$id'],
                        [Query::limit(1)]
                    )['total'];
                }
            }

            // Databases
            $scope = 'databases.read';
            if (\in_array(Resource::TYPE_DATABASE, $resources)) {
                $report[Resource::TYPE_DATABASE] = $this->countResources('databases');
            }

            $scope = 'collections.read';
            if (\in_array(Resource::TYPE_COLLECTION, $resources)) {
                $report[Resource::TYPE_COLLECTION] = 0;
                $databases = $this->listDatabases();
                foreach ($databases as $database) {
                    $collectionId = "database_{$database->getInternalId()}";

                    $report[Resource::TYPE_COLLECTION] += $this->countResources($collectionId);
                }
            }

            $scope = 'documents.read';
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

            $scope = 'attributes.read';
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

            $scope = 'indexes.read';
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

            // Storage
            $scope = 'buckets.read';
            if (\in_array(Resource::TYPE_BUCKET, $resources)) {
                $report[Resource::TYPE_BUCKET] = $this->storage->listBuckets()['total'];
            }

            $scope = 'files.read';
            if (\in_array(Resource::TYPE_FILE, $resources)) {
                $report[Resource::TYPE_FILE] = 0;
                $report['size'] = 0;
                $buckets = [];
                $lastBucket = null;

                while (true) {
                    $currentBuckets = $this->storage->listBuckets(
                        $lastBucket
                            ? [Query::cursorAfter($lastBucket)]
                            : [Query::limit(20)]
                    )['buckets'];

                    $buckets = array_merge($buckets, $currentBuckets);
                    $lastBucket = $buckets[count($buckets) - 1]['$id'] ?? null;

                    if (count($currentBuckets) < 20) {
                        break;
                    }
                }

                foreach ($buckets as $bucket) {
                    $files = [];
                    $lastFile = null;

                    while (true) {
                        $currentFiles = $this->storage->listFiles(
                            $bucket['$id'],
                            $lastFile
                                ? [Query::cursorAfter($lastFile)]
                                : [Query::limit(20)]
                        )['files'];

                        $files = array_merge($files, $currentFiles);
                        $lastFile = $files[count($files) - 1]['$id'] ?? null;

                        if (count($currentFiles) < 20) {
                            break;
                        }
                    }

                    $report[Resource::TYPE_FILE] += count($files);
                    foreach ($files as $file) {
                        $report['size'] += $this->storage->getFile(
                            $bucket['$id'],
                            $file['$id']
                        )['sizeOriginal'];
                    }
                }
                $report['size'] = $report['size'] / 1000 / 1000; // MB
            }

            // Functions
            $scope = 'functions.read';
            if (\in_array(Resource::TYPE_FUNCTION, $resources)) {
                $report[Resource::TYPE_FUNCTION] = $this->functions->list()['total'];
            }

            if (\in_array(Resource::TYPE_DEPLOYMENT, $resources)) {
                $report[Resource::TYPE_DEPLOYMENT] = 0;
                $functions = $this->functions->list()['functions'];
                foreach ($functions as $function) {
                    if (!empty($function['deployment'])) {
                        $report[Resource::TYPE_DEPLOYMENT] += 1;
                    }
                }
            }

            if (\in_array(Resource::TYPE_ENVIRONMENT_VARIABLE, $resources)) {
                $report[Resource::TYPE_ENVIRONMENT_VARIABLE] = 0;
                $functions = $this->functions->list()['functions'];
                foreach ($functions as $function) {
                    $report[Resource::TYPE_ENVIRONMENT_VARIABLE] += $this->functions->listVariables($function['$id'])['total'];
                }
            }

            $report['version'] = $this->call(
                'GET',
                '/health/version',
                [
                    'X-Appwrite-Key' => '',
                    'X-Appwrite-Project' => '',
                ]
            )['version'];

            $this->previousReport = $report;

            return $report;
        } catch (\Throwable $e) {
            if ($e->getCode() === 403) {
                throw new \Exception("Missing scope: $scope.");
            } else {
                throw new \Exception($e->getMessage(), previous: $e);
            }
        }
    }

    /**
     * Export Auth Resources
     *
     * @param  int  $batchSize  Max 100
     * @param  array<string>  $resources
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
                    ! $user['status'],
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
            if (\in_array(Resource::TYPE_COLLECTION, $resources)) {
                $this->exportCollections($batchSize);
            }
        } catch (\Throwable $e) {
            $this->addError(
                new Exception(
                    Resource::TYPE_COLLECTION,
                    Transfer::GROUP_DATABASES,
                    message: $e->getMessage(),
                    code: $e->getCode(),
                    previous: $e
                )
            );

            return;
        }

        try {
            if (\in_array(Resource::TYPE_ATTRIBUTE, $resources)) {
                $this->exportAttributes($batchSize);
            }
        } catch (\Throwable $e) {
            $this->addError(
                new Exception(
                    Resource::TYPE_ATTRIBUTE,
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
            if (\in_array(Resource::TYPE_DOCUMENT, $resources)) {
                $this->exportDocuments($batchSize);
            }
        } catch (\Throwable $e) {
            $this->addError(
                new Exception(
                    Resource::TYPE_DOCUMENT,
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
            $queries = [Query::limit($batchSize)];

            if ($this->rootResourceId !== '' && $this->rootResourceType === Resource::TYPE_DATABASE) {
                $queries[] = Query::equal('$id', $this->rootResourceId);
                $queries[] = Query::limit(1);
            }

            $databases = [];

            if ($lastDatabase) {
                $queries[] = Query::cursorAfter($lastDatabase);
            }

            $response = $this->listDatabases($queries);

            foreach ($response as $database) {
                $newDatabase = new Database(
                    $database['$id'],
                    $database['name'],
                    $database['$createdAt'],
                    $database['$updatedAt'],
                );

                $databases[] = $newDatabase;
            }

            if (empty($databases)) {
                break;
            }

            $lastDatabase = $databases[count($databases) - 1]->getId();

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
    private function exportCollections(int $batchSize): void
    {
        $databases = $this->cache->get(Database::getName());

        foreach ($databases as $database) {
            $lastCollection = null;

            /** @var Database $database */
            while (true) {
                $queries = [Query::limit($batchSize)];
                $collections = [];

                if ($lastCollection) {
                    $queries[] = Query::cursorAfter($lastCollection);
                }

                $response = $this->listCollections($database, $queries);

                foreach ($response as $collection) {
                    $newCollection = new Collection(
                        $database,
                        $collection['name'],
                        $collection['$id'],
                        $collection['documentSecurity'],
                        $collection['$permissions'],
                        $collection['$createdAt'],
                        $collection['$updatedAt'],
                    );

                    $collections[] = $newCollection;
                }

                if (empty($collections)) {
                    break;
                }

                $this->callback($collections);

                $lastCollection = $collections[count($collections) - 1]->getId();

                if (count($collections) < $batchSize) {
                    break;
                }
            }
        }
    }


    /**
     * @param int $batchSize
     * @throws Exception
     */
    private function exportAttributes(int $batchSize): void
    {
        $collections = $this->cache->get(Collection::getName());
        /** @var Collection[] $collections */
        foreach ($collections as $collection) {
            $lastAttribute = null;

            while (true) {
                $queries = [Query::limit($batchSize)];
                $attributes = [];

                if ($lastAttribute) {
                    $queries[] = Query::cursorAfter($lastAttribute);
                }

                $response = $this->listAttributes($collection, $queries);

                foreach ($response as $attribute) {
                    if (
                        $attribute['type'] === UtopiaDatabase::VAR_RELATIONSHIP
                        && $attribute['side'] === UtopiaDatabase::RELATION_SIDE_CHILD
                    ) {
                        continue;
                    }

                    switch ($attribute['type']) {
                        case Attribute::TYPE_STRING:
                            $attr = match ($attribute['format']) {
                                Attribute::TYPE_EMAIL => new Email(
                                    $attribute['key'],
                                    $collection,
                                    required: $attribute['required'],
                                    default: $attribute['default'],
                                    array: $attribute['array'],
                                    size: $attribute['size'] ?? 254,
                                    createdAt: $attribute['$createdAt'] ?? '',
                                    updatedAt: $attribute['$updatedAt'] ?? '',
                                ),
                                Attribute::TYPE_ENUM => new Enum(
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
                                Attribute::TYPE_URL => new URL(
                                    $attribute['key'],
                                    $collection,
                                    required: $attribute['required'],
                                    default: $attribute['default'],
                                    array: $attribute['array'],
                                    size: $attribute['size'] ?? 2000,
                                    createdAt: $attribute['$createdAt'] ?? '',
                                    updatedAt: $attribute['$updatedAt'] ?? '',
                                ),
                                Attribute::TYPE_IP => new IP(
                                    $attribute['key'],
                                    $collection,
                                    required: $attribute['required'],
                                    default: $attribute['default'],
                                    array: $attribute['array'],
                                    size: $attribute['size'] ?? 39,
                                    createdAt: $attribute['$createdAt'] ?? '',
                                    updatedAt: $attribute['$updatedAt'] ?? '',
                                ),
                                default => new Text(
                                    $attribute['key'],
                                    $collection,
                                    required: $attribute['required'],
                                    default: $attribute['default'],
                                    array: $attribute['array'],
                                    size: $attribute['size'] ?? 0,
                                    createdAt: $attribute['$createdAt'] ?? '',
                                    updatedAt: $attribute['$updatedAt'] ?? '',
                                ),
                            };

                            break;
                        case Attribute::TYPE_BOOLEAN:
                            $attr = new Boolean(
                                $attribute['key'],
                                $collection,
                                required: $attribute['required'],
                                default: $attribute['default'],
                                array: $attribute['array'],
                                createdAt: $attribute['$createdAt'] ?? '',
                                updatedAt: $attribute['$updatedAt'] ?? '',
                            );
                            break;
                        case Attribute::TYPE_INTEGER:
                            $attr = new Integer(
                                $attribute['key'],
                                $collection,
                                required: $attribute['required'],
                                default: $attribute['default'],
                                array: $attribute['array'],
                                min: $attribute['min'] ?? null,
                                max: $attribute['max'] ?? null,
                                createdAt: $attribute['$createdAt'] ?? '',
                                updatedAt: $attribute['$updatedAt'] ?? '',
                            );
                            break;
                        case Attribute::TYPE_FLOAT:
                            $attr = new Decimal(
                                $attribute['key'],
                                $collection,
                                required: $attribute['required'],
                                default: $attribute['default'],
                                array: $attribute['array'],
                                min: $attribute['min'] ?? null,
                                max: $attribute['max'] ?? null,
                                createdAt: $attribute['$createdAt'] ?? '',
                                updatedAt: $attribute['$updatedAt'] ?? '',
                            );
                            break;
                        case Attribute::TYPE_RELATIONSHIP:
                            $attr = new Relationship(
                                $attribute['key'],
                                $collection,
                                relatedCollection: $attribute['relatedCollection'],
                                relationType: $attribute['relationType'],
                                twoWay: $attribute['twoWay'],
                                twoWayKey: $attribute['twoWayKey'],
                                onDelete: $attribute['onDelete'],
                                side: $attribute['side'],
                                createdAt: $attribute['$createdAt'] ?? '',
                                updatedAt: $attribute['$updatedAt'] ?? '',
                            );
                            break;
                        case Attribute::TYPE_DATETIME:
                            $attr = new DateTime(
                                $attribute['key'],
                                $collection,
                                required: $attribute['required'],
                                default: $attribute['default'],
                                array: $attribute['array'],
                                createdAt: $attribute['$createdAt'] ?? '',
                                updatedAt: $attribute['$updatedAt'] ?? '',
                            );
                            break;
                    }

                    if (!isset($attr)) {
                        throw new Exception(
                            resourceName: Resource::TYPE_ATTRIBUTE,
                            resourceGroup: Transfer::GROUP_DATABASES,
                            resourceId: $attribute['$id'],
                            message: 'Unknown attribute type: ' . $attribute['type']
                        );
                    }

                    $attributes[] = $attr;
                }

                if (empty($attributes)) {
                    break;
                }

                $this->callback($attributes);

                $lastAttribute = $attributes[count($attributes) - 1]->getId();

                if (count($attributes) < $batchSize) {
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
        $collections = $this->cache->get(Resource::TYPE_COLLECTION);

        // Transfer Indexes
        foreach ($collections as $collection) {
            /** @var Collection $collection */
            $lastIndex = null;

            while (true) {
                $queries = [Query::limit($batchSize)];
                $indexes = [];

                if ($lastIndex) {
                    $queries[] = Query::cursorAfter($lastIndex);
                }

                $response = $this->listIndexes($collection, $queries);

                foreach ($response['indexes'] as $index) {
                    $indexes[] = new Index(
                        'unique()',
                        $index['key'],
                        $collection,
                        $index['type'],
                        $index['attributes'],
                        [],
                        $index['orders'],
                        $index['$createdAt'],
                        $index['$updatedAt'],
                    );
                }

                if (empty($indexes)) {
                    break;
                }

                $this->callback($indexes);

                $lastIndex = $indexes[count($indexes) - 1]->getId();

                if (count($indexes) < $batchSize) {
                    break;
                }
            }
        }
    }

    /**
     * @throws Exception
     */
    private function exportDocuments(int $batchSize): void
    {
        $collections = $this->cache->get(Collection::getName());

        foreach ($collections as $collection) {
            /** @var Collection $collection */
            $lastDocument = null;

            while (true) {
                $queries = [Query::limit($batchSize)];

                $documents = [];

                if ($lastDocument) {
                    $queries[] = Query::cursorAfter($lastDocument);
                }

                $selects = ['*', '$id', '$permissions', '$updatedAt', '$createdAt']; // We want relations flat!
                $manyToMany = [];

                $attributes = $this->cache->get(Attribute::getName());
                foreach ($attributes as $attribute) {
                    /** @var Relationship $attribute */
                    if (
                        $attribute->getCollection()->getId() === $collection->getId() &&
                        $attribute->getType() === Attribute::TYPE_RELATIONSHIP &&
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
                /** @var Attribute|Relationship $attribute */

                $queries[] = Query::select($selects);

                $response = $this->listDocuments($collection, $queries);

                foreach ($response as $document) {
                    // HACK: Handle many to many
                    if (! empty($manyToMany)) {
                        $stack = ['$id']; // Adding $id because we can't select only relations
                        foreach ($manyToMany as $relation) {
                            $stack[] = $relation.'.$id';
                        }

                        $doc = $this->db->getDocument(
                            $collection->getId(),
                            $document['$id'],
                            [Query::select($stack)]
                        );

                        foreach ($manyToMany as $key) {
                            $document[$key] = [];
                            foreach ($doc[$key] as $relationDocument) {
                                $document[$key][] = $relationDocument['$id'];
                            }
                        }
                    }

                    $id = $document['$id'];
                    $permissions = $document['$permissions'];

                    unset($document['$id']);
                    unset($document['$permissions']);
                    unset($document['$collectionId']);
                    unset($document['$databaseId']);

                    $documents[] = new Document(
                        $id,
                        $collection,
                        $document,
                        $permissions
                    );

                    $lastDocument = $id;
                }

                $this->callback($documents);

                if (count($response) < $batchSize) {
                    break;
                }
            }
        }
    }

    /**
     * @throws Exception
     *
     * @returns array<Document>
     */
    private function listDatabases(array $queries = []): array
    {
        try {
            return $this->db->find('databases', $queries);
        } catch (DatabaseException $e) {
            throw new Exception(
                resourceName: 'databases',
                resourceGroup: Transfer::GROUP_DATABASES,
                message: $e->getMessage(),
                code: $e->getCode(),
                previous: $e
            );
        }
    }

    /**
     * @throws Exception
     *
     * @returns array<Document>
     */
    private function listCollections(Database $resource, array $queries = []): array
    {
        try {
            $database = $this->db->getDocument('databases', $resource->getId());
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
            return $this->db->find(
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

    /**
     * @param Collection $resource
     * @param array $queries
     * @return array
     * @throws DatabaseException
     * @throws Exception
     */
    private function listAttributes(Collection $resource, array $queries = []): array
    {
        $database = $this->db->getDocument(
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

        $collection = $this->db->getDocument(
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
            return $this->db->find('attributes', $queries);
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

    /**
     * @param Collection $resource
     * @param array $queries
     * @return array
     * @throws DatabaseException
     * @throws Exception
     */
    private function listIndexes(Collection $resource, array $queries = []): array
    {
        $database = $this->db->getDocument(
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

        $collection = $this->db->getDocument(
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
            return $this->db->find('indexes', $queries);
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

    private function listDocuments(Collection $resource, array $queries = []): array
    {
        $database = $this->db->getDocument(
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

        $collection = $this->db->getDocument(
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
            return $this->db->find($collectionId, $queries);
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

    /**
     * @param string $collection
     * @param array $queries
     * @return int
     * @throws Exception
     */
    private function countResources(string $collection, array $queries = []): int
    {
        try {
            return $this->db->count($collection, $queries);
        } catch (DatabaseException $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
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
                $function['deployment'],
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
        if (! array_key_exists('Content-Length', $responseHeaders)) {
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
            $deployment->setInternalId($deployment->getId());

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

        $deployment->setInternalId($deployment->getId());

        // Loop until the entire file is downloaded
        while ($start < $fileSize) {
            $chunkData = $this->call(
                'GET',
                "/functions/{$func->getId()}/deployments/{$deployment->getInternalId()}/download",
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

    public function getBatchSize(): int
    {
        return 500;
    }
}
