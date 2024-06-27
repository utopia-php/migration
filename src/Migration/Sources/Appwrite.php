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
    private Databases $database;
    private Storage $storage;
    private Functions $functions;


    public function __construct(
        protected string $project,
        string $endpoint,
        protected string $key
    ) {
        $this->client = (new Client())
            ->setEndpoint($endpoint)
            ->setProject($project)
            ->setKey($key);

        $this->users = new Users($this->client);
        $this->teams = new Teams($this->client);
        $this->database = new Databases($this->client);
        $this->storage = new Storage($this->client);
        $this->functions = new Functions($this->client);

        $this->endpoint = $endpoint;

        $this->headers['X-Appwrite-Project'] = $this->project;
        $this->headers['X-Appwrite-Key'] = $this->key;
    }

    public static function getName(): string
    {
        return 'Appwrite';
    }

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
     * @param  array<string>  $resources
     * @return array<string, mixed>
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
                $report[Resource::TYPE_DATABASE] = $this->database->list()['total'];
            }

            $scope = 'collections.read';
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

            $scope = 'documents.read';
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

            $scope = 'attributes.read';
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

            $scope = 'indexes.read';
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
                        $lastFile = $files[count($files) - 1]['$id'];

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
                    if (! empty($function['deployment'])) {
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
                    'X-Appwrite-Project' => ''
                ]
            )['version'];

            $this->previousReport = $report;

            return $report;
        } catch (\Throwable $e) {
            if ($e->getCode() === 403) {
                throw new \Exception("Missing scope: $scope.");
            } else {
                throw new \Exception($e->getMessage());
            }
        }
    }

    /**
     * Export Auth Resources
     *
     * @param  int  $batchSize  Max 100
     * @param  array<string>  $resources
     * @return void
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
                $e->getMessage()
            ));
        }

        try {
            if (\in_array(Resource::TYPE_TEAM, $resources)) {
                $this->exportTeams($batchSize);
            }
        } catch (\Throwable $e) {
            $this->addError(new Exception(
                Resource::TYPE_TEAM,
                $e->getMessage()
            ));
        }

        try {
            if (\in_array(Resource::TYPE_MEMBERSHIP, $resources)) {
                $this->exportMemberships($batchSize);
            }
        } catch (\Throwable $e) {
            $this->addError(new Exception(
                Resource::TYPE_MEMBERSHIP,
                $e->getMessage()
            ));
        }
    }

    /**
     * @throws AppwriteException
     */
    private function exportUsers(int $batchSize): void
    {
        $lastDocument = null;

        // Export Users
        while (true) {
            $users = [];

            $queries = [Query::limit($batchSize)];

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
                    $user['email'],
                    $user['name'],
                    $user['password'] ? new Hash($user['password'], algorithm: $user['hash']) : null,
                    $user['phone'],
                    $this->calculateTypes($user),
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

        // Export Teams
        while (true) {
            $teams = [];

            $queries = [Query::limit($batchSize)];

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
                    $e->getMessage()
                )
            );
        }

        try {
            if (\in_array(Resource::TYPE_COLLECTION, $resources)) {
                $this->exportCollections($batchSize);
            }
        } catch (\Throwable $e) {
            $this->addError(
                new Exception(
                    Resource::TYPE_COLLECTION,
                    $e->getMessage()
                )
            );
        }

        try {
            if (\in_array(Resource::TYPE_ATTRIBUTE, $resources)) {
                $this->exportAttributes($batchSize);
            }
        } catch (\Throwable $e) {
            $this->addError(
                new Exception(
                    Resource::TYPE_ATTRIBUTE,
                    $e->getMessage()
                )
            );
        }

        try {
            if (\in_array(Resource::TYPE_INDEX, $resources)) {
                $this->exportIndexes($batchSize);
            }
        } catch (\Throwable $e) {
            $this->addError(
                new Exception(
                    Resource::TYPE_INDEX,
                    $e->getMessage()
                )
            );
        }

        try {
            if (\in_array(Resource::TYPE_DOCUMENT, $resources)) {
                $this->exportDocuments($batchSize);
            }
        } catch (\Throwable $e) {
            $this->addError(
                new Exception(
                    Resource::TYPE_DOCUMENT,
                    $e->getMessage()
                )
            );
        }
    }

    public function stripMetadata(array $document, bool $root = true): array
    {
        if ($root) {
            unset($document['$id']);
        }

        unset($document['$permissions']);
        unset($document['$collectionId']);
        unset($document['$updatedAt']);
        unset($document['$createdAt']);
        unset($document['$databaseId']);

        foreach ($document as $key => $value) {
            if (is_array($value)) {
                $document[$key] = $this->stripMetadata($value, false);
            }
        }

        return $document;
    }

    /**
     * @throws AppwriteException
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

                $response = $this->database->listDocuments(
                    $collection->getDatabase()->getId(),
                    $collection->getId(),
                    $queries
                );

                foreach ($response['documents'] as $document) {
                    $id = $document['$id'];
                    $permissions = $document['$permissions'];

                    $document = $this->stripMetadata($document);

                    // Certain Appwrite versions allowed for data to be required but null
                    // This isn't allowed in modern versions, so we need to remove it by comparing their attributes and replacing it with default value.
                    $attributes = $this->cache->get(Attribute::getName());
                    foreach ($attributes as $attribute) {
                        /** @var Attribute $attribute */
                        if ($attribute->getCollection()->getId() !== $collection->getId()) {
                            continue;
                        }

                        if ($attribute->getRequired() && ! isset($document[$attribute->getKey()])) {
                            switch ($attribute->getTypeName()) {
                                case Attribute::TYPE_BOOLEAN:
                                    $document[$attribute->getKey()] = false;
                                    break;
                                case Attribute::TYPE_STRING:
                                    $document[$attribute->getKey()] = '';
                                    break;
                                case Attribute::TYPE_INTEGER:
                                    $document[$attribute->getKey()] = 0;
                                    break;
                                case Attribute::TYPE_FLOAT:
                                    $document[$attribute->getKey()] = 0.0;
                                    break;
                                case Attribute::TYPE_DATETIME:
                                    $document[$attribute->getKey()] = '1970-01-01 00:00:00.000';
                                    break;
                                case Attribute::TYPE_URL:
                                    $document[$attribute->getKey()] = 'http://null';
                                    break;
                            }
                        }
                    }

                    $cleanData = $this->stripMetadata($document);

                    $documents[] = new Document(
                        $id,
                        $collection,
                        $cleanData,
                        $permissions
                    );
                    $lastDocument = $id;
                }

                $this->callback($documents);

                if (count($response['documents']) < $batchSize) {
                    break;
                }
            }
        }
    }

    /**
     * @throws \Exception
     */
    private function convertAttribute(array $value, Collection $collection): Attribute
    {
        switch ($value['type']) {
            case 'string':
                if (!isset($value['format'])) {
                    return new Text(
                        $value['key'],
                        $collection,
                        $value['required'],
                        $value['array'],
                        $value['default'],
                        $value['size'] ?? 0
                    );
                }

                return match ($value['format']) {
                    'email' => new Email(
                        $value['key'],
                        $collection,
                        $value['required'],
                        $value['array'],
                        $value['default']
                    ),
                    'enum' => new Enum(
                        $value['key'],
                        $collection,
                        $value['elements'],
                        $value['required'],
                        $value['array'],
                        $value['default']
                    ),
                    'url' => new URL(
                        $value['key'],
                        $collection,
                        $value['required'],
                        $value['array'],
                        $value['default']
                    ),
                    'ip' => new IP(
                        $value['key'],
                        $collection,
                        $value['required'],
                        $value['array'],
                        $value['default']
                    ),
                    'datetime' => new DateTime(
                        $value['key'],
                        $collection,
                        $value['required'],
                        $value['array'],
                        $value['default']
                    ),
                    default => new Text(
                        $value['key'],
                        $collection,
                        $value['required'],
                        $value['array'],
                        $value['default'],
                        $value['size'] ?? 0
                    ),
                };
            case 'boolean':
                return new Boolean(
                    $value['key'],
                    $collection,
                    $value['required'],
                    $value['array'],
                    $value['default']
                );
            case 'integer':
                return new Integer(
                    $value['key'],
                    $collection,
                    $value['required'],
                    $value['array'],
                    $value['default'],
                    $value['min'] ?? null,
                    $value['max'] ?? null
                );
            case 'double':
                return new Decimal(
                    $value['key'],
                    $collection,
                    $value['required'],
                    $value['array'],
                    $value['default'],
                    $value['min'] ?? null,
                    $value['max'] ?? null
                );
            case 'relationship':
                return new Relationship(
                    $value['key'],
                    $collection,
                    $value['required'],
                    $value['array'],
                    $value['relatedCollection'],
                    $value['relationType'],
                    $value['twoWay'],
                    $value['twoWayKey'],
                    $value['onDelete'],
                    $value['side']
                );
            case 'datetime':
                return new DateTime(
                    $value['key'],
                    $collection,
                    $value['required'],
                    $value['array'],
                    $value['default']
                );
        }

        throw new \Exception('Unknown attribute type: '.$value['type']);
    }

    /**
     * @throws AppwriteException
     */
    private function exportDatabases(int $batchSize): void
    {
        $this->database = new Databases($this->client);

        $lastDatabase = null;

        // Transfer Databases
        while (true) {
            $queries = [Query::limit($batchSize)];
            $databases = [];

            if ($lastDatabase) {
                $queries[] = Query::cursorAfter($lastDatabase);
            }

            $response = $this->database->list($queries);

            foreach ($response['databases'] as $database) {
                $newDatabase = new Database(
                    $database['$id'],
                    $database['name'],
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
     * @throws AppwriteException
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

                $response = $this->database->listCollections(
                    $database->getId(),
                    $queries
                );

                foreach ($response['collections'] as $collection) {
                    $newCollection = new Collection(
                        $database,
                        $collection['name'],
                        $collection['$id'],
                        $collection['documentSecurity'],
                        $collection['$permissions']
                    );

                    $collections[] = $newCollection;
                }

                $lastCollection = ! empty($collection)
                    ? $collections[count($collections) - 1]->getId()
                    : null;

                $this->callback($collections);

                if (count($collections) < $batchSize) {
                    break;
                }
            }
        }
    }

    /**
     * @throws AppwriteException
     * @throws \Exception
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

                $response = $this->database->listAttributes(
                    $collection->getDatabase()->getId(),
                    $collection->getId(),
                    $queries
                );

                // Remove two way relationship attributes
                $this->cache->get(Resource::TYPE_ATTRIBUTE);

                $knownTwoWays = [];

                foreach ($this->cache->get(Resource::TYPE_ATTRIBUTE) as $attribute) {
                    /** @var Attribute|Relationship $attribute */
                    if ($attribute->getTypeName() == Attribute::TYPE_RELATIONSHIP && $attribute->getTwoWay()) {
                        $knownTwoWays[] = $attribute->getTwoWayKey();
                    }
                }

                foreach ($response['attributes'] as $attribute) {
                    /** @var array $attribute */
                    if (\in_array($attribute['key'], $knownTwoWays)) {
                        continue;
                    }

                    if ($attribute['type'] === 'relationship') {
                        $knownTwoWays[] = $attribute['twoWayKey'];
                    }

                    $attributes[] = $this->convertAttribute($attribute, $collection);
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
     * @throws AppwriteException
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

                $response = $this->database->listIndexes(
                    $collection->getDatabase()->getId(),
                    $collection->getId(),
                    $queries
                );

                foreach ($response['indexes'] as $index) {
                    $indexes[] = new Index(
                        'unique()',
                        $index['key'],
                        $collection,
                        $index['type'],
                        $index['attributes'],
                        $index['orders']
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

    private function calculateTypes(array $user): array
    {
        if (empty($user['email']) && empty($user['phone'])) {
            return [User::TYPE_ANONYMOUS];
        }

        $types = [];

        if (! empty($user['email']) && ! empty($user['password'])) {
            $types[] = User::TYPE_PASSWORD;
        }

        if (! empty($user['phone'])) {
            $types[] = User::TYPE_PHONE;
        }

        return $types;
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
                    $e->getMessage()
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
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * @throws AppwriteException
     */
    private function exportBuckets(int $batchSize): void
    {
        $buckets = $this->storage->listBuckets();

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
                            resourceType: Resource::TYPE_FILE,
                            message: $e->getMessage(),
                            code: $e->getCode(),
                            resourceId: $file['$id']
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
                $e->getMessage()
            ));
        }

        try {
            if (\in_array(Resource::TYPE_DEPLOYMENT, $resources)) {
                $this->exportDeployments($batchSize, true);
            }
        } catch (\Throwable $e) {
            $this->addError(new Exception(
                Resource::TYPE_DEPLOYMENT,
                $e->getMessage()
            ));
        }
    }

    /**
     * @throws AppwriteException
     */
    private function exportFunctions(int $batchSize): void
    {
        $this->functions = new Functions($this->client);

        $functions = $this->functions->list();

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
                $function['deployment']
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

    private function exportDeploymentData(Func $func, array $deployment)
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

            $deployment = new Deployment(
                $deployment['$id'],
                $func,
                strlen($file),
                $deployment['entrypoint'],
                $start,
                $end,
                $file,
                $deployment['activate']
            );
            $deployment->setInternalId($deployment->getId());

            return $this->callback([$deployment]);
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
}
