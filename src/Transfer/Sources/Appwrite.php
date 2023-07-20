<?php

namespace Utopia\Transfer\Sources;

use Appwrite\Client;
use Appwrite\Query;
use Appwrite\Services\Databases;
use Appwrite\Services\Functions;
use Appwrite\Services\Storage;
use Appwrite\Services\Teams;
use Appwrite\Services\Users;
use Utopia\Transfer\Resource;
use Utopia\Transfer\Resources\Auth\Hash;
use Utopia\Transfer\Resources\Auth\Membership;
use Utopia\Transfer\Resources\Auth\Team;
use Utopia\Transfer\Resources\Auth\User;
use Utopia\Transfer\Resources\Database\Attribute;
use Utopia\Transfer\Resources\Database\Attributes\Boolean;
use Utopia\Transfer\Resources\Database\Attributes\DateTime;
use Utopia\Transfer\Resources\Database\Attributes\Decimal;
use Utopia\Transfer\Resources\Database\Attributes\Email;
use Utopia\Transfer\Resources\Database\Attributes\Enum;
use Utopia\Transfer\Resources\Database\Attributes\Integer;
use Utopia\Transfer\Resources\Database\Attributes\IP;
use Utopia\Transfer\Resources\Database\Attributes\Relationship;
use Utopia\Transfer\Resources\Database\Attributes\Text;
use Utopia\Transfer\Resources\Database\Attributes\URL;
use Utopia\Transfer\Resources\Database\Collection;
use Utopia\Transfer\Resources\Database\Database;
use Utopia\Transfer\Resources\Database\Document;
use Utopia\Transfer\Resources\Database\Index;
use Utopia\Transfer\Resources\Functions\Deployment;
use Utopia\Transfer\Resources\Functions\EnvVar;
use Utopia\Transfer\Resources\Functions\Func;
use Utopia\Transfer\Resources\Storage\Bucket;
use Utopia\Transfer\Resources\Storage\File;
use Utopia\Transfer\Source;
use Utopia\Transfer\Transfer;

class Appwrite extends Source
{
    /**
     * @var Client|null
     */
    protected $client = null;

    protected string $project = '';

    protected string $key = '';

    /**
     * Constructor
     *
     *
     * @return self
     */
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

    /**
     * Get Name
     */
    public static function getName(): string
    {
        return 'Appwrite';
    }

    /**
     * Get Supported Resources
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
            Resource::TYPE_ENVVAR,

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
                $buckets = $storageClient->listBuckets()['buckets'];
                foreach ($buckets as $bucket) {
                    $files = $storageClient->listFiles($bucket['$id']);
                    $report[Resource::TYPE_FILE] += $files['total'];
                    foreach ($files['files'] as $file) {
                        $report['size'] += $storageClient->getFile($bucket['$id'], $file['$id'])['sizeOriginal'];
                    }
                }
                $report['size'] = $report['size'] / 1024 / 1024; // MB
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

            if (in_array(Resource::TYPE_ENVVAR, $resources)) {
                $report[Resource::TYPE_ENVVAR] = 0;
                $functions = $functionsClient->list()['functions'];
                foreach ($functions as $function) {
                    $report[Resource::TYPE_ENVVAR] += $functionsClient->listVariables($function['$id'])['total'];
                }
            }

            $report['version'] = $this->call('GET', '/health/version', ['X-Appwrite-Key' => '', 'X-Appwrite-Project' => ''])['version'];

            return $report;
        } catch (\Exception $e) {
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
     * @param  int  $batchSize Max 100
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
                    $user['password'] ? new Hash($user['password'], $user['hash']) : null,
                    $user['phone'],
                    $this->calculateTypes($user),
                    '',
                    $user['emailVerification'],
                    $user['phoneVerification'],
                    ! $user['status'],
                    $user['prefs']
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

        $lastDocument = null;

        // Export Memberships
        $cacheTeams = $this->cache->get(Team::getName());
        $cacheUsers = $this->cache->get(User::getName());

        foreach ($cacheTeams as $team) {
            /** @var Team $team */
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

                $user = null;
                foreach ($cacheUsers as $cacheUser) {
                    /** @var User $cacheUser */
                    if ($cacheUser->getId() === $response['memberships'][0]['userId']) {
                        $user = $cacheUser;
                        break;
                    }
                }

                if (!$user) {
                    throw new \Exception('User not found');
                }

                foreach ($response['memberships'] as $membership) {
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

    function cleanupSubcollectionData(array $document, bool $root = true) {
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
                $document[$key] = $this->cleanupSubcollectionData($value, false);
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

                    $document = $this->cleanupSubcollectionData($document);

                    // Certain Appwrite versions allowed for data to be required but null
                    // This isn't allowed in modern versions so we need to remove it by comparing their attributes and replacing it with default value.
                    $attributes = $this->cache->get(Attribute::getName());
                    foreach ($attributes as $attribute) {
                        /** @var Attribute $attribute */
                        if ($attribute->getCollection()->getId() == $collection->getId()) {
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
                    }

                    $cleanData = $this->handleSubcollections($document, $collection, ['$databaseId', '$collectionId', '$createdAt', '$updatedAt', '$permissions']);

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

    private function handleSubcollections($document, Collection $collection, $keys = [])
    {
        if (! array_key_exists('$id', $document)) {
            return $document;
        }

        foreach ($document as $key => &$value) {
            if (in_array($key, $keys, true)) {
                unset($document[$key]);
            } elseif (is_array($value)) {
                $value = $this->handleSubcollections($value, $collection, $keys);
            }
        }

        return $document;
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
        }

        throw new \Exception('Unknown attribute type: '.$value['type']);
    }

    private function exportDatabases(int $batchSize)
    {
        $databaseClient = new Databases($this->client);

        $lastDocument = null;

        // Transfer Databases
        while (true) {
            $queries = [Query::limit($batchSize)];
            $databases = [];

            if ($lastDocument) {
                $queries[] = Query::cursorAfter($lastDocument);
            }

            $response = $databaseClient->list($queries);

            foreach ($response['databases'] as $database) {
                $newDatabase = new Database(
                    $database['name'],
                    $database['$id']
                );

                $databases[] = $newDatabase;
            }

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
        $lastDocument = null;

        $databases = $this->cache->get(Database::getName());
        foreach ($databases as $database) {
            /** @var Database $database */
            while (true) {
                $queries = [Query::limit($batchSize)];
                $collections = [];

                if ($lastDocument) {
                    $queries[] = Query::cursorAfter($lastDocument);
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
        $lastDocument = null;
        $collections = $this->cache->get(Collection::getName());
        /** @var Collection[] $collections */
        foreach ($collections as $collection) {
            while (true) {
                $queries = [Query::limit($batchSize)];
                $attributes = [];

                if ($lastDocument) {
                    $queries[] = Query::cursorAfter($lastDocument);
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

                $this->callback($attributes);

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
        $lastDocument = null;
        foreach ($collections as $collection) {
            /** @var Collection $collection */
            while (true) {
                $queries = [Query::limit($batchSize)];
                $indexes = [];

                if ($lastDocument) {
                    $queries[] = Query::cursorAfter($lastDocument);
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

                $this->callback($indexes);

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

        if (! empty($user['email'])) {
            $types[] = User::TYPE_EMAIL;
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
                    $this->exportFileData(new File(
                        $file['$id'],
                        $bucket,
                        $file['name'],
                        $file['signature'],
                        $file['mimeType'],
                        $file['$permissions'],
                        $file['sizeOriginal'],
                    ));

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
            $this->exportDeployments($batchSize);
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
                $function['timeout']
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

    private function exportDeployments(int $batchSize)
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
                    $this->exportDeploymentData($func, $deployment);

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
        $fileSize = $deployment['size'];

        if ($end > $fileSize) {
            $end = $fileSize - 1;
        }

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
