<?php

namespace Utopia\Migration\Destinations;

use Appwrite\AppwriteException;
use Appwrite\Client;
use Appwrite\InputFile;
use Appwrite\Services\Databases;
use Appwrite\Services\Functions;
use Appwrite\Services\Storage;
use Appwrite\Services\Teams;
use Appwrite\Services\Users;
use Override;
use Utopia\Migration\Destination;
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
use Utopia\Migration\Transfer;

class Appwrite extends Destination
{
    protected Client $client;
    protected string $project;

    protected string $key;

    private Databases $databases;
    private Functions $functions;
    private Storage $storage;
    private Teams $teams;
    private Users $users;


    public function __construct(string $project, string $endpoint, string $key)
    {
        $this->project = $project;
        $this->endpoint = $endpoint;
        $this->key = $key;

        $this->client = (new Client())
            ->setEndpoint($endpoint)
            ->setProject($project)
            ->setKey($key);

        $this->databases = new Databases($this->client);
        $this->functions = new Functions($this->client);
        $this->storage = new Storage($this->client);
        $this->teams = new Teams($this->client);
        $this->users = new Users($this->client);
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
        ];
    }

    /**
     * @param array<string> $resources
     * @throws AppwriteException
     * @throws \Exception
     */
    #[Override]
    public function report(array $resources = []): array
    {
        if (empty($resources)) {
            $resources = $this->getSupportedResources();
        }

        $scope = '';

        // Most of these API calls are purposely wrong. Appwrite will throw a 403 before a 400.
        // We want to make sure the API key has full read and write access to the project.
        try {
            // Auth
            if (\in_array(Resource::TYPE_USER, $resources)) {
                $scope = 'users.read';
                $this->users->list();

                $scope = 'users.write';
                $this->users->create('');
            }

            if (\in_array(Resource::TYPE_TEAM, $resources)) {
                $scope = 'teams.read';
                $this->teams->list();

                $scope = 'teams.write';
                $this->teams->create('', '');
            }

            if (\in_array(Resource::TYPE_MEMBERSHIP, $resources)) {
                $scope = 'memberships.read';
                $this->teams->listMemberships('');

                $scope = 'memberships.write';
                $this->teams->createMembership('', [], '');
            }

            // Database
            if (\in_array(Resource::TYPE_DATABASE, $resources)) {
                $scope = 'database.read';
                $this->databases->list();

                $scope = 'database.write';
                $this->databases->create('', '');
            }

            if (\in_array(Resource::TYPE_COLLECTION, $resources)) {
                $scope = 'collections.read';
                $this->databases->listCollections('');

                $scope = 'collections.write';
                $this->databases->createCollection('', '', '');
            }

            if (\in_array(Resource::TYPE_ATTRIBUTE, $resources)) {
                $scope = 'attributes.read';
                $this->databases->listAttributes('', '');

                $scope = 'attributes.write';
                $this->databases->createStringAttribute('', '', '', 0, false);
            }

            if (\in_array(Resource::TYPE_INDEX, $resources)) {
                $scope = 'indexes.read';
                $this->databases->listIndexes('', '');

                $scope = 'indexes.write';
                $this->databases->createIndex('', '', '', '', []);
            }

            if (\in_array(Resource::TYPE_DOCUMENT, $resources)) {
                $scope = 'documents.read';
                $this->databases->listDocuments('', '');

                $scope = 'documents.write';
                $this->databases->createDocument('', '', '', []);
            }

            // Storage
            if (\in_array(Resource::TYPE_BUCKET, $resources)) {
                $scope = 'storage.read';
                $this->storage->listBuckets();

                $scope = 'storage.write';
                $this->storage->createBucket('', '');
            }

            if (\in_array(Resource::TYPE_FILE, $resources)) {
                $scope = 'files.read';
                $this->storage->listFiles('');

                $scope = 'files.write';
                $this->storage->createFile('', '', new InputFile());
            }

            // Functions
            if (\in_array(Resource::TYPE_FUNCTION, $resources)) {
                $scope = 'functions.read';
                $this->functions->list();

                $scope = 'functions.write';
                $this->functions->create('', '', '');
            }

        } catch (AppwriteException $e) {
            if ($e->getCode() === 403) {
                throw new \Exception('Missing scope: ' . $scope, previous: $e);
            }
            throw $e;
        }

        return [];
    }

    /**
     * @param array<Resource> $resources
     * @param callable $callback
     * @return void
     */
    #[Override]
    protected function import(array $resources, callable $callback): void
    {
        var_dump("Appwrite importing.......");
        var_dump($resources);

        if (empty($resources)) {
            return;
        }

        foreach ($resources as $resource) {
            $resource->setStatus(Resource::STATUS_PROCESSING);

            try {
                $responseResource = match ($resource->getGroup()) {
                    Transfer::GROUP_DATABASES => $this->importDatabaseResource($resource),
                    Transfer::GROUP_STORAGE => $this->importFileResource($resource),
                    Transfer::GROUP_AUTH => $this->importAuthResource($resource),
                    Transfer::GROUP_FUNCTIONS => $this->importFunctionResource($resource),
                    default => throw new \Exception('Invalid resource group'),
                };
            } catch (\Throwable $e) {

                var_dump("Appwrite import Throwable ==== ");
                var_dump("getCode ==== ");
                var_dump($e->getCode());
                var_dump("getMessage ==== ");
                var_dump($e->getMessage());

                if ($e->getCode() ===  409) {
                    // DATABASE_ALREADY_EXISTS why SKIP? not termination
                    $resource->setStatus(Resource::STATUS_SKIPPED, $e->getMessage());
                } else {
                    $resource->setStatus(Resource::STATUS_ERROR, $e->getMessage());

                    $this->addError(new Exception(
                        resourceType: $resource->getGroup(),
                        message: $e->getMessage(),
                        code: $e->getCode(),
                        resourceId: $resource->getId()
                    ));
                }

                $responseResource = $resource;
            }

            $this->cache->update($responseResource);
        }

        $callback($resources);
    }

    /**
     * @throws AppwriteException
     * @throws \Exception
     */
    public function importDatabaseResource(Resource $resource): Resource
    {
        var_dump("Destination Appwrite::importDatabaseResource === " . $resource->getName());

        $this->databases = new Databases($this->client);

        switch ($resource->getName()) {
            case Resource::TYPE_DATABASE:
                /** @var Database $resource */
                $this->databases->create(
                    $resource->getId(),
                    $resource->getDBName()
                );
                break;
            case Resource::TYPE_COLLECTION:
                /** @var Collection $resource */
                $newCollection = $this->databases->createCollection(
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
                $this->databases->createIndex(
                    $resource->getCollection()->getDatabase()->getId(),
                    $resource->getCollection()->getId(),
                    $resource->getKey(),
                    Index::getIndexType($resource->getType()),
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
                // Check if document has already been created
                $exists = \array_key_exists(
                    $resource->getId(),
                    $this->cache->get(Resource::TYPE_DOCUMENT)
                );

                if ($exists) {
                    $resource->setStatus(
                        Resource::STATUS_SKIPPED,
                        'Document has been already created by relationship'
                    );

                    return $resource;
                }

                $this->databases->createDocument(
                    $resource->getCollection()->getDatabase()->getId(),
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

    /**
     * @throws AppwriteException
     * @throws \Exception
     */
    public function createAttribute(Attribute $attribute): void
    {
        switch ($attribute->getTypeName()) {
            case Attribute::TYPE_STRING:
                /** @var Text $attribute */
                $this->databases->createStringAttribute(
                    $attribute->getCollection()->getDatabase()->getId(),
                    $attribute->getCollection()->getId(),
                    $attribute->getKey(),
                    $attribute->getSize(),
                    $attribute->getRequired(),
                    $attribute->getDefault(),
                    $attribute->getArray()
                );
                break;
            case Attribute::TYPE_INTEGER:
                /** @var \Utopia\Migration\Resources\Database\Attributes\Integer $attribute */
                $this->databases->createIntegerAttribute(
                    $attribute->getCollection()->getDatabase()->getId(),
                    $attribute->getCollection()->getId(),
                    $attribute->getKey(),
                    $attribute->getRequired(),
                    $attribute->getMin(),
                    $attribute->getMax(),
                    $attribute->getDefault(),
                    $attribute->getArray()
                );
                break;
            case Attribute::TYPE_FLOAT:
                /** @var Decimal $attribute */
                // todo: Change createFloatAttribute min/max to accept float!!!

                $this->databases->createFloatAttribute(
                    $attribute->getCollection()->getDatabase()->getId(),
                    $attribute->getCollection()->getId(),
                    $attribute->getKey(),
                    $attribute->getRequired(),
                    $attribute->getMin(),
                    $attribute->getMax(),
                    $attribute->getDefault(),
                    $attribute->getArray()
                );
                break;
            case Attribute::TYPE_BOOLEAN:
                /** @var Boolean $attribute */
                $this->databases->createBooleanAttribute(
                    $attribute->getCollection()->getDatabase()->getId(),
                    $attribute->getCollection()->getId(),
                    $attribute->getKey(),
                    $attribute->getRequired(),
                    $attribute->getDefault(),
                    $attribute->getArray()
                );
                break;
            case Attribute::TYPE_DATETIME:
                /** @var DateTime $attribute */
                $this->databases->createDatetimeAttribute(
                    $attribute->getCollection()->getDatabase()->getId(),
                    $attribute->getCollection()->getId(),
                    $attribute->getKey(),
                    $attribute->getRequired(),
                    $attribute->getDefault(),
                    $attribute->getArray()
                );
                break;
            case Attribute::TYPE_EMAIL:
                /** @var Email $attribute */
                $this->databases->createEmailAttribute(
                    $attribute->getCollection()->getDatabase()->getId(),
                    $attribute->getCollection()->getId(),
                    $attribute->getKey(),
                    $attribute->getRequired(),
                    $attribute->getDefault(),
                    $attribute->getArray()
                );
                break;
            case Attribute::TYPE_IP:
                /** @var IP $attribute */
                $this->databases->createIpAttribute(
                    $attribute->getCollection()->getDatabase()->getId(),
                    $attribute->getCollection()->getId(),
                    $attribute->getKey(),
                    $attribute->getRequired(),
                    $attribute->getDefault(),
                    $attribute->getArray()
                );
                break;
            case Attribute::TYPE_URL:
                /** @var URL $attribute */
                $this->databases->createUrlAttribute(
                    $attribute->getCollection()->getDatabase()->getId(),
                    $attribute->getCollection()->getId(),
                    $attribute->getKey(),
                    $attribute->getRequired(),
                    $attribute->getDefault(),
                    $attribute->getArray()
                );
                break;
            case Attribute::TYPE_ENUM:
                /** @var Enum $attribute */
                $this->databases->createEnumAttribute(
                    $attribute->getCollection()->getDatabase()->getId(),
                    $attribute->getCollection()->getId(),
                    $attribute->getKey(),
                    $attribute->getElements(),
                    $attribute->getRequired(),
                    $attribute->getDefault(),
                    $attribute->getArray()
                );
                break;
            case Attribute::TYPE_RELATIONSHIP:
                /** @var Relationship $attribute */
                $this->databases->createRelationshipAttribute(
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
     * @throws \Exception
     */
    public function awaitAttributeCreation(Attribute $attribute, int $timeout): bool
    {
        $start = \time();

        while (\time() - $start < $timeout) {
            $response = $this->databases->getAttribute($attribute->getCollection()->getDatabase()->getId(), $attribute->getCollection()->getId(), $attribute->getKey());

            if ($response['status'] === 'available') {
                return true;
            }

            \usleep(500000);
        }

        throw new \Exception('Attribute creation timeout');
    }

    /**
     * @throws AppwriteException
     */
    public function importFileResource(Resource $resource): Resource
    {
        switch ($resource->getName()) {
            case Resource::TYPE_FILE:
                /** @var File $resource */
                return $this->importFile($resource);
            case Resource::TYPE_BUCKET:
                /** @var Bucket $resource */
                $response = $this->storage->createBucket(
                    $resource->getId(),
                    $resource->getBucketName(),
                    $resource->getPermissions(),
                    $resource->getFileSecurity(),
                    $resource->getEnabled(),
                    $resource->getMaxFileSize(),
                    $resource->getAllowedFileExtensions(),
                    $resource->getCompression(),
                    $resource->getEncryption(),
                    $resource->getAntiVirus()
                );

                $resource->setId($response['$id']);
        }

        $resource->setStatus(Resource::STATUS_SUCCESS);

        return $resource;
    }

    /**
     * Import File Data
     *
     * @returns File
     * @throws AppwriteException
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
                'content-range' => 'bytes '.($file->getStart()) . '-' . ($file->getEnd() == ($file->getSize() - 1) ? $file->getSize() : $file->getEnd()) . '/' . $file->getSize(),
                'x-appwrite-project' => $this->project,
                'x-appwrite-key' => $this->key,
            ],
            [
                'bucketId' => $bucketId,
                'fileId' => $file->getId(),
                'file' => new \CurlFile('data://'.$file->getMimeType().';base64,'.base64_encode($file->getData()), $file->getMimeType(), $file->getFileName()),
                'permissions' => $file->getPermissions(),
            ]
        );

        if ($file->getEnd() == ($file->getSize() - 1)) {
            $file->setStatus(Resource::STATUS_SUCCESS);

            // Signatures for encrypted files are invalid, so we skip the check
            if (!$file->getBucket()->getEncryption() || $file->getSize() > (20 * 1024 * 1024)) {
                if (\is_array($response) && $response['signature'] !== $file->getSignature()) {
                    $file->setStatus(Resource::STATUS_WARNING, 'File signature mismatch, Possibly corrupted.');
                }
            }
        }

        $file->setData('');

        return $file;
    }

    /**
     * @throws AppwriteException
     */
    public function importAuthResource(Resource $resource): Resource
    {
        switch ($resource->getName()) {
            case Resource::TYPE_USER:
                /** @var User $resource */
                if (\in_array(User::TYPE_PASSWORD, $resource->getTypes())) {
                    $this->importPasswordUser($resource);
                } elseif (\in_array(User::TYPE_OAUTH, $resource->getTypes())) {
                    $resource->setStatus(
                        Resource::STATUS_WARNING,
                        'OAuth users cannot be imported.'
                    );

                    return $resource;
                } else {
                    $this->users->create(
                        $resource->getId(),
                        $resource->getEmail(),
                        in_array(User::TYPE_PHONE, $resource->getTypes()) ? $resource->getPhone() : null,
                        null,
                        $resource->getName()
                    );
                }

                if (!empty($resource->getUsername())) {
                    $this->users->updateName($resource->getId(), $resource->getUsername());
                }

                if (!empty($resource->getPhone())) {
                    $this->users->updatePhone($resource->getId(), $resource->getPhone());
                }

                if ($resource->getEmailVerified()) {
                    $this->users->updateEmailVerification($resource->getId(), true);
                }

                if ($resource->getPhoneVerified()) {
                    $this->users->updatePhoneVerification($resource->getId(), true);
                }

                if ($resource->getDisabled()) {
                    $this->users->updateStatus($resource->getId(), false);
                }

                if (!empty($resource->getPreferences())) {
                    $this->users->updatePrefs($resource->getId(), $resource->getPreferences());
                }

                if (!empty($resource->getLabels())) {
                    $this->users->updateLabels($resource->getId(), $resource->getLabels());
                }

                break;
            case Resource::TYPE_TEAM:
                /** @var Team $resource */
                $this->teams->create($resource->getId(), $resource->getTeamName());

                if (!empty($resource->getPreferences())) {
                    $this->teams->updatePrefs($resource->getId(), $resource->getPreferences());
                }
                break;
            case Resource::TYPE_MEMBERSHIP:
                /** @var Membership $resource */
                $user = $resource->getUser();

                $this->teams->createMembership(
                    $resource->getTeam()->getId(),
                    $resource->getRoles(),
                    userId: $user->getId(),
                );
                break;
        }

        $resource->setStatus(Resource::STATUS_SUCCESS);

        return $resource;
    }

    /**
     * @param User $user
     * @return array<string, mixed>|null
     * @throws AppwriteException
     * @throws \Exception
     */
    public function importPasswordUser(User $user): ?array
    {
        $hash = $user->getPasswordHash();
        $result = null;

        if (!$hash) {
            throw new \Exception('Password hash is missing');
        }

        switch ($hash->getAlgorithm()) {
            case Hash::ALGORITHM_SCRYPT_MODIFIED:
                $result = $this->users->createScryptModifiedUser(
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
                $result = $this->users->createBcryptUser(
                    $user->getId(),
                    $user->getEmail(),
                    $hash->getHash(),
                    empty($user->getUsername()) ? null : $user->getUsername()
                );
                break;
            case Hash::ALGORITHM_ARGON2:
                $result = $this->users->createArgon2User(
                    $user->getId(),
                    $user->getEmail(),
                    $hash->getHash(),
                    empty($user->getUsername()) ? null : $user->getUsername()
                );
                break;
            case Hash::ALGORITHM_SHA256:
                $result = $this->users->createShaUser(
                    $user->getId(),
                    $user->getEmail(),
                    $hash->getHash(),
                    'sha256',
                    empty($user->getUsername()) ? null : $user->getUsername()
                );
                break;
            case Hash::ALGORITHM_PHPASS:
                $result = $this->users->createPHPassUser(
                    $user->getId(),
                    $user->getEmail(),
                    $hash->getHash(),
                    empty($user->getUsername()) ? null : $user->getUsername()
                );
                break;
            case Hash::ALGORITHM_SCRYPT:
                $result = $this->users->createScryptUser(
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
                $result = $this->users->create(
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

    /**
     * @throws AppwriteException
     */
    public function importFunctionResource(Resource $resource): Resource
    {
        switch ($resource->getName()) {
            case Resource::TYPE_FUNCTION:
                /** @var Func $resource */
                $this->functions->create(
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
                $this->functions->createVariable(
                    $resource->getFunc()->getId(),
                    $resource->getKey(),
                    $resource->getValue()
                );
                break;
            case Resource::TYPE_DEPLOYMENT:
                /** @var Deployment $resource */
                return $this->importDeployment($resource);
        }

        $resource->setStatus(Resource::STATUS_SUCCESS);

        return $resource;
    }

    /**
     * @throws AppwriteException
     * @throws \Exception
     */
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

        if (!\is_array($response) || !isset($response['$id'])) {
            throw new \Exception('Deployment creation failed');
        }

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
