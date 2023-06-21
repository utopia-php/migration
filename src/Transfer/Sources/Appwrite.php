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
use Utopia\Transfer\Resources\Auth\Team;
use Utopia\Transfer\Resources\Auth\Membership;
use Utopia\Transfer\Resources\Auth\User;
use Utopia\Transfer\Resources\Database\Attribute;
use Utopia\Transfer\Resources\Database\Attributes\Bool;
use Utopia\Transfer\Resources\Database\Attributes\Boolean;
use Utopia\Transfer\Resources\Database\Attributes\DateTime;
use Utopia\Transfer\Resources\Database\Attributes\Decimal;
use Utopia\Transfer\Resources\Database\Attributes\Email;
use Utopia\Transfer\Resources\Database\Attributes\Enum;
use Utopia\Transfer\Resources\Database\Attributes\Float;
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
    static function getSupportedResources(): array
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
                    $report[Resource::TYPE_MEMBERSHIP] += $teamsClient->listMemberships($team['$id'])['total'];
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
                    $report[Resource::TYPE_COLLECTION] += $databaseClient->listCollections($database['$id'])['total'];
                }
            }

            $currentPermission = 'documents.read';
            if (in_array(Resource::TYPE_DOCUMENT, $resources)) {
                $report[Resource::TYPE_DOCUMENT] = 0;
                $databases = $databaseClient->list()['databases'];
                foreach ($databases as $database) {
                    $collections = $databaseClient->listCollections($database['$id'])['collections'];
                    foreach ($collections as $collection) {
                        $report[Resource::TYPE_DOCUMENT] += $databaseClient->listDocuments($database['$id'], $collection['$id'])['total'];
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
                $buckets = $storageClient->listBuckets()['buckets'];
                foreach ($buckets as $bucket) {
                    $report[Resource::TYPE_FILE] += $storageClient->listFiles($bucket['$id'])['total'];
                }
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
                    $report[Resource::TYPE_DEPLOYMENT] += $functionsClient->listDeployments($function['$id'])['total'];
                }
            }

            if (in_array(Resource::TYPE_ENVVAR, $resources)) {
                $report[Resource::TYPE_ENVVAR] = 0;
                $functions = $functionsClient->list()['functions'];
                foreach ($functions as $function) {
                    $report[Resource::TYPE_ENVVAR] += $functionsClient->listVariables($function['$id'])['total'];
                }
            }

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
    protected function exportAuthGroup(int $batchSize, array $resources)
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
                    !$user['status'],
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

                foreach ($response['memberships'] as $membership) {
                    $memberships[] = new Membership(
                        $team,
                        $membership['userId'],
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

    protected function exportDatabasesGroup(int $batchSize, array $resources)
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
                    unset($document['$id']);
                    unset($document['$permissions']);
                    unset($document['$collectionId']);
                    unset($document['$updatedAt']);
                    unset($document['$createdAt']);
                    unset($document['$databaseId']);

                    $documents[] = new Document(
                        $id,
                        $collection->getDatabase(),
                        $collection,
                        $document,
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

                foreach ($response['attributes'] as $attribute) {
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

        if (!empty($user['email'])) {
            $types[] = User::TYPE_EMAIL;
        }

        if (!empty($user['phone'])) {
            $types[] = User::TYPE_PHONE;
        }

        return $types;
    }

    protected function exportStorageGroup(int $batchSize, array $resources)
    {
        if (in_array(Resource::TYPE_BUCKET, $resources)) {
            $this->exportBuckets($batchSize);
        }

        if (in_array(Resource::TYPE_FILE, $resources)) {
            $this->exportFiles($batchSize);
        }
    }

    private function exportBuckets(int $batchSize)
    {
        //TODO: Impl batching
        $storageClient = new Storage($this->client);

        $buckets = $storageClient->listBuckets();

        $convertedBuckets = [];

        foreach ($buckets['buckets'] as $bucket) {
            $convertedBuckets[] = new Bucket(
                $bucket['$id'],
                $bucket['$permissions'],
                $bucket['fileSecurity'],
                $bucket['name'],
                $bucket['enabled'],
                $bucket['maximumFileSize'],
                $bucket['allowedFileExtensions'],
                $bucket['compression'],
                $bucket['encryption'],
                $bucket['antivirus'],
            );
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

    protected function exportFunctionsGroup(int $batchSize, array $resources)
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
        //TODO: Implement batching
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
