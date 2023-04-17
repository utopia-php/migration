<?php

namespace Utopia\Transfer\Sources;

use Utopia\Transfer\Source;
use Appwrite\Client;
use Appwrite\Query;
use Appwrite\Services\Databases;
use Appwrite\Services\Storage;
use Appwrite\Services\Users;
use Utopia\Transfer\Log;
use Utopia\Transfer\Resources\Attribute;
use Utopia\Transfer\Resources\User;
use Utopia\Transfer\Transfer;
use Utopia\Transfer\Resources\Attributes\BoolAttribute;
use Utopia\Transfer\Resources\Attributes\DateTimeAttribute;
use Utopia\Transfer\Resources\Attributes\EmailAttribute;
use Utopia\Transfer\Resources\Attributes\EnumAttribute;
use Utopia\Transfer\Resources\Attributes\FloatAttribute;
use Utopia\Transfer\Resources\Attributes\IntAttribute;
use Utopia\Transfer\Resources\Attributes\IPAttribute;
use Utopia\Transfer\Resources\Attributes\StringAttribute;
use Utopia\Transfer\Resources\Attributes\URLAttribute;
use Utopia\Transfer\Resources\Attributes\RelationshipAttribute;
use Utopia\Transfer\Resources\Collection;
use Utopia\Transfer\Resources\Database;
use Utopia\Transfer\Resources\Document;
use Utopia\Transfer\Resources\Hash;
use Utopia\Transfer\Resources\Index;
use Utopia\Transfer\Resources\Bucket;
use Utopia\Transfer\Resources\File;
use Utopia\Transfer\Resources\FileData;

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
    }

    /**
     * Get Name
     *
     * @return string
     */
    public function getName(): string
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
            Transfer::RESOURCE_USERS,
            Transfer::RESOURCE_DATABASES,
            Transfer::RESOURCE_DOCUMENTS,
            Transfer::RESOURCE_FILES,
        ];
    }

    public function check(array $resources = []): array
    {
        $report = [
            "Users" => [],
            "Databases" => [],
            "Documents" => [],
            "Files" => [],
            "Functions" => [],
        ];

        if (empty($resources)) {
            $resources = $this->getSupportedResources();
        }

        // Most of these API calls are purposely wrong. Appwrite will throw a 403 before a 400.
        // We want to make sure the API key has the correct permissions.

        foreach ($resources as $resource) {
            switch ($resource) {
                case Transfer::RESOURCE_DATABASES:
                    $databases = new Databases($this->client);
                    try {
                        $databases->list();
                    } catch (\Throwable $e) {
                        if ($e->getCode() == 401) {
                            $report["Databases"][] =
                                "API Key is missing scope: databases.read";
                        }
                    }
                    break;
                case Transfer::RESOURCE_USERS:
                    $auth = new Users($this->client);
                    try {
                        $auth->list();
                    } catch (\Throwable $e) {
                        if ($e->getCode() == 401) {
                            $report["Users"][] =
                                "API Key is missing scope: users.read";
                        }
                    }
                    break;
                case Transfer::RESOURCE_DOCUMENTS:
                    $databases = new Databases($this->client);
                    try {
                        $databases->list();
                    } catch (\Throwable $e) {
                        if ($e->getCode() == 401) {
                            $report["Databases"][] =
                                "API Key is missing scope: databases.read";
                        }
                    }

                    try {
                        $databases->create("", "");
                    } catch (\Throwable $e) {
                        if ($e->getCode() == 401) {
                            $report["Databases"][] =
                                "API Key is missing scope: databases.write";
                        }
                    }

                    try {
                        $databases->listCollections("", [], "");
                    } catch (\Throwable $e) {
                        if ($e->getCode() == 401) {
                            $report["Databases"][] =
                                "API Key is missing scope: collections.write";
                        }
                    }

                    try {
                        $databases->createCollection("", "", "", []);
                    } catch (\Throwable $e) {
                        if ($e->getCode() == 401) {
                            $report["Databases"][] =
                                "API Key is missing scope: collections.write";
                        }
                    }

                    try {
                        $databases->listDocuments("", "", []);
                    } catch (\Throwable $e) {
                        if ($e->getCode() == 401) {
                            $report["Databases"][] =
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
                            $report["Databases"][] =
                                "API Key is missing scope: indexes.read";
                        }
                    }

                    try {
                        $databases->createIndex("", "", "", "", [], []);
                    } catch (\Throwable $e) {
                        if ($e->getCode() == 401) {
                            $report["Databases"][] =
                                "API Key is missing scope: indexes.write";
                        }
                    }

                    try {
                        $databases->listAttributes("", "");
                    } catch (\Throwable $e) {
                        if ($e->getCode() == 401) {
                            $report["Databases"][] =
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
                            $report["Databases"][] =
                                "API Key is missing scope: attributes.write";
                        }
                    }
            }
        }

        return $report;
    }

    /**
     * Export Users
     *
     * @param int $batchSize Max 100
     * @param callable $callback Callback function to be called after each batch, $callback(user[] $batch);
     *
     * @return void
     */
    public function exportUsers(int $batchSize, callable $callback): void
    {
        $usersClient = new Users($this->client);

        $lastDocument = null;

        while (true) {
            $users = [];

            $queries = [Query::limit($batchSize)];

            if ($lastDocument) {
                $queries[] = Query::cursorAfter($lastDocument);
            }

            $response = $usersClient->list($queries);

            foreach ($response["users"] as $user) {
                $users[] = new User(
                    $user['$id'],
                    $user["email"],
                    $user["name"],
                    new Hash($user["password"], $user["hash"]),
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

            $callback($users);

            if (count($users) < $batchSize) {
                break;
            }
        }
    }

    public function convertAttribute(array $value): Attribute
    {
        switch ($value["type"]) {
            case "string":
                if (!isset($value["format"])) {
                    return new StringAttribute(
                        $value["key"],
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
                            $value["required"],
                            $value["array"],
                            $value["default"]
                        );
                    case "enum":
                        return new EnumAttribute(
                            $value["key"],
                            $value["elements"],
                            $value["required"],
                            $value["array"],
                            $value["default"]
                        );
                    case "url":
                        return new URLAttribute(
                            $value["key"],
                            $value["required"],
                            $value["array"],
                            $value["default"]
                        );
                    case "ip":
                        return new IPAttribute(
                            $value["key"],
                            $value["required"],
                            $value["array"],
                            $value["default"]
                        );
                    case "datetime":
                        return new DateTimeAttribute(
                            $value["key"],
                            $value["required"],
                            $value["array"],
                            $value["default"]
                        );
                    default:
                        return new StringAttribute(
                            $value["key"],
                            $value["required"],
                            $value["array"],
                            $value["default"],
                            $value["size"] ?? 0
                        );
                }
            case "boolean":
                return new BoolAttribute(
                    $value["key"],
                    $value["required"],
                    $value["array"],
                    $value["default"]
                );
            case "integer":
                return new IntAttribute(
                    $value["key"],
                    $value["required"],
                    $value["array"],
                    $value["default"],
                    $value["min"] ?? 0,
                    $value["max"] ?? 0
                );
            case "double":
                return new FloatAttribute(
                    $value["key"],
                    $value["required"],
                    $value["array"],
                    $value["default"],
                    $value["min"] ?? 0,
                    $value["max"] ?? 0
                );
            case "relationship":
                return new RelationshipAttribute(
                    $value["key"],
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

    /**
     * Export Databases
     *
     * @param int $batchSize Max 100
     * @param callable $callback Callback function to be called after each database, $callback(database[] $batch);
     *
     * @return void
     */
    public function exportDatabases(int $batchSize, callable $callback): void
    {
        $databaseClient = new Databases($this->client);

        $lastDocument = null;

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

                $collections = $databaseClient->listCollections(
                    $database['$id']
                );

                $generalCollections = [];
                foreach ($collections["collections"] as $collection) {
                    $newCollection = new Collection(
                        $collection["name"],
                        $collection['$id'],
                        $collection["documentSecurity"],
                        $collection['$permissions']
                    );

                    $attributes = [];
                    $indexes = [];

                    foreach ($collection["attributes"] as $attribute) {
                        $attributes[] = $this->convertAttribute($attribute);
                    }

                    foreach ($collection["indexes"] as $index) {
                        $indexes[] = new Index(
                            "unique()",
                            $index["key"],
                            $index["type"],
                            $index["attributes"],
                            $index["orders"]
                        );
                    }

                    $newCollection->setAttributes($attributes);
                    $newCollection->setIndexes($indexes);

                    $generalCollections[] = $newCollection;
                }

                $newDatabase->setCollections($generalCollections);
                $databases[] = $newDatabase;

                $lastDocument = $database['$id'];
            }

            $callback($databases);

            if (count($response["databases"]) < $batchSize) {
                break;
            }
        }
    }

    /**
     * Export Documents
     *
     * @param int $batchSize Max 100
     * @param callable $callback Callback function to be called after each batch, $callback(document[] $batch);
     *
     * @return void
     */
    public function exportDocuments(int $batchSize, callable $callback): void
    {
        $databaseClient = new Databases($this->client);

        $databases = $this->resourceCache[Transfer::RESOURCE_DATABASES];

        foreach ($databases as $database) {
            /** @var Database $database */
            $collections = $database->getCollections();

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
                        $database->getId(),
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
                            $database,
                            $collection,
                            $document,
                            $permissions
                        );
                        $lastDocument = $id;
                    }

                    $callback($documents);

                    if (count($response["documents"]) < $batchSize) {
                        break;
                    }
                }
            }
        }
    }

    /**
     * Calculate Types
     *
     * @param array $user
     *
     * @return array
     */
    protected function calculateTypes(array $user): array
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

    /**
     * Export Files
     *
     * @param int $batchSize Max 5
     * @param callable $callback Callback function to be called after each batch, $callback(File[]|Bucket[] $batch);
     */
    public function exportFiles(int $batchSize, callable $callback): void
    {
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

        $callback($convertedBuckets);

        foreach ($convertedBuckets as $bucket) {
            $lastDocument = null;

            while (true) {
                $queries = [Query::limit($batchSize)];

                $files = [];

                if ($lastDocument) {
                    $queries[] = Query::cursorAfter($lastDocument);
                }

                $response = $storageClient->listFiles(
                    $bucket->getId(),
                    $queries
                );

                foreach ($response["files"] as $file) {
                    $files[] = new File(
                        $file['$id'],
                        $bucket,
                        $file['name'],
                        $file['signature'],
                        $file['mimeType'],
                        $file['$permissions'],
                        $file['sizeOriginal'],
                    );
                    $lastDocument = $file['$id'];
                }

                foreach ($files as $file) {
                    $this->streamFile($file, $callback);
                }

                if (count($response["files"]) < $batchSize) {
                    break;
                }
            }
        }
    }

    /**
     * Stream File
     * Streams a file to the destination
     *
     * @param File $file
     * @param callable $callback (array $data)
     *
     * @return void
     */
    protected function streamFile(File $file, callable $callback): void
    {
        // Set the chunk size (5MB)
        $chunkSize = 5 * 1024 * 1024;
        $start = 0;
        $end = $chunkSize - 1;

        // Get the file size
        $fileSize = $file->getSize();

        // Initialize cURL
        $ch = curl_init("{$this->endpoint}/storage/buckets/{$file->getBucket()->getId()}/files/{$file->getId()}/download");

        // Loop until the entire file is downloaded
        while ($start < $fileSize) {
            // Set the Range header
            $range = "Range: bytes=$start-$end";
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                $range,
                'X-Appwrite-key: ' . $this->key,
                'X-Appwrite-Project: ' . $this->project,
            ]);

            // Set cURL options
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

            // Download the chunk
            $chunkData = curl_exec($ch);

            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($status !== 200 && $status !== 206) {
                $this->logs[Log::FATAL][] = new Log('Failed to download file, Error: ' . $chunkData);
                throw new \Exception('Failed to download file, Error: ' . $chunkData);
            }

            // Send the chunk to the callback function
            $callback([new FileData(
                $chunkData,
                $start,
                $end,
                $file
            )]);

            // Update the range
            $start += $chunkSize;
            $end += $chunkSize;

            if ($end > $fileSize) {
                $end = $fileSize - 1;
            }
        }

        // Close cURL
        curl_close($ch);
    }
}
