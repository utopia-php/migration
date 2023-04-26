<?php

namespace Utopia\Transfer\Destinations;

use Appwrite\Client;
use Appwrite\Services\Users;
use Appwrite\Services\Databases;
use Appwrite\Services\Functions;
use Appwrite\Services\Storage;
use Appwrite\Services\Teams;
use Utopia\Transfer\Destination;
use Utopia\Transfer\Transfer;
use Utopia\Transfer\Resources\Auth\User;
use Utopia\Transfer\Resources\Auth\Hash;
use Utopia\Transfer\Resources\Auth\TeamMembership;
use Utopia\Transfer\Resources\Storage\Bucket;
use Utopia\Transfer\Resources\Storage\FileData;
use Utopia\Transfer\Resources\Storage\Index;
use Utopia\Transfer\Resources\Functions\Func;
use Utopia\Transfer\Resources\Functions\EnvVar;
use Utopia\Transfer\Resources\Database\Database;
use Utopia\Transfer\Resources\Database\Collection;
use Utopia\Transfer\Resources\Database\Attribute;
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
use Utopia\Transfer\Resources\Database\Document;
use Utopia\Transfer\Resource;

class Appwrite extends Destination
{
    protected Client $client;

    protected string $project;
    protected string $key;

    public function __construct(string $project, string $endpoint, string $key)
    {
        $this->project = $project;
        $this->endpoint = $endpoint;
        $this->key = $key;

        $this->client = (new Client())
            ->setEndpoint($endpoint)
            ->setProject($project)
            ->setKey($key);
    }

    /**
     * Get Name
     *
     * @return string
     */
    public function getName(): string
    {
        return 'Appwrite';
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
            Transfer::GROUP_DOCUMENTS,
            Transfer::GROUP_STORAGE,
            Transfer::GROUP_FUNCTIONS
        ];
    }

    public function check(array $resources = []): array
    {
        $report = [
            'Users' => [],
            'Databases' => [],
            'Documents' => [],
            'Files' => [],
            'Functions' => []
        ];

        if (empty($resources)) {
            $resources = $this->getSupportedResources();
        }

        // Most of these API calls are purposely wrong. Appwrite will throw a 403 before a 400.
        // We want to make sure the API key has full read and write access to the project.
        foreach ($resources as $resource) {
            switch ($resource) {
                case Transfer::GROUP_DATABASES:
                    $databases = new Databases($this->client);
                    try {
                        $databases->list();
                    } catch (\Throwable $e) {
                        if ($e->getCode() == 401) {
                            $report['Databases'][] = 'API Key is missing scope: databases.read';
                        }
                    }
                    break;
                case Transfer::GROUP_AUTH:
                    $auth = new Users($this->client);
                    try {
                        $auth->list();
                    } catch (\Throwable $e) {
                        if ($e->getCode() == 401) {
                            $report['Users'][] = 'API Key is missing scope: users.read';
                        }
                    }
                    break;
                case Transfer::GROUP_DOCUMENTS:
                    $databases = new Databases($this->client);
                    try {
                        $databases->list();
                    } catch (\Throwable $e) {
                        if ($e->getCode() == 401) {
                            $report['Databases'][] = 'API Key is missing scope: databases.read';
                        }
                    }

                    try {
                        $databases->create('', '');
                    } catch (\Throwable $e) {
                        if ($e->getCode() == 401) {
                            $report['Databases'][] = 'API Key is missing scope: databases.write';
                        }
                    }

                    try {
                        $databases->listCollections('', [], '');
                    } catch (\Throwable $e) {
                        if ($e->getCode() == 401) {
                            $report['Databases'][] = 'API Key is missing scope: collections.write';
                        }
                    }

                    try {
                        $databases->createCollection('', '', '', []);
                    } catch (\Throwable $e) {
                        if ($e->getCode() == 401) {
                            $report['Databases'][] = 'API Key is missing scope: collections.write';
                        }
                    }

                    try {
                        $databases->listDocuments('', '', []);
                    } catch (\Throwable $e) {
                        if ($e->getCode() == 401) {
                            $report['Databases'][] = 'API Key is missing scope: documents.write';
                        }
                    }

                    try {
                        $databases->createDocument('', '', '', [], []);
                    } catch (\Throwable $e) {
                        if ($e->getCode() == 401) {
                            $report['Documents'][] = 'API Key is missing scope: documents.write';
                        }
                    }

                    try {
                        $databases->listIndexes('', '');
                    } catch (\Throwable $e) {
                        if ($e->getCode() == 401) {
                            $report['Databases'][] = 'API Key is missing scope: indexes.read';
                        }
                    }

                    try {
                        $databases->createIndex('', '', '', '', [], []);
                    } catch (\Throwable $e) {
                        if ($e->getCode() == 401) {
                            $report['Databases'][] = 'API Key is missing scope: indexes.write';
                        }
                    }

                    try {
                        $databases->listAttributes('', '');
                    } catch (\Throwable $e) {
                        if ($e->getCode() == 401) {
                            $report['Databases'][] = 'API Key is missing scope: attributes.read';
                        }
                    }

                    try {
                        $databases->createStringAttribute('', '', '', 0, false, false);
                    } catch (\Throwable $e) {
                        if ($e->getCode() == 401) {
                            $report['Databases'][] = 'API Key is missing scope: attributes.write';
                        }
                    }
            }
        }

        return $report;
    }

    function importResources(array $resources, callable $callback, string $group): void
    {
        foreach ($resources as $resource) {
            /** @var Resource $resource */
            switch ($resource->getGroup()) {
                case Transfer::GROUP_DATABASES:
                    $responseResource = $this->importDatabaseResource($resource);
                    break;
                case Transfer::GROUP_DOCUMENTS:
                    $responseResource = $this->importDocumentResource($resource);
                    break;
                case Transfer::GROUP_STORAGE:
                    $responseResource = $this->importFileResource($resource);
                    break;
                case Transfer::GROUP_AUTH:
                    $responseResource = $this->importAuthResource($resource);
                    break;
                case Transfer::GROUP_FUNCTIONS:
                    $responseResource = $this->importFunctionResource($resource);
                    break;
            }

            $this->resourceCache->update($responseResource);
        }
    }

    public function importDatabaseResource(Resource $resource): Resource
    {
        $databaseService = new Databases($this->client);

        $response = null;
        $resource->setStatus(Resource::STATUS_PROCESSING);

        try {
            switch ($resource->getName()) {
                case Resource::TYPE_DATABASE:
                    /** @var Database $resource */
                    $response = $databaseService->create($resource->getId(), $resource->getDBName());
                    break;
                case Resource::TYPE_COLLECTION:
                    /** @var Collection $resource */
                    $response = $newCollection = $databaseService->createCollection(
                        $resource->getDatabase()->getId(),
                        $resource->getId(),
                        $resource->getCollectionName(),
                        $resource->getPermissions(),
                        $resource->getDocumentSecurity()
                    );
                    $resource->setId($newCollection['$id']);
                    break;
                case Resource::TYPE_INDEX:
                    /** @var Index $resource */
                    $response = $databaseService->createIndex(
                        $resource->getCollection()->getDatabase()->getId(),
                        $resource->getCollection()->getId(),
                        $resource->getKey(),
                        $resource->getType(),
                        $resource->getAttributes(),
                        $resource->getOrders()
                    );
                    break;
                case Resource::TYPE_ATTRIBUTE:
                    /** @var Attribute $resource */
                    $this->createAttribute($resource);
                    break;
                case Resource::TYPE_DOCUMENT:
                    /** @var Document $resource */
                    $response = $databaseService->createDocument(
                        $resource->getDatabase()->getId(),
                        $resource->getCollection()->getId(),
                        $resource->getId(),
                        $resource->getData(),
                        $resource->getPermissions()
                    );
                    break;
            }

            $resource->setStatus(Resource::STATUS_SUCCESS);
        } catch (\Exception $e) {
            $resource->setStatus(Resource::STATUS_ERROR, $e->getMessage());
        } finally {
            return $resource;
        }
    }

    public function createAttribute(Attribute $attribute): void
    {
        $databaseService = new Databases($this->client);

        switch ($attribute->getTypeName()) {
            case Attribute::TYPE_STRING:
                /** @var StringAttribute $attribute */
                $databaseService->createStringAttribute($attribute->getCollection()->getDatabase()->getId(), $attribute->getCollection()->getId(), $attribute->getKey(), $attribute->getSize(), $attribute->getRequired(), $attribute->getDefault(), $attribute->getArray());
                break;
            case Attribute::TYPE_INTEGER:
                /** @var IntAttribute $attribute */
                $databaseService->createIntegerAttribute($attribute->getCollection()->getDatabase()->getId(), $attribute->getCollection()->getId(), $attribute->getKey(), $attribute->getRequired(), $attribute->getMin(), $attribute->getMax() ?? null, $attribute->getDefault(), $attribute->getArray());
                break;
            case Attribute::TYPE_FLOAT:
                /** @var FloatAttribute $attribute */
                $databaseService->createFloatAttribute($attribute->getCollection()->getDatabase()->getId(), $attribute->getCollection()->getId(), $attribute->getKey(), $attribute->getRequired(), null, null, $attribute->getDefault(), $attribute->getArray());
                break;
            case Attribute::TYPE_BOOLEAN:
                /** @var BoolAttribute $attribute */
                $databaseService->createBooleanAttribute($attribute->getCollection()->getDatabase()->getId(), $attribute->getCollection()->getId(), $attribute->getKey(), $attribute->getRequired(), $attribute->getDefault(), $attribute->getArray());
                break;
            case Attribute::TYPE_DATETIME:
                /** @var DateTimeAttribute $attribute */
                $databaseService->createDateTimeAttribute($attribute->getCollection()->getDatabase()->getId(), $attribute->getCollection()->getId(), $attribute->getKey(), $attribute->getRequired(), $attribute->getDefault(), $attribute->getArray());
                break;
            case Attribute::TYPE_EMAIL:
                /** @var EmailAttribute $attribute */
                $databaseService->createEmailAttribute($attribute->getCollection()->getDatabase()->getId(), $attribute->getCollection()->getId(), $attribute->getKey(), $attribute->getRequired(), $attribute->getDefault(), $attribute->getArray());
                break;
            case Attribute::TYPE_IP:
                /** @var IPAttribute $attribute */
                $databaseService->createIPAttribute($attribute->getCollection()->getDatabase()->getId(), $attribute->getCollection()->getId(), $attribute->getKey(), $attribute->getRequired(), $attribute->getDefault(), $attribute->getArray());
                break;
            case Attribute::TYPE_URL:
                /** @var URLAttribute $attribute */
                $databaseService->createUrlAttribute($attribute->getCollection()->getDatabase()->getId(), $attribute->getCollection()->getId(), $attribute->getKey(), $attribute->getRequired(), $attribute->getDefault(), $attribute->getArray());
                break;
            case Attribute::TYPE_ENUM:
                /** @var EnumAttribute $attribute */
                $databaseService->createEnumAttribute($attribute->getCollection()->getDatabase()->getId(), $attribute->getCollection()->getId(), $attribute->getKey(), $attribute->getElements(), $attribute->getRequired(), $attribute->getDefault(), $attribute->getArray());
                break;
            case Attribute::TYPE_RELATIONSHIP:
                /** @var RelationshipAttribute $attribute */
                $databaseService->createRelationshipAttribute($attribute->getCollection()->getDatabase()->getId(), $attribute->getCollection()->getId(), $attribute->getRelatedCollection(), $attribute->getRelationType(), $attribute->getTwoWay(), $attribute->getKey(), $attribute->getTwoWayKey(), $attribute->getOnDelete());
                break;
            default:
                throw new \Exception('Invalid attribute type');
        }

        // Wait for attribute to be created
        $this->awaitAttributeCreation($attribute, 5);
    }

    /**
     * Await Attribute Creation
     *
     * @param Attribute $attribute
     * @param int $timeout
     *
     * @return bool
     */
    public function awaitAttributeCreation(Attribute $attribute, int $timeout): bool
    {
        $databaseService = new Databases($this->client);

        $start = \time();

        while (\time() - $start < $timeout) {
            $response = $databaseService->getAttribute($attribute->getCollection()->getDatabase()->getId(), $attribute->getCollection()->getId(), $attribute->getKey());

            if ($response['status'] === 'available') {
                return true;
            }

            \usleep(500000);
        }

        throw new \Exception('Attribute creation timeout');
    }

    public function importDocumentResource(Resource $resource): Resource
    {
        $databaseService = new Databases($this->client);

        try {
            switch ($resource->getName()) {
                case Resource::TYPE_DOCUMENT:
                    /** @var Document $resource */
                    $databaseService->createDocument(
                        $resource->getDatabase()->getId(),
                        $resource->getCollection()->getId(),
                        $resource->getId(),
                        $resource->getData(),
                        $resource->getPermissions()
                    );
                    break;
            }

            $resource->setStatus(Resource::STATUS_SUCCESS);
        } catch (\Exception $e) {
            $resource->setStatus(Resource::STATUS_ERROR, $e->getMessage());
        } finally {
            return $resource;
        }
    }

    public function importFileResource(Resource $resource): Resource
    {
        $storageService = new Storage($this->client);

        $response = null;

        try {
            switch ($resource->getName()) {
                case Resource::TYPE_FILE:
                    /** @var File $resource */
                    $response = $storageService->createFile(
                        $resource->getBucket()->getId(),
                        $resource->getId(),
                        $resource->getFileName(),
                        $resource->getPermissions()
                    );
                    break;
                case Resource::TYPE_FILEDATA:
                    return $this->importFileData($resource);
                    break;
                case Resource::TYPE_BUCKET:
                    /** @var Bucket $resource */
                    $response = $storageService->createBucket(
                        $resource->getId(),
                        $resource->getBucketName(),
                        $resource->getPermissions(),
                        $resource->getFileSecurity(),
                        true, // Set to true for now, we'll come back later.
                        $resource->getMaxFileSize(),
                        $resource->getAllowedFileExtensions(),
                        $resource->getCompression(),
                        $resource->getEncryption(),
                        $resource->getAntiVirus()
                    );
                    $resource->setId($response['$id']);
            }

            $resource->setStatus(Resource::STATUS_SUCCESS);
        } catch (\Exception $e) {
            $resource->setStatus(Resource::STATUS_ERROR, $e->getMessage());
        } finally {
            return $resource;
        }
    }

    /**
     * Import File Data
     *
     * @param FileData $filePart
     * @returns FileData
     */
    public function importFileData(FileData $resource): FileData
    {
        $file = $resource->getFile();
        $bucketId = $file->getBucket()->getId();

        $response = null;

        if ($file->getSize() <= Transfer::STORAGE_MAX_CHUNK_SIZE) {
            $response = $this->client->call(
                'POST',
                "/v1/storage/buckets/{$bucketId}/files",
                [
                    'content-type' => 'multipart/form-data',
                ],
                [
                    'bucketId' => $bucketId,
                    'fileId' => $file->getId(),
                    'file' => new \CurlFile('data://' . $file->getMimeType() . ';base64,' . base64_encode($resource->getData()), $file->getMimeType(), $file->getFileName()),
                    'permissions' => $file->getPermissions(),
                ]
            );

            $resource->setStatus(Resource::STATUS_SUCCESS);
            return $resource;
        }

        $response = $this->client->call(
            'POST',
            "/v1/storage/buckets/{$bucketId}/files",
            [
                'content-type' => 'multipart/form-data',
                'content-range' => 'bytes ' . ($resource->getStart()) . '-' . ($resource->getEnd() == ($file->getSize() - 1) ? $file->getSize() : $resource->getEnd()) . '/' . $file->getSize(),
            ],
            [
                'bucketId' => $bucketId,
                'fileId' => $file->getId(),
                'file' => new \CurlFile('data://' . $file->getMimeType() . ';base64,' . base64_encode($resource->getData()), $file->getMimeType(), $file->getFileName()),
                'permissions' => $file->getPermissions(),
            ]
        );

        if ($resource->getEnd() == ($file->getSize() - 1)) {
            // Signatures for Encrypted files are invalid, so we skip the check
            if ($file->getBucket()->getEncryption() == false || $file->getSize() > (20 * 1024 * 1024)) {
                if ($response['signature'] !== $file->getSignature()) {
                    $resource->setStatus(Resource::STATUS_WARNING, 'File signature mismatch, Possibly corrupted.');
                }
            }
        }

        return $resource;
    }

    public function importAuthResource(Resource $resource): Resource
    {
        $userService = new Users($this->client);
        $teamService = new Teams($this->client);

        try {
            switch ($resource->getName()) {
                case Resource::TYPE_USER:
                    /** @var User $resource */
                    if (in_array(User::TYPE_EMAIL, $resource->getTypes())) {
                        $this->importPasswordUser($resource);
                    } else {
                        $userService->create($resource->getId(), $resource->getEmail(), $resource->getPhone(), null, $resource->getName());
                    }

                    if ($resource->getUsername()) {
                        $userService->updateName($resource->getId(), $resource->getUsername());
                    }

                    if ($resource->getPhone()) {
                        $userService->updatePhone($resource->getId(), $resource->getPhone());
                    }

                    if ($resource->getEmailVerified()) {
                        $userService->updateEmailVerification($resource->getId(), $resource->getEmailVerified());
                    }

                    if ($resource->getPhoneVerified()) {
                        $userService->updatePhoneVerification($resource->getId(), $resource->getPhoneVerified());
                    }

                    if ($resource->getDisabled()) {
                        $userService->updateStatus($resource->getId(), !$resource->getDisabled());
                    }

                    break;
                case Resource::TYPE_TEAM:
                    /** @var Team $resource */
                    $teamService->create($resource->getId(), $resource->getName());
                    $teamService->updatePrefs($resource->getId(), $resource->getPrefs());
                    break;
                case Resource::TYPE_TEAM_MEMBERSHIP:
                    /** @var TeamMembership $resource */
                    //TODO: Discuss in meeting.
                    // $teamService->createMembership($resource->getTeam()->getId(), $resource->getRoles(), )
                    // break;
            }
        } catch (\Exception $e) {
            $resource->setStatus(Resource::STATUS_ERROR, $e->getMessage());
        } finally {
            return $resource;
        }
    }

    public function importPasswordUser(User $user): array|null
    {
        $auth = new Users($this->client);
        $hash = $user->getPasswordHash();
        $result = null;

        if (empty($hash->getHash())) {
            throw new \Exception('User password hash is empty');
        }

        switch ($hash->getAlgorithm()) {
            case Hash::SCRYPT_MODIFIED:
                $result = $auth->createScryptModifiedUser(
                    $user->getId(),
                    $user->getEmail(),
                    $hash->getHash(),
                    $hash->getSalt(),
                    $hash->getSeparator(),
                    $hash->getSigningKey(),
                    $user->getUsername()
                );
                break;
            case Hash::BCRYPT:
                $result = $auth->createBcryptUser(
                    $user->getId(),
                    $user->getEmail(),
                    $hash->getHash(),
                    $user->getUsername()
                );
                break;
            case Hash::ARGON2:
                $result = $auth->createArgon2User(
                    $user->getId(),
                    $user->getEmail(),
                    $hash->getHash(),
                    $user->getUsername()
                );
                break;
            case Hash::SHA256:
                $result = $auth->createShaUser(
                    $user->getId(),
                    $user->getEmail(),
                    $hash->getHash(),
                    'sha256',
                    $user->getUsername()
                );
                break;
            case Hash::PHPASS:
                $result = $auth->createPHPassUser(
                    $user->getId(),
                    $user->getEmail(),
                    $hash->getHash(),
                    $user->getUsername()
                );
                break;
            case Hash::SCRYPT:
                $result = $auth->createScryptUser(
                    $user->getId(),
                    $user->getEmail(),
                    $hash->getHash(),
                    $hash->getSalt(),
                    $hash->getPasswordCpu(),
                    $hash->getPasswordMemory(),
                    $hash->getPasswordParallel(),
                    $hash->getPasswordLength(),
                    $user->getUsername()
                );
                break;
        }

        return $result;
    }

    public function importFunctionResource(Resource $resource): Resource
    {
        $functions = new Functions($this->client);

        try {
            switch ($resource->getName()) {
                case Resource::TYPE_FUNCTION:
                    /** @var Func $resource */
                    $functions->create(
                        $resource->getId(),
                        $resource->getFunctionName(),
                        $resource->getRuntime(),
                        $resource->getExecute(),
                        $resource->getEvents(),
                        $resource->getSchedule(),
                        $resource->getTimeout(),
                        $resource->getEnabled()
                    );
                case Resource::TYPE_ENVVAR:
                    /** @var EnvVar $resource */
                    $functions->createVariable(
                        $resource->getFunc()->getId(),
                        $resource->getKey(),
                        $resource->getValue()
                    );
            }

            $resource->setStatus(Resource::STATUS_SUCCESS);
        } catch (\Exception $e) {
            $resource->setStatus(Resource::STATUS_ERROR, $e->getMessage());
        } finally {
            return $resource;
        }
    }
}
