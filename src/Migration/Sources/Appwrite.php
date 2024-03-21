<?php

namespace Utopia\Migration\Sources;

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
    protected ?Client $client = null;

    protected string $project = '';

    protected string $key = '';

    public function __construct(string $project, string $endpoint, string $key)
    {
        $this->client = (new Client())
            ->setEndpoint($endpoint)
            ->setProject($project)
            ->setKey($key);

        $this->endpoint = $endpoint;
        $this->project = $project;
        $this->key = $key;
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

    public function report(array $resources = []): array
    {
        $report = [];
        $currentPermission = '';

        if (empty($resources)) {
            $resources = $this->getSupportedResources();
        }

        $usersClient = new Users($this->client);
        $teamsClient = new Teams($this->client);
        $databaseClient = new Databases($this->client);
        $storageClient = new Storage($this->client);
        $functionsClient = new Functions($this->client);

        // Auth
        try {
            $currentPermission = 'users.read';
            if (in_array(Resource::TYPE_USER, $resources)) {
                $report[Resource::TYPE_USER] = $usersClient->list()['total'];
            }

            $currentPermission = 'teams.read';
            if (in_array(Resource::TYPE_TEAM, $resources)) {
                $report[Resource::TYPE_TEAM] = $teamsClient->list()['total'];
            }

            if (in_array(Resource::TYPE_MEMBERSHIP, $resources)) {
                $report[Resource::TYPE_MEMBERSHIP] = 0;
                $teams = $teamsClient->list()['teams'];
                foreach ($teams as $team) {
                    $report[Resource::TYPE_MEMBERSHIP] += $teamsClient->listMemberships($team['$id'], [Query::limit(1)])['total'];
                }
            }

            // Databases
            $currentPermission = 'databases.read';
            if (in_array(Resource::TYPE_DATABASE, $resources)) {
                $report[Resource::TYPE_DATABASE] = $databaseClient->list()['total'];
            }

            $currentPermission = 'collections.read';
            if (in_array(Resource::TYPE_COLLECTION, $resources)) {
                $report[Resource::TYPE_COLLECTION] = 0;
                $databases = $databaseClient->list()['databases'];
                foreach ($databases as $database) {
                    $report[Resource::TYPE_COLLECTION] += $databaseClient->listCollections($database['$id'], [Query::limit(1)])['total'];
                }
            }

            $currentPermission = 'documents.read';
            if (in_array(Resource::TYPE_DOCUMENT, $resources)) {
                $report[Resource::TYPE_DOCUMENT] = 0;
                $databases = $databaseClient->list()['databases'];
                foreach ($databases as $database) {
                    $collections = $databaseClient->listCollections($database['$id'])['collections'];
                    foreach ($collections as $collection) {
                        $report[Resource::TYPE_DOCUMENT] += $databaseClient->listDocuments($database['$id'], $collection['$id'], [Query::limit(1)])['total'];
                    }
                }
            }

            $currentPermission = 'attributes.read';
            if (in_array(Resource::TYPE_ATTRIBUTE, $resources)) {
                $report[Resource::TYPE_ATTRIBUTE] = 0;
                $databases = $databaseClient->list()['databases'];
                foreach ($databases as $database) {
                    $collections = $databaseClient->listCollections($database['$id'])['collections'];
                    foreach ($collections as $collection) {
                        $report[Resource::TYPE_ATTRIBUTE] += $databaseClient->listAttributes($database['$id'], $collection['$id'])['total'];
                    }
                }
            }

            $currentPermission = 'indexes.read';
            if (in_array(Resource::TYPE_INDEX, $resources)) {
                $report[Resource::TYPE_INDEX] = 0;
                $databases = $databaseClient->list()['databases'];
                foreach ($databases as $database) {
                    $collections = $databaseClient->listCollections($database['$id'])['collections'];
                    foreach ($collections as $collection) {
                        $report[Resource::TYPE_INDEX] += $databaseClient->listIndexes($database['$id'], $collection['$id'])['total'];
                    }
                }
            }

            // Storage
            $currentPermission = 'buckets.read';
            if (in_array(Resource::TYPE_BUCKET, $resources)) {
                $report[Resource::TYPE_BUCKET] = $storageClient->listBuckets()['total'];
            }

            $currentPermission = 'files.read';
            if (in_array(Resource::TYPE_FILE, $resources)) {
                $report[Resource::TYPE_FILE] = 0;
                $report['size'] = 0;
                $buckets = [];
                $lastBucket = null;

                while (true) {
                    $currentBuckets = $storageClient->listBuckets($lastBucket ? [Query::cursorAfter($lastBucket)] : [Query::limit(20)])['buckets'];

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
                        $currentFiles = $storageClient->listFiles($bucket['$id'], $lastFile ? [Query::cursorAfter($lastFile)] : [Query::limit(20)])['files'];
                        $files = array_merge($files, $currentFiles);
                        $lastFile = $files[count($files) - 1]['$id'];

                        if (count($currentFiles) < 20) {
                            break;
                        }
                    }

                    $report[Resource::TYPE_FILE] += count($files);
                    foreach ($files as $file) {
                        $report['size'] += $storageClient->getFile($bucket['$id'], $file['$id'])['sizeOriginal'];
                    }
                }
                $report['size'] = $report['size'] / 1000 / 1000; // MB
            }

            // Functions
            $currentPermission = 'functions.read';
            if (in_array(Resource::TYPE_FUNCTION, $resources)) {
                $report[Resource::TYPE_FUNCTION] = $functionsClient->list()['total'];
            }

            if (in_array(Resource::TYPE_DEPLOYMENT, $resources)) {
                $report[Resource::TYPE_DEPLOYMENT] = 0;
                $functions = $functionsClient->list()['functions'];
                foreach ($functions as $function) {
                    $report[Resource::TYPE_DEPLOYMENT] += $functionsClient->listDeployments($function['$id'], [Query::limit(1)])['total'];
                }
            }

            if (in_array(Resource::TYPE_ENVIRONMENT_VARIABLE, $resources)) {
                $report[Resource::TYPE_ENVIRONMENT_VARIABLE] = 0;
                $functions = $functionsClient->list()['functions'];
                foreach ($functions as $function) {
                    $report[Resource::TYPE_ENVIRONMENT_VARIABLE] += $functionsClient->listVariables($function['$id'])['total'];
                }
            }

            $report['version'] = $this->call('GET', '/health/version', ['X-Appwrite-Key' => '', 'X-Appwrite-Project' => ''])['version'];

            $this->previousReport = $report;

            return $report;
        } catch (\Throwable $e) {
            if ($e->getCode() === 403) {
                throw new \Exception("Missing Permission: {$currentPermission}.");
            } else {
                throw new \Exception($e->getMessage());
            }
        }
    }

    /**
     * Export Auth Resources
     *
     * @param  int  $batchSize  Max 100
     * @param  string[]  $resources
     * @return void
     */
    protected function exportGroupAuth(int $batchSize, array $resources)
    {

        if (in_array(Resource::TYPE_USER, $resources)) {

            $this->exportUsers($batchSize);

        }

        if (in_array(Resource::TYPE_TEAM, $resources)) {

            $this->exportTeams($batchSize);
        }

        if (in_array(Resource::TYPE_MEMBERSHIP, $resources)) {
            $this->exportMemberships($batchSize);
        }
    }

    private function exportUsers(int $batchSize)
    {
        $usersClient = new Users($this->client);
        $lastDocument = null;

        // Export Users
        while (true) {
            $users = [];

            $queries = [Query::limit($batchSize)];

            if ($lastDocument) {
                $queries[] = Query::cursorAfter($lastDocument);
            }

            $response = $usersClient->list($queries);

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

    private function exportTeams(int $batchSize)
    {
        $teamsClient = new Teams($this->client);
        $lastDocument = null;

        // Export Teams
        while (true) {
            $teams = [];

            $queries = [Query::limit($batchSize)];

            if ($lastDocument) {
                $queries[] = Query::cursorAfter($lastDocument);
            }

            $response = $teamsClient->list($queries);

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

    private function exportMemberships(int $batchSize)
    {
        $teamsClient = new Teams($this->client);

        // Export Memberships
        $cacheTeams = $this->cache->get(Team::getName());
        /** @var array<string, User> - array where key is user ID */
        $cacheUsers = [];
        foreach ($this->cache->get(User::getName()) as $cacheUser) {
            /** @var User $cacheUser */
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

                $response = $teamsClient->listMemberships($team->getId(), $queries);

                if ($response['total'] == 0) {
                    break;
                }

                foreach ($response['memberships'] as $membership) {
                    $user = $cacheUsers[$membership['userId']] ?? null;
                    if ($user === null) {
                        throw new \Exception('User not found');
                    }

                    $memberships[] = new Membership(
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

    protected function exportGroupDatabases(int $batchSize, array $resources)
    {
        if (in_array(Resource::TYPE_DATABASE, $resources)) {
            $this->exportDatabases($batchSize);
        }

        if (in_array(Resource::TYPE_COLLECTION, $resources)) {
            $this->exportCollections($batchSize);
        }

        if (in_array(Resource::TYPE_ATTRIBUTE, $resources)) {
            $this->exportAttributes($batchSize);
        }

        if (in_array(Resource::TYPE_INDEX, $resources)) {
            $this->exportIndexes($batchSize);
        }

        if (in_array(Resource::TYPE_DOCUMENT, $resources)) {
            $this->exportDocuments($batchSize);
        }
    }

    public function stripMetadata(array $document, bool $root = true)
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

    private function exportDocuments(int $batchSize)
    {
        $databaseClient = new Databases($this->client);
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

                $response = $databaseClient->listDocuments(
                    $collection->getDatabase()->getId(),
                    $collection->getId(),
                    $queries
                );

                foreach ($response['documents'] as $document) {
                    $id = $document['$id'];
                    $permissions = $document['$permissions'];

                    $document = $this->stripMetadata($document);

                    // Certain Appwrite versions allowed for data to be required but null
                    // This isn't allowed in modern versions so we need to remove it by comparing their attributes and replacing it with default value.
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
                                    $document[$attribute->getKey()] = 0;
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
                        $collection->getDatabase(),
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

    private function convertAttribute(array $value, Collection $collection): Attribute
    {
        switch ($value['type']) {
            case 'string':
                if (! isset($value['format'])) {
                    return new Text(
                        $value['key'],
                        $collection,
                        $value['required'],
                        $value['array'],
                        $value['default'],
                        $value['size'] ?? 0
                    );
                }

                switch ($value['format']) {
                    case 'email':
                        return new Email(
                            $value['key'],
                            $collection,
                            $value['required'],
                            $value['array'],
                            $value['default']
                        );
                    case 'enum':
                        return new Enum(
                            $value['key'],
                            $collection,
                            $value['elements'],
                            $value['required'],
                            $value['array'],
                            $value['default']
                        );
                    case 'url':
                        return new URL(
                            $value['key'],
                            $collection,
                            $value['required'],
                            $value['array'],
                            $value['default']
                        );
                    case 'ip':
                        return new IP(
                            $value['key'],
                            $collection,
                            $value['required'],
                            $value['array'],
                            $value['default']
                        );
                    case 'datetime':
                        return new DateTime(
                            $value['key'],
                            $collection,
                            $value['required'],
                            $value['array'],
                            $value['default']
                        );
                    default:
                        return new Text(
                            $value['key'],
                            $collection,
                            $value['required'],
                            $value['array'],
                            $value['default'],
                            $value['size'] ?? 0
                        );
                }
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
                    $value['min'] ?? 0,
                    $value['max'] ?? 0
                );
            case 'double':
                return new Decimal(
                    $value['key'],
                    $collection,
                    $value['required'],
                    $value['array'],
                    $value['default'],
                    $value['min'] ?? 0,
                    $value['max'] ?? 0
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

    private function exportDatabases(int $batchSize)
    {

        $databaseClient = new Databases($this->client);

        $lastDatabase = null;

        // Transfer Databases
        while (true) {
            $queries = [Query::limit($batchSize)];
            $databases = [];

            if ($lastDatabase) {
                $queries[] = Query::cursorAfter($lastDatabase);
            }

            $response = $databaseClient->list($queries);

            foreach ($response['databases'] as $database) {
                $newDatabase = new Database(
                    $database['name'],
                    $database['$id']
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

    private function exportCollections(int $batchSize)
    {
        $databaseClient = new Databases($this->client);

        // Transfer Collections

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

                $response = $databaseClient->listCollections(
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

    private function exportAttributes(int $batchSize)
    {
        $databaseClient = new Databases($this->client);

        // Transfer Attributes
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

                $response = $databaseClient->listAttributes(
                    $collection->getDatabase()->getId(),
                    $collection->getId(),
                    $queries
                );

                // Remove two way relationship attributes
                $this->cache->get(Resource::TYPE_ATTRIBUTE);

                $knownTwoways = [];

                foreach ($this->cache->get(Resource::TYPE_ATTRIBUTE) as $attribute) {
                    /** @var Attribute|Relationship $attribute */
                    if ($attribute->getTypeName() == Attribute::TYPE_RELATIONSHIP && $attribute->getTwoWay()) {
                        $knownTwoways[] = $attribute->getTwoWayKey();
                    }
                }

                foreach ($response['attributes'] as $attribute) {
                    if (in_array($attribute['key'], $knownTwoways)) {
                        continue;
                    }

                    if ($attribute['type'] === 'relationship') {
                        $knownTwoways[] = $attribute['twoWayKey'];
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

    private function exportIndexes(int $batchSize)
    {
        $databaseClient = new Databases($this->client);

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

                $response = $databaseClient->listIndexes(
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

    protected function exportGroupStorage(int $batchSize, array $resources)
    {
        if (in_array(Resource::TYPE_BUCKET, $resources)) {
            $this->exportBuckets($batchSize, false);
        }

        if (in_array(Resource::TYPE_FILE, $resources)) {
            $this->exportFiles($batchSize);
        }

        if (in_array(Resource::TYPE_BUCKET, $resources)) {
            $this->exportBuckets($batchSize, true);
        }
    }

    private function exportBuckets(int $batchSize, bool $updateLimits)
    {
        $storageClient = new Storage($this->client);

        $buckets = $storageClient->listBuckets();

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
                $updateLimits
            );

            $bucket->setUpdateLimits($updateLimits);
            $convertedBuckets[] = $bucket;
        }

        if (empty($convertedBuckets)) {
            return;
        }

        $this->callback($convertedBuckets);
    }

    private function exportFiles(int $batchSize)
    {
        $storageClient = new Storage($this->client);

        $buckets = $this->cache->get(Bucket::getName());
        foreach ($buckets as $bucket) {
            /** @var Bucket $bucket */
            $lastDocument = null;

            while (true) {
                $queries = [Query::limit($batchSize)];

                if ($lastDocument) {
                    $queries[] = Query::cursorAfter($lastDocument);
                }

                $response = $storageClient->listFiles(
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

    private function exportFileData(File $file)
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
            $file->setData($chunkData)
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

    protected function exportGroupFunctions(int $batchSize, array $resources)
    {
        if (in_array(Resource::TYPE_FUNCTION, $resources)) {
            $this->exportFunctions($batchSize);
        }

        if (in_array(Resource::TYPE_DEPLOYMENT, $resources)) {
            $this->exportDeployments($batchSize, true);
        }
    }

    private function exportFunctions(int $batchSize)
    {
        $functionsClient = new Functions($this->client);

        $functions = $functionsClient->list();

        if ($functions['total'] === 0) {
            return;
        }

        $convertedResources = [];

        foreach ($functions['functions'] as $function) {
            $convertedFunc = new Func(
                $function['name'],
                $function['$id'],
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
                    $convertedFunc,
                    $var['key'],
                    $var['value'],
                );
            }
        }

        $this->callback($convertedResources);
    }

    private function exportDeployments(int $batchSize, bool $exportOnlyActive = false)
    {
        $functionsClient = new Functions($this->client);
        $functions = $this->cache->get(Func::getName());

        // exportDeploymentData doesn't exist on Appwrite versions prior to 1.4
        $appwriteVersion = $this->call('GET', '/health/version', ['X-Appwrite-Key' => '', 'X-Appwrite-Project' => ''])['version'];

        if (version_compare($appwriteVersion, '1.4.0', '<')) {
            return;
        }

        foreach ($functions as $func) {
            /** @var Func $func */
            $lastDocument = null;

            if ($exportOnlyActive && $func->getActiveDeployment()) {
                $deployment = $functionsClient->getDeployment($func->getId(), $func->getActiveDeployment());

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

                $response = $functionsClient->listDeployments(
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
            $deployment->setData($chunkData);
            $deployment->setStart($start);
            $deployment->setEnd($end);
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
