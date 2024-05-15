<?php

namespace Utopia\Migration\Destinations;

use Appwrite\Client;
use Appwrite\InputFile;
use Appwrite\Services\Databases;
use Appwrite\Services\Functions;
use Appwrite\Services\Storage;
use Appwrite\Services\Teams;
use Appwrite\Services\Users;
use Utopia\Migration\Destination;
use Utopia\Migration\Exception;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Auth\Hash;
use Utopia\Migration\Resources\Auth\Membership;
use Utopia\Migration\Resources\Auth\Team;
use Utopia\Migration\Resources\Auth\User;
use Utopia\Migration\Resources\Database\Attribute;
use Utopia\Migration\Resources\Database\Attributes\DateTime;
use Utopia\Migration\Resources\Database\Attributes\Decimal;
use Utopia\Migration\Resources\Database\Attributes\Email;
use Utopia\Migration\Resources\Database\Attributes\Enum;
use Utopia\Migration\Resources\Database\Attributes\IP;
use Utopia\Migration\Resources\Database\Attributes\Relationship;
use Utopia\Migration\Resources\Database\Attributes\Text;
use Utopia\Migration\Resources\Database\Collection;
use Utopia\Migration\Resources\Database\Database;
use Utopia\Migration\Resources\Database\Document;
use Utopia\Migration\Resources\Functions\Deployment;
use Utopia\Migration\Resources\Functions\EnvVar;
use Utopia\Migration\Resources\Functions\Func;
use Utopia\Migration\Resources\Storage\Bucket;
use Utopia\Migration\Resources\Storage\File;
use Utopia\Migration\Resources\Storage\Index;
use Utopia\Migration\Transfer;

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
        if (empty($resources)) {
            $resources = $this->getSupportedResources();
        }

        $databases = new Databases($this->client);
        $functions = new Functions($this->client);
        $storage = new Storage($this->client);
        $teams = new Teams($this->client);
        $users = new Users($this->client);

        $currentPermission = '';
        // Most of these API calls are purposely wrong. Appwrite will throw a 403 before a 400.
        // We want to make sure the API key has full read and write access to the project.

        try {
            // Auth
            if (in_array(Resource::TYPE_USER, $resources)) {
                $currentPermission = 'users.read';
                $users->list();

                $currentPermission = 'users.write';
                $users->create('', '', '');
            }

            if (in_array(Resource::TYPE_TEAM, $resources)) {
                $currentPermission = 'teams.read';
                $teams->list();

                $currentPermission = 'teams.write';
                $teams->create('', '');
            }

            if (in_array(Resource::TYPE_MEMBERSHIP, $resources)) {
                $currentPermission = 'memberships.read';
                $teams->listMemberships('');

                $currentPermission = 'memberships.write';
                $teams->createMembership('', [], '');
            }

            // Database
            if (in_array(Resource::TYPE_DATABASE, $resources)) {
                $currentPermission = 'database.read';
                $databases->list();

                $currentPermission = 'database.write';
                $databases->create('', '');
            }

            if (in_array(Resource::TYPE_COLLECTION, $resources)) {
                $currentPermission = 'collections.read';
                $databases->listCollections('');

                $currentPermission = 'collections.write';
                $databases->createCollection('', '', '');
            }

            if (in_array(Resource::TYPE_ATTRIBUTE, $resources)) {
                $currentPermission = 'attributes.read';
                $databases->listAttributes('', '');

                $currentPermission = 'attributes.write';
                $databases->createStringAttribute('', '', '', 0, false);
            }

            if (in_array(Resource::TYPE_INDEX, $resources)) {
                $currentPermission = 'indexes.read';
                $databases->listIndexes('', '');

                $currentPermission = 'indexes.write';
                $databases->createIndex('', '', '', '', []);
            }

            if (in_array(Resource::TYPE_DOCUMENT, $resources)) {
                $currentPermission = 'documents.read';
                $databases->listDocuments('', '');

                $currentPermission = 'documents.write';
                $databases->createDocument('', '', '', []);
            }

            // Storage
            if (in_array(Resource::TYPE_BUCKET, $resources)) {
                $currentPermission = 'storage.read';
                $storage->listBuckets();

                $currentPermission = 'storage.write';
                $storage->createBucket('', '');
            }

            if (in_array(Resource::TYPE_FILE, $resources)) {
                $currentPermission = 'files.read';
                $storage->listFiles('');

                $currentPermission = 'files.write';
                $storage->createFile('', '', new InputFile());
            }

            // Functions
            if (in_array(Resource::TYPE_FUNCTION, $resources)) {
                $currentPermission = 'functions.read';
                $functions->list();

                $currentPermission = 'functions.write';
                $functions->create('', '', '');
            }

            return [];
        } catch (\Throwable $exception) {
            if ($exception->getCode() === 403) {
                throw new \Exception('Missing permission: '.$currentPermission);
            } else {
                throw $exception;
            }
        }
    }

    protected function import(array $resources, callable $callback): void
    {
        if (empty($resources)) {
            return;
        }

        foreach ($resources as $resource) {
            /** @var resource $resource */
            $resource->setStatus(Resource::STATUS_PROCESSING);

            try {
                switch ($resource->getGroup()) {
                    case Transfer::GROUP_DATABASES:
                        $responseResource = $this->importDatabaseResource($resource);
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
            } catch (\Throwable $e) {
                if ($e->getCode() === 409) {
                    $resource->setStatus(Resource::STATUS_SKIPPED, $e->getMessage());
                } else {
                    $resource->setStatus(Resource::STATUS_ERROR, $e->getMessage());
                    $this->addError(new Exception(
                        resourceType: $resource->getGroup(),
                        resourceId: $resource->getId(),
                        message: $e->getMessage(),
                        code: $e->getCode()
                    ));
                }

                $responseResource = $resource;
            }

            $this->cache->update($responseResource);
        }

        $callback($resources);
    }

    public function importDatabaseResource(Resource $resource): Resource
    {
        $databaseService = new Databases($this->client);

        switch ($resource->getName()) {
            case Resource::TYPE_DATABASE:
                /** @var Database $resource */
                $databaseService->create($resource->getId(), $resource->getDBName());
                break;
            case Resource::TYPE_COLLECTION:
                /** @var Collection $resource */
                $newCollection = $databaseService->createCollection(
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
                $databaseService->createIndex(
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
                // Check if document has already been created by subcollection
                $docExists = array_key_exists($resource->getId(), $this->cache->get(Resource::TYPE_DOCUMENT));

                if ($docExists) {
                    $resource->setStatus(Resource::STATUS_SKIPPED, 'Document has been already created by relationship');

                    return $resource;
                }

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

        return $resource;
    }

    public function createAttribute(Attribute $attribute): void
    {
        $databaseService = new Databases($this->client);

        switch ($attribute->getTypeName()) {
            case Attribute::TYPE_STRING:
                /** @var Text $attribute */
                $databaseService->createStringAttribute($attribute->getCollection()->getDatabase()->getId(), $attribute->getCollection()->getId(), $attribute->getKey(), $attribute->getSize(), $attribute->getRequired(), $attribute->getDefault(), $attribute->getArray());
                break;
            case Attribute::TYPE_INTEGER:
                /** @var int $attribute */
                $databaseService->createIntegerAttribute($attribute->getCollection()->getDatabase()->getId(), $attribute->getCollection()->getId(), $attribute->getKey(), $attribute->getRequired(), $attribute->getMin(), $attribute->getMax() ?? null, $attribute->getDefault(), $attribute->getArray());
                break;
            case Attribute::TYPE_FLOAT:
                /** @var Decimal $attribute */
                $databaseService->createFloatAttribute($attribute->getCollection()->getDatabase()->getId(), $attribute->getCollection()->getId(), $attribute->getKey(), $attribute->getRequired(), null, null, $attribute->getDefault(), $attribute->getArray());
                break;
            case Attribute::TYPE_BOOLEAN:
                /** @var bool $attribute */
                $databaseService->createBooleanAttribute($attribute->getCollection()->getDatabase()->getId(), $attribute->getCollection()->getId(), $attribute->getKey(), $attribute->getRequired(), $attribute->getDefault(), $attribute->getArray());
                break;
            case Attribute::TYPE_DATETIME:
                /** @var DateTime $attribute */
                $databaseService->createDatetimeAttribute($attribute->getCollection()->getDatabase()->getId(), $attribute->getCollection()->getId(), $attribute->getKey(), $attribute->getRequired(), $attribute->getDefault(), $attribute->getArray());
                break;
            case Attribute::TYPE_EMAIL:
                /** @var Email $attribute */
                $databaseService->createEmailAttribute($attribute->getCollection()->getDatabase()->getId(), $attribute->getCollection()->getId(), $attribute->getKey(), $attribute->getRequired(), $attribute->getDefault(), $attribute->getArray());
                break;
            case Attribute::TYPE_IP:
                /** @var IP $attribute */
                $databaseService->createIpAttribute($attribute->getCollection()->getDatabase()->getId(), $attribute->getCollection()->getId(), $attribute->getKey(), $attribute->getRequired(), $attribute->getDefault(), $attribute->getArray());
                break;
            case Attribute::TYPE_URL:
                /** @var URLAttribute $attribute */
                $databaseService->createUrlAttribute($attribute->getCollection()->getDatabase()->getId(), $attribute->getCollection()->getId(), $attribute->getKey(), $attribute->getRequired(), $attribute->getDefault(), $attribute->getArray());
                break;
            case Attribute::TYPE_ENUM:
                /** @var Enum $attribute */
                $databaseService->createEnumAttribute($attribute->getCollection()->getDatabase()->getId(), $attribute->getCollection()->getId(), $attribute->getKey(), $attribute->getElements(), $attribute->getRequired(), $attribute->getDefault(), $attribute->getArray());
                break;
            case Attribute::TYPE_RELATIONSHIP:
                /** @var Relationship $attribute */
                $databaseService->createRelationshipAttribute(
                    $attribute->getCollection()->getDatabase()->getId(),
                    $attribute->getCollection()->getId(),
                    $attribute->getRelatedCollection(),
                    $attribute->getRelationType(),
                    $attribute->getTwoWay(),
                    $attribute->getKey(),
                    $attribute->getTwoWayKey(),
                    $attribute->getOnDelete()
                );
                break;
            default:
                throw new \Exception('Invalid attribute type');
        }

        // Wait for attribute to be created
        $this->awaitAttributeCreation($attribute, 5);
    }

    /**
     * Await Attribute Creation
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

    public function importFileResource(File|Bucket $resource): Resource
    {
        $storageService = new Storage($this->client);

        $response = null;

        switch ($resource->getName()) {
            case Resource::TYPE_FILE:
                /** @var File $resource */
                return $this->importFile($resource);
                break;
            case Resource::TYPE_BUCKET:
                /** @var Bucket $resource */
                if (! $resource->getUpdateLimits()) {
                    $response = $storageService->createBucket(
                        $resource->getId() ?? 'unique()',
                        $resource->getBucketName(),
                        $resource->getPermissions(),
                        $resource->getFileSecurity(),
                        true, // Set to true for now, we'll come back later.
                        null,
                        null,
                        $resource->getCompression() ?? 'none',
                        $resource->getEncryption() ?? null,
                        $resource->getAntiVirus() ?? null
                    );
                } else {
                    $response = $storageService->updateBucket(
                        $resource->getId(),
                        $resource->getBucketName(),
                        $resource->getPermissions(),
                        $resource->getFileSecurity(),
                        $resource->getEnabled(),
                        $resource->getMaxFileSize() ?? null,
                        $resource->getAllowedFileExtensions() ?? null,
                        $resource->getCompression() ?? 'none',
                        $resource->getEncryption() ?? null,
                        $resource->getAntiVirus() ?? null
                    );
                }
                $resource->setId($response['$id']);
        }

        $resource->setStatus(Resource::STATUS_SUCCESS);

        return $resource;
    }

    /**
     * Import File Data
     *
     * @returns File
     */
    public function importFile(File $file): File
    {
        $bucketId = $file->getBucket()->getId();

        $response = null;

        if ($file->getSize() <= Transfer::STORAGE_MAX_CHUNK_SIZE) {
            $response = $this->client->call(
                'POST',
                "/storage/buckets/{$bucketId}/files",
                [
                    'content-type' => 'multipart/form-data',
                    'X-Appwrite-Project' => $this->project,
                    'X-Appwrite-Key' => $this->key,
                ],
                [
                    'bucketId' => $bucketId,
                    'fileId' => $file->getId(),
                    'file' => new \CurlFile('data://'.$file->getMimeType().';base64,'.base64_encode($file->getData()), $file->getMimeType(), $file->getFileName()),
                    'permissions' => $file->getPermissions(),
                ]
            );

            $file->setStatus(Resource::STATUS_SUCCESS);
            $file->setData('');

            return $file;
        }

        $response = $this->client->call(
            'POST',
            "/storage/buckets/{$bucketId}/files",
            [
                'content-type' => 'multipart/form-data',
                'content-range' => 'bytes '.($file->getStart()).'-'.($file->getEnd() == ($file->getSize() - 1) ? $file->getSize() : $file->getEnd()).'/'.$file->getSize(),
                'X-Appwrite-Project' => $this->project,
                'x-Appwrite-Key' => $this->key,
            ],
            [
                'bucketId' => $bucketId,
                'fileId' => $file->getId(),
                'file' => new \CurlFile('data://'.$file->getMimeType().';base64,'.base64_encode($file->getData()), $file->getMimeType(), $file->getFileName()),
                'permissions' => $file->getPermissions(),
            ]
        );

        if ($file->getEnd() == ($file->getSize() - 1)) {
            // Signatures for Encrypted files are invalid, so we skip the check
            if ($file->getBucket()->getEncryption() == false || $file->getSize() > (20 * 1024 * 1024)) {
                if ($response['signature'] !== $file->getSignature()) {
                    $file->setStatus(Resource::STATUS_WARNING, 'File signature mismatch, Possibly corrupted.');
                }
            } else {
                $file->setStatus(Resource::STATUS_SUCCESS);
            }
        }

        $file->setData('');
        return $file;
    }

    public function importAuthResource(Resource $resource): Resource
    {
        $userService = new Users($this->client);
        $teamService = new Teams($this->client);

        switch ($resource->getName()) {
            case Resource::TYPE_USER:
                /** @var User $resource */
                if (in_array(User::TYPE_PASSWORD, $resource->getTypes())) {
                    $this->importPasswordUser($resource);
                } elseif (in_array(User::TYPE_OAUTH, $resource->getTypes())) {
                    $resource->setStatus(Resource::STATUS_WARNING, 'OAuth users cannot be imported.');

                    return $resource;
                } else {
                    $userService->create(
                        $resource->getId(),
                        $resource->getEmail(),
                        in_array(User::TYPE_PHONE, $resource->getTypes()) ? $resource->getPhone() : null,
                        null,
                        $resource->getName()
                    );
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
                    $userService->updateStatus($resource->getId(), ! $resource->getDisabled());
                }

                if ($resource->getPreferences()) {
                    $userService->updatePrefs($resource->getId(), $resource->getPreferences());
                }

                if ($resource->getLabels()) {
                    $userService->updateLabels($resource->getId(), $resource->getLabels());
                }

                break;
            case Resource::TYPE_TEAM:
                /** @var Team $resource */
                $teamService->create($resource->getId(), $resource->getTeamName());
                $teamService->updatePrefs($resource->getId(), $resource->getPreferences());
                break;
            case Resource::TYPE_MEMBERSHIP:
                /** @var Membership $resource */
                $user = $resource->getUser();
                $teamService->createMembership(
                    $resource->getTeam()->getId(),
                    $resource->getRoles(),
                    userId: $user->getId(),
                );
                break;
        }

        $resource->setStatus(Resource::STATUS_SUCCESS);

        return $resource;
    }

    public function importPasswordUser(User $user): ?array
    {
        $auth = new Users($this->client);
        $hash = $user->getPasswordHash();
        $result = null;

        if (! $hash) {
            throw new \Exception('Password hash is missing');
        }

        switch ($hash->getAlgorithm()) {
            case Hash::ALGORITHM_SCRYPT_MODIFIED:
                $result = $auth->createScryptModifiedUser(
                    $user->getId(),
                    $user->getEmail(),
                    $hash->getHash(),
                    $hash->getSalt(),
                    $hash->getSeparator(),
                    $hash->getSigningKey(),
                    empty($user->getUsername()) ? null : $user->getUsername()
                );
                break;
            case Hash::ALGORITHM_BCRYPT:
                $result = $auth->createBcryptUser(
                    $user->getId(),
                    $user->getEmail(),
                    $hash->getHash(),
                    empty($user->getUsername()) ? null : $user->getUsername()
                );
                break;
            case Hash::ALGORITHM_ARGON2:
                $result = $auth->createArgon2User(
                    $user->getId(),
                    $user->getEmail(),
                    $hash->getHash(),
                    empty($user->getUsername()) ? null : $user->getUsername()
                );
                break;
            case Hash::ALGORITHM_SHA256:
                $result = $auth->createShaUser(
                    $user->getId(),
                    $user->getEmail(),
                    $hash->getHash(),
                    'sha256',
                    empty($user->getUsername()) ? null : $user->getUsername()
                );
                break;
            case Hash::ALGORITHM_PHPASS:
                $result = $auth->createPHPassUser(
                    $user->getId(),
                    $user->getEmail(),
                    $hash->getHash(),
                    empty($user->getUsername()) ? null : $user->getUsername()
                );
                break;
            case Hash::ALGORITHM_SCRYPT:
                $result = $auth->createScryptUser(
                    $user->getId(),
                    $user->getEmail(),
                    $hash->getHash(),
                    $hash->getSalt(),
                    $hash->getPasswordCpu(),
                    $hash->getPasswordMemory(),
                    $hash->getPasswordParallel(),
                    $hash->getPasswordLength(),
                    empty($user->getUsername()) ? null : $user->getUsername()
                );
                break;
            case Hash::ALGORITHM_PLAINTEXT:
                $result = $auth->create(
                    $user->getId(),
                    $user->getEmail(),
                    $user->getPhone(),
                    $hash->getHash(),
                    empty($user->getUsername()) ? null : $user->getUsername()
                );
                break;
        }

        return $result;
    }

    public function importFunctionResource(Resource $resource): Resource
    {
        $functions = new Functions($this->client);

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
                break;
            case Resource::TYPE_ENVIRONMENT_VARIABLE:
                /** @var EnvVar $resource */
                $functions->createVariable(
                    $resource->getFunc()->getId(),
                    $resource->getKey(),
                    $resource->getValue()
                );
                break;
            case Resource::TYPE_DEPLOYMENT:
                return $this->importDeployment($resource);
                break;
        }

        $resource->setStatus(Resource::STATUS_SUCCESS);

        return $resource;
    }

    private function importDeployment(Deployment $deployment): Resource
    {
        $functionId = $deployment->getFunction()->getId();

        $response = null;

        if ($deployment->getSize() <= Transfer::STORAGE_MAX_CHUNK_SIZE) {
            $response = $this->client->call(
                'POST',
                "/functions/{$functionId}/deployments",
                [
                    'content-type' => 'multipart/form-data',
                ],
                [
                    'functionId' => $functionId,
                    'code' => new \CurlFile('data://application/gzip;base64,'.base64_encode($deployment->getData()), 'application/gzip', 'deployment.tar.gz'),
                    'activate' => $deployment->getActivated() ? 'true' : 'false',
                    'entrypoint' => $deployment->getEntrypoint(),
                ]
            );

            $deployment->setStatus(Resource::STATUS_SUCCESS);

            return $deployment;
        }

        $response = $this->client->call(
            'POST',
            "/v1/functions/{$functionId}/deployments",
            [
                'content-type' => 'multipart/form-data',
                'content-range' => 'bytes '.($deployment->getStart()).'-'.($deployment->getEnd() == ($deployment->getSize() - 1) ? $deployment->getSize() : $deployment->getEnd()).'/'.$deployment->getSize(),
                'x-appwrite-id' => $deployment->getId(),
            ],
            [
                'functionId' => $functionId,
                'code' => new \CurlFile('data://application/gzip;base64,'.base64_encode($deployment->getData()), 'application/gzip', 'deployment.tar.gz'),
                'activate' => $deployment->getActivated(),
                'entrypoint' => $deployment->getEntrypoint(),
            ]
        );

        if ($deployment->getStart() === 0) {
            $deployment->setId($response['$id']);
        }

        if ($deployment->getEnd() == ($deployment->getSize() - 1)) {
            $deployment->setStatus(Resource::STATUS_SUCCESS);
        } else {
            $deployment->setStatus(Resource::STATUS_PENDING);
        }

        return $deployment;
    }
}
