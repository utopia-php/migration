<?php

namespace Utopia\Transfer\Sources;

use Utopia\Transfer\Source;
use Appwrite\Client;
use Appwrite\Query;
use Appwrite\Services\Databases;
use Appwrite\Services\Functions;
use Appwrite\Services\Storage;
use Appwrite\Services\Teams;
use Appwrite\Services\Users;
use Utopia\Transfer\Resource;
use Utopia\Transfer\Resources\Database\Attribute;
use Utopia\Transfer\Transfer;
use Utopia\Transfer\Resources\Database\Attributes\BoolAttribute;
use Utopia\Transfer\Resources\Database\Attributes\DateTimeAttribute;
use Utopia\Transfer\Resources\Database\Attributes\EmailAttribute;
use Utopia\Transfer\Resources\Database\Attributes\EnumAttribute;
use Utopia\Transfer\Resources\Database\Attributes\FloatAttribute;
use Utopia\Transfer\Resources\Database\Attributes\IntAttribute;
use Utopia\Transfer\Resources\Database\Attributes\IPAttribute;
use Utopia\Transfer\Resources\Database\Attributes\StringAttribute;
use Utopia\Transfer\Resources\Database\Attributes\URLAttribute;
use Utopia\Transfer\Resources\Database\Attributes\RelationshipAttribute;
use Utopia\Transfer\Resources\Database\Collection;
use Utopia\Transfer\Resources\Database\Database;
use Utopia\Transfer\Resources\Database\Document;
use Utopia\Transfer\Resources\Auth\User;
use Utopia\Transfer\Resources\Auth\Hash;
use Utopia\Transfer\Resources\Database\Index;
use Utopia\Transfer\Resources\Storage\Bucket;
use Utopia\Transfer\Resources\Functions\EnvVar;
use Utopia\Transfer\Resources\Storage\File;
use Utopia\Transfer\Resources\Storage\FileData;
use Utopia\Transfer\Resources\Functions\Func;
use Utopia\Transfer\Resources\Auth\Team;
use Utopia\Transfer\Resources\Auth\TeamMembership;

class Appwrite extends Source
{
    /**
     * @var Client|null
     */
    protected $client = null;

    /**
     * @var string
     */
    protected string $project = '';

    /**
     * @var string
     */
    protected string $key = '';

    /**
     * Constructor
     *
     * @param string $project
     * @param string $endpoint
     * @param string $key
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
     *
     * @return string
     */
    static function getName(): string
    {
        return "Appwrite";
    }

    /**
     * Get Supported Resources
     *
     * @return array
     */
    public function getSupportedResources(): array
    {
        return [
            Transfer::GROUP_AUTH,
            Transfer::GROUP_DATABASES,
            Transfer::GROUP_STORAGE,
            Transfer::GROUP_FUNCTIONS
        ];
    }

    public function check(array $resources = []): array
    {
        $report = [
            Transfer::GROUP_AUTH => [],
            Transfer::GROUP_DATABASES => [],
            Transfer::GROUP_STORAGE => [],
            Transfer::GROUP_FUNCTIONS => []
        ];

        if (empty($resources)) {
            $resources = $this->getSupportedResources();
        }

        // Most of these API calls are purposely wrong. Appwrite will throw a 403 before a 400.
        // We want to make sure the API key has the correct permissions.

        foreach ($resources as $resource) {
            switch ($resource) {
                case Transfer::GROUP_DATABASES:
                    $databases = new Databases($this->client);
                    try {
                        $databases->list();
                    } catch (\Throwable $e) {
                        if ($e->getCode() == 401) {
                            $report[Transfer::GROUP_DATABASES][] =
                                "API Key is missing scope: databases.read";
                        }
                    }

                    try {
                        $databases->create("", "");
                    } catch (\Throwable $e) {
                        if ($e->getCode() == 401) {
                            $report[Transfer::GROUP_DATABASES][] =
                                "API Key is missing scope: databases.write";
                        }
                    }

                    try {
                        $databases->listCollections("", [], "");
                    } catch (\Throwable $e) {
                        if ($e->getCode() == 401) {
                            $report[Transfer::GROUP_DATABASES][] =
                                "API Key is missing scope: collections.write";
                        }
                    }

                    try {
                        $databases->createCollection("", "", "", []);
                    } catch (\Throwable $e) {
                        if ($e->getCode() == 401) {
                            $report[Transfer::GROUP_DATABASES][] =
                                "API Key is missing scope: collections.write";
                        }
                    }

                    try {
                        $databases->listDocuments("", "", []);
                    } catch (\Throwable $e) {
                        if ($e->getCode() == 401) {
                            $report[Transfer::GROUP_DATABASES][] =
                                "API Key is missing scope: documents.write";
                        }
                    }

                    try {
                        $databases->createDocument("", "", "", [], []);
                    } catch (\Throwable $e) {
                        if ($e->getCode() == 401) {
                            $report["Documents"][] =
                                "API Key is missing scope: documents.write";
                        }
                    }

                    try {
                        $databases->listIndexes("", "");
                    } catch (\Throwable $e) {
                        if ($e->getCode() == 401) {
                            $report[Transfer::GROUP_DATABASES][] =
                                "API Key is missing scope: indexes.read";
                        }
                    }

                    try {
                        $databases->createIndex("", "", "", "", [], []);
                    } catch (\Throwable $e) {
                        if ($e->getCode() == 401) {
                            $report[Transfer::GROUP_DATABASES][] =
                                "API Key is missing scope: indexes.write";
                        }
                    }

                    try {
                        $databases->listAttributes("", "");
                    } catch (\Throwable $e) {
                        if ($e->getCode() == 401) {
                            $report[Transfer::GROUP_DATABASES][] =
                                "API Key is missing scope: attributes.read";
                        }
                    }

                    try {
                        $databases->createStringAttribute(
                            "",
                            "",
                            "",
                            0,
                            false,
                            false
                        );
                    } catch (\Throwable $e) {
                        if ($e->getCode() == 401) {
                            $report[Transfer::GROUP_DATABASES][] =
                                "API Key is missing scope: attributes.write";
                        }
                    }
                    break;
                case Transfer::GROUP_AUTH:
                    $auth = new Users($this->client);
                    try {
                        $auth->list();
                    } catch (\Throwable $e) {
                        if ($e->getCode() == 401) {
                            $report[Transfer::GROUP_AUTH][] =
                                "API Key is missing scope: users.read";
                        }
                    }
                    break;
                case Transfer::GROUP_STORAGE:
                    $storage = new Storage($this->client);
                    try {
                        $storage->listFiles('');
                    } catch (\Throwable $e) {
                        if ($e->getCode() == 401) {
                            $report[Transfer::GROUP_STORAGE][] =
                                "API Key is missing scope: files.read";
                        }
                    }
                case Transfer::GROUP_FUNCTIONS:
                    $functions = new Functions($this->client);
                    try {
                        $functions->list();
                    } catch (\Throwable $e) {
                        if ($e->getCode() == 401) {
                            $report[Transfer::GROUP_FUNCTIONS][] =
                                "API Key is missing scope: functions.read";
                        }
                    }

                    try {
                        $functions->listExecutions('');
                    } catch (\Throwable $e) {
                        if ($e->getCode() == 401) {
                            $report[Transfer::GROUP_FUNCTIONS][] =
                                "API Key is missing scope: executions.read";
                        }
                    }
                    break;
            }
        }

        return $report;
    }

    /**
     * Export Auth Resources
     *
     * @param int $batchSize Max 100
     *
     * @return void
     */
    public function exportAuthGroup(int $batchSize, array $resources)
    {
        if (in_array(Resource::TYPE_USER, $resources)) {
            $this->exportUsers($batchSize);
        }

        if (in_array(Resource::TYPE_TEAM, $resources)) {
            $this->exportTeams($batchSize);
        }

        if (in_array(Resource::TYPE_TEAM_MEMBERSHIP, $resources)) {
            $this->exportTeamMemberships($batchSize);
        }
    }

    function exportUsers(int $batchSize)
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

            if ($response["total"] == 0) {
                break;
            }

            foreach ($response["users"] as $user) {
                $users[] = new User(
                    $user['$id'],
                    $user["email"],
                    $user["name"],
                    $user["password"] ? new Hash($user["password"], $user["hash"]) : null,
                    $user["phone"],
                    $this->calculateTypes($user),
                    "",
                    $user["emailVerification"],
                    $user["phoneVerification"],
                    !$user["status"],
                    $user["prefs"]
                );

                $lastDocument = $user['$id'];
            }

            $this->callback($users);

            if (count($users) < $batchSize) {
                break;
            }
        }
    }

    function exportTeams(int $batchSize)
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

            if ($response["total"] == 0) {
                break;
            }

            foreach ($response["teams"] as $team) {
                $teams[] = new Team(
                    $team['$id'],
                    $team["name"],
                    $team["prefs"],
                );

                $lastDocument = $team['$id'];
            }

            $this->callback($teams);

            if (count($teams) < $batchSize) {
                break;
            }
        }
    }

    function exportTeamMemberships(int $batchSize)
    {
        $teamsClient = new Teams($this->client);

        $lastDocument = null;

        // Export Memberships
        $cacheTeams = $this->resourceCache->get(Team::getName());

        foreach ($cacheTeams as $team) {
            while (true) {
                $memberships = [];

                $queries = [Query::limit($batchSize)];

                if ($lastDocument) {
                    $queries[] = Query::cursorAfter($lastDocument);
                }

                $response = $teamsClient->listMemberships($team->getId(), $queries);

                if ($response["total"] == 0) {
                    break;
                }

                foreach ($response["memberships"] as $membership) {
                    $memberships[] = new TeamMembership(
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

    public function exportDatabasesGroup(int $batchSize, array $resources)
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


    function exportDocuments(int $batchSize)
    {
        $databaseClient = new Databases($this->client);
        $collections = $this->resourceCache->get(Collection::getName());

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

                foreach ($response["documents"] as $document) {
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

                if (count($response["documents"]) < $batchSize) {
                    break;
                }
            }
        }
    }

    function convertAttribute(array $value, Collection $collection): Attribute
    {
        switch ($value["type"]) {
            case "string":
                if (!isset($value["format"])) {
                    return new StringAttribute(
                        $value["key"],
                        $collection,
                        $value["required"],
                        $value["array"],
                        $value["default"],
                        $value["size"] ?? 0
                    );
                }

                switch ($value["format"]) {
                    case "email":
                        return new EmailAttribute(
                            $value["key"],
                            $collection,
                            $value["required"],
                            $value["array"],
                            $value["default"]
                        );
                    case "enum":
                        return new EnumAttribute(
                            $value["key"],
                            $collection,
                            $value["elements"],
                            $value["required"],
                            $value["array"],
                            $value["default"]
                        );
                    case "url":
                        return new URLAttribute(
                            $value["key"],
                            $collection,
                            $value["required"],
                            $value["array"],
                            $value["default"]
                        );
                    case "ip":
                        return new IPAttribute(
                            $value["key"],
                            $collection,
                            $value["required"],
                            $value["array"],
                            $value["default"]
                        );
                    case "datetime":
                        return new DateTimeAttribute(
                            $value["key"],
                            $collection,
                            $value["required"],
                            $value["array"],
                            $value["default"]
                        );
                    default:
                        return new StringAttribute(
                            $value["key"],
                            $collection,
                            $value["required"],
                            $value["array"],
                            $value["default"],
                            $value["size"] ?? 0
                        );
                }
            case "boolean":
                return new BoolAttribute(
                    $value["key"],
                    $collection,
                    $value["required"],
                    $value["array"],
                    $value["default"]
                );
            case "integer":
                return new IntAttribute(
                    $value["key"],
                    $collection,
                    $value["required"],
                    $value["array"],
                    $value["default"],
                    $value["min"] ?? 0,
                    $value["max"] ?? 0
                );
            case "double":
                return new FloatAttribute(
                    $value["key"],
                    $collection,
                    $value["required"],
                    $value["array"],
                    $value["default"],
                    $value["min"] ?? 0,
                    $value["max"] ?? 0
                );
            case "relationship":
                return new RelationshipAttribute(
                    $value["key"],
                    $collection,
                    $value["required"],
                    $value["array"],
                    $value["relatedCollection"],
                    $value["relationType"],
                    $value["twoWay"],
                    $value["twoWayKey"],
                    $value["onDelete"],
                    $value["side"]
                );
        }

        throw new \Exception("Unknown attribute type: " . $value["type"]);
    }

    function exportDatabases(int $batchSize)
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

            foreach ($response["databases"] as $database) {
                $newDatabase = new Database(
                    $database["name"],
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

    function exportCollections(int $batchSize)
    {
        $databaseClient = new Databases($this->client);

        // Transfer Collections
        $lastDocument = null;

        $databases = $this->resourceCache->get(Database::getName());
        foreach ($databases as $database) {
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

                foreach ($response["collections"] as $collection) {
                    $newCollection = new Collection(
                        $database,
                        $collection["name"],
                        $collection['$id'],
                        $collection["documentSecurity"],
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

    function exportAttributes(int $batchSize)
    {
        $databaseClient = new Databases($this->client);

        // Transfer Attributes
        $lastDocument = null;
        $collections = $this->resourceCache->get(Collection::getName());
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

                foreach ($response["attributes"] as $attribute) {
                    $attributes[] = $this->convertAttribute($attribute, $collection);
                }

                $this->callback($attributes);

                if (count($attributes) < $batchSize) {
                    break;
                }
            }
        }
    }

    function exportIndexes(int $batchSize)
    {
        $databaseClient = new Databases($this->client);

        $collections = $this->resourceCache->get(Resource::TYPE_COLLECTION);

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

                foreach ($response["indexes"] as $index) {
                    $indexes[] = new Index(
                        "unique()",
                        $index["key"],
                        $collection,
                        $index["type"],
                        $index["attributes"],
                        $index["orders"]
                    );
                }

                $this->callback($indexes);

                if (count($indexes) < $batchSize) {
                    break;
                }
            }
        }
    }

    function calculateTypes(array $user): array
    {
        if (empty($user["email"]) && empty($user["phone"])) {
            return [User::TYPE_ANONYMOUS];
        }

        $types = [];

        if (!empty($user["email"])) {
            $types[] = User::TYPE_EMAIL;
        }

        if (!empty($user["phone"])) {
            $types[] = User::TYPE_PHONE;
        }

        return $types;
    }

    public function exportStorageGroup(int $batchSize, array $resources)
    {
        if (in_array(Resource::TYPE_BUCKET, $resources)) {
            $this->exportBuckets($batchSize);
        }

        if (in_array(Resource::TYPE_FILE, $resources)) {
            $this->exportFiles($batchSize);
        }
    }

    function exportBuckets(int $batchSize)
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

    function exportFiles(int $batchSize)
    {
        $storageClient = new Storage($this->client);

        $buckets = $this->resourceCache->get(Bucket::getName());
        foreach ($buckets as $bucket) {
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

                foreach ($response["files"] as $file) {
                    $this->handleDataTransfer(new File(
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

                if (count($response["files"]) < $batchSize) {
                    break;
                }
            }
        }
    }

    function handleDataTransfer(File $file)
    {
        // Set the chunk size (5MB)
        $start = 0;
        $end = Transfer::STORAGE_MAX_CHUNK_SIZE - 1;

        if ($end > $file->getSize()) {
            $end = $file->getSize() - 1;
        }

        // Get the file size
        $fileSize = $file->getSize();

        // Loop until the entire file is downloaded
        while ($start < $fileSize) {
            $chunkData = $this->call(
                'GET',
                "/storage/buckets/{$file->getBucket()->getId()}/files/{$file->getId()}/download",
                ['range' => "bytes=$start-$end"]
            );

            // Send the chunk to the callback function
            $this->callback([new FileData(
                $chunkData,
                $start,
                $end,
                $file
            )]);

            // Update the range
            $start += Transfer::STORAGE_MAX_CHUNK_SIZE;
            $end += Transfer::STORAGE_MAX_CHUNK_SIZE;

            if ($end > $fileSize) {
                $end = $fileSize - 1;
            }
        }
    }

    public function exportFunctionsGroup(int $batchSize, array $resources)
    {
        if (in_array(Resource::TYPE_FUNCTION, $resources)) {
            $this->exportFunctions($batchSize);
        }
    }

    public function exportFunctions(int $batchSize)
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
}
