<?php

namespace Utopia\Migration\Destinations;

use Appwrite\AppwriteException;
use Appwrite\Client;
use Appwrite\Enums\Compression;
use Appwrite\Enums\PasswordHash;
use Appwrite\Enums\Runtime;
use Appwrite\InputFile;
use Appwrite\Services\Functions;
use Appwrite\Services\Storage;
use Appwrite\Services\Teams;
use Appwrite\Services\Users;
use Override;
use Utopia\Database\Database as UtopiaDatabase;
use Utopia\Database\Document as UtopiaDocument;
use Utopia\Database\Exception as DatabaseException;
use Utopia\Database\Exception\Authorization as AuthorizationException;
use Utopia\Database\Exception\Duplicate as DuplicateException;
use Utopia\Database\Exception\Limit as LimitException;
use Utopia\Database\Exception\Structure as StructureException;
use Utopia\Database\Helpers\ID;
use Utopia\Database\Helpers\Permission;
use Utopia\Database\Query;
use Utopia\Database\Validator\Index as IndexValidator;
use Utopia\Database\Validator\Structure;
use Utopia\Migration\Destination;
use Utopia\Migration\Exception;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Auth\Hash;
use Utopia\Migration\Resources\Auth\Membership;
use Utopia\Migration\Resources\Auth\Team;
use Utopia\Migration\Resources\Auth\User;
use Utopia\Migration\Resources\Database\Attribute;
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


    private Functions $functions;
    private Storage $storage;
    private Teams $teams;
    private Users $users;

    /**
     * @var array<UtopiaDocument>
     */
    private array $documentBuffer = [];

    /**
     * @param string $project
     * @param string $endpoint
     * @param string $key
     * @param UtopiaDatabase $database
     * @param array<array<string, mixed>> $collectionStructure
     */
    public function __construct(
        string $project,
        string $endpoint,
        string $key,
        protected UtopiaDatabase $database,
        protected array $collectionStructure
    ) {
        $this->project = $project;
        $this->endpoint = $endpoint;
        $this->key = $key;

        $this->client = (new Client())
            ->setEndpoint($endpoint)
            ->setProject($project)
            ->setKey($key)
            ->addHeader('x-appwrite-preserve-dates', 'true');

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
     * @return array<string, int>
     * @throws AppwriteException
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
                $this->functions->create('', '', Runtime::NODE180());
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
        if (empty($resources)) {
            return;
        }

        $total = \count($resources);

        foreach ($resources as $index => $resource) {
            $resource->setStatus(Resource::STATUS_PROCESSING);

            $isLast = $index === $total - 1;

            try {
                $responseResource = match ($resource->getGroup()) {
                    Transfer::GROUP_DATABASES => $this->importDatabaseResource($resource, $isLast),
                    Transfer::GROUP_STORAGE => $this->importFileResource($resource),
                    Transfer::GROUP_AUTH => $this->importAuthResource($resource),
                    Transfer::GROUP_FUNCTIONS => $this->importFunctionResource($resource),
                    default => throw new \Exception('Invalid resource group'),
                };
            } catch (\Throwable $e) {
                if ($e->getCode() === 409) {
                    $resource->setStatus(Resource::STATUS_SKIPPED, $e->getMessage());
                } else {
                    $resource->setStatus(Resource::STATUS_ERROR, $e->getMessage());

                    $this->addError(new Exception(
                        resourceName: $resource->getName(),
                        resourceGroup: $resource->getGroup(),
                        resourceId: $resource->getId(),
                        message: $e->getMessage(),
                        code: $e->getCode(),
                        previous: $e
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
     * @throws \Throwable
     */
    public function importDatabaseResource(Resource $resource, bool $isLast): Resource
    {
        switch ($resource->getName()) {
            case Resource::TYPE_DATABASE:
                /** @var Database $resource */
                $success = $this->createDatabase($resource);
                break;
            case Resource::TYPE_COLLECTION:
                /** @var Collection $resource */
                $success = $this->createCollection($resource);
                break;
            case Resource::TYPE_ATTRIBUTE:
                /** @var Attribute $resource */
                $success = $this->createAttribute($resource);
                break;
            case Resource::TYPE_INDEX:
                /** @var Index $resource */
                $success = $this->createIndex($resource);
                break;
            case Resource::TYPE_DOCUMENT:
                /** @var Document $resource */
                $success = $this->createDocument($resource, $isLast);
                break;
            default:
                $success = false;
                break;
        }

        if ($success) {
            $resource->setStatus(Resource::STATUS_SUCCESS);
        }

        return $resource;
    }

    /**
     * @throws AuthorizationException
     * @throws StructureException
     * @throws DatabaseException
     */
    protected function createDatabase(Database $resource): bool
    {
        $resourceId = $resource->getId() == 'unique()'
            ? ID::unique()
            : $resource->getId();

        $resource->setId($resourceId);

        $database = $this->database->createDocument('databases', new UtopiaDocument([
            '$id' => $resource->getId(),
            'name' => $resource->getDatabaseName(),
            'enabled' => true,
            'search' => implode(' ', [$resource->getId(), $resource->getDatabaseName()]),
        ]));

        $resource->setInternalId($database->getInternalId());

        $attributes = \array_map(
            fn ($attr) => new UtopiaDocument($attr),
            $this->collectionStructure['attributes']
        );
        $indexes = \array_map(
            fn ($index) => new UtopiaDocument($index),
            $this->collectionStructure['indexes']
        );

        $this->database->createCollection(
            'database_' . $database->getInternalId(),
            $attributes,
            $indexes
        );

        return true;
    }

    /**
     * @throws AuthorizationException
     * @throws DatabaseException
     * @throws StructureException
     * @throws Exception
     */
    protected function createCollection(Collection $resource): bool
    {
        $resourceId = $resource->getId() == 'unique()'
            ? ID::unique()
            : $resource->getId();

        $resource->setId($resourceId);

        $database = $this->database->getDocument(
            'databases',
            $resource->getDatabase()->getId()
        );

        if ($database->isEmpty()) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Database not found',
            );
        }

        $collection = $this->database->createDocument('database_' . $resource->getDatabase()->getInternalId(), new UtopiaDocument([
            '$id' => $resource->getId(),
            'databaseInternalId' => $resource->getDatabase()->getInternalId(),
            'databaseId' => $resource->getDatabase()->getId(),
            '$permissions' => Permission::aggregate($resource->getPermissions()),
            'documentSecurity' => $resource->getDocumentSecurity(),
            'enabled' => true,
            'name' => $resource->getCollectionName(),
            'search' => implode(' ', [$resource->getId(), $resource->getCollectionName()]),
        ]));

        $resource->setInternalId($collection->getInternalId());

        $this->database->createCollection(
            'database_' . $resource->getDatabase()->getInternalId() . '_collection_' . $resource->getInternalId(),
            permissions: $resource->getPermissions(),
            documentSecurity: $resource->getDocumentSecurity()
        );

        return true;
    }

    /**
     * @throws AppwriteException
     * @throws \Exception
     * @throws \Throwable
     */
    protected function createAttribute(Attribute $resource): bool
    {
        $database = $this->database->getDocument(
            'databases',
            $resource->getCollection()->getDatabase()->getId(),
        );
        if ($database->isEmpty()) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Database not found',
            );
        }

        $collection = $this->database->getDocument(
            'database_' . $database->getInternalId(),
            $resource->getCollection()->getId(),
        );
        if ($collection->isEmpty()) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Collection not found',
            );
        }

        if (!empty($resource->getFormat())) {
            if (!Structure::hasFormat($resource->getFormat(), $resource->getType())) {
                throw new Exception(
                    resourceName: $resource->getName(),
                    resourceGroup: $resource->getGroup(),
                    resourceId: $resource->getId(),
                    message: "Format {$resource->getFormat()} not available for attribute type {$resource->getType()}",
                );
            }
        }
        if ($resource->isRequired() && $resource->getDefault() !== null) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Cannot set default value for required attribute',
            );
        }
        if ($resource->isArray() && $resource->getDefault() !== null) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Cannot set default value for array attribute',
            );
        }
        if ($resource->getType() === UtopiaDatabase::VAR_RELATIONSHIP) {
            $resource->getOptions()['side'] = UtopiaDatabase::RELATION_SIDE_PARENT;
            $relatedCollection = $this->database->getDocument(
                'database_' . $database->getInternalId(),
                $resource->getOptions()['relatedCollection']
            );
            if ($relatedCollection->isEmpty()) {
                throw new Exception(
                    resourceName: $resource->getName(),
                    resourceGroup: $resource->getGroup(),
                    resourceId: $resource->getId(),
                    message: 'Related collection not found',
                );
            }
        }

        try {
            $attribute = new UtopiaDocument([
                '$id' => ID::custom($database->getInternalId() . '_' . $collection->getInternalId() . '_' . $resource->getKey()),
                'key' => $resource->getKey(),
                'databaseInternalId' => $database->getInternalId(),
                'databaseId' => $database->getId(),
                'collectionInternalId' => $collection->getInternalId(),
                'collectionId' => $collection->getId(),
                'type' => $resource->getType(),
                'status' => 'available',
                'size' => $resource->getSize(),
                'required' => $resource->isRequired(),
                'signed' => $resource->isSigned(),
                'default' => $resource->getDefault(),
                'array' => $resource->isArray(),
                'format' => $resource->getFormat(),
                'formatOptions' => $resource->getFormatOptions(),
                'filters' => $resource->getFilters(),
                'options' => $resource->getOptions(),
            ]);

            $this->database->checkAttribute($collection, $attribute);

            $attribute = $this->database->createDocument('attributes', $attribute);
        } catch (DuplicateException) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Attribute already exists',
            );
        } catch (LimitException) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Attribute limit exceeded',
            );
        } catch (\Throwable $e) {
            $this->database->purgeCachedDocument('database_' . $database->getInternalId(), $collection->getId());
            $this->database->purgeCachedCollection('database_' . $database->getInternalId() . '_collection_' . $collection->getInternalId());
            throw $e;
        }

        $this->database->purgeCachedDocument('database_' . $database->getInternalId(), $collection->getId());
        $this->database->purgeCachedCollection('database_' . $database->getInternalId() . '_collection_' . $collection->getInternalId());
        $options = $resource->getOptions();

        if ($resource->getType() === UtopiaDatabase::VAR_RELATIONSHIP && isset($relatedCollection) && $options['twoWay']) {
            $twoWayKey = $options['twoWayKey'];
            $options['relatedCollection'] = $collection->getId();
            $options['twoWayKey'] = $resource->getKey();
            $options['side'] = UtopiaDatabase::RELATION_SIDE_CHILD;

            try {
                $twoWayAttribute = new UtopiaDocument([
                    '$id' => ID::custom($database->getInternalId() . '_' . $relatedCollection->getInternalId() . '_' . $twoWayKey),
                    'key' => $twoWayKey,
                    'databaseInternalId' => $database->getInternalId(),
                    'databaseId' => $database->getId(),
                    'collectionInternalId' => $relatedCollection->getInternalId(),
                    'collectionId' => $relatedCollection->getId(),
                    'type' => $resource->getType(),
                    'status' => 'available',
                    'size' => $resource->getSize(),
                    'required' => $resource->isRequired(),
                    'signed' => $resource->isSigned(),
                    'default' => $resource->getDefault(),
                    'array' => $resource->isArray(),
                    'format' => $resource->getFormat(),
                    'formatOptions' => $resource->getFormatOptions(),
                    'filters' => $resource->getFilters(),
                    'options' => $options,
                ]);

                $this->database->createDocument('attributes', $twoWayAttribute);
            } catch (DuplicateException) {
                $this->database->deleteDocument('attributes', $attribute->getId());

                throw new Exception(
                    resourceName: $resource->getName(),
                    resourceGroup: $resource->getGroup(),
                    resourceId: $resource->getId(),
                    message: 'Attribute already exists',
                );
            } catch (LimitException) {
                $this->database->deleteDocument('attributes', $attribute->getId());

                throw new Exception(
                    resourceName: $resource->getName(),
                    resourceGroup: $resource->getGroup(),
                    resourceId: $resource->getId(),
                    message: 'Attribute limit exceeded',
                );
            } catch (\Throwable $e) {
                $this->database->purgeCachedDocument('database_' . $database->getInternalId(), $relatedCollection->getId());
                $this->database->purgeCachedCollection('database_' . $database->getInternalId() . '_collection_' . $relatedCollection->getInternalId());
                throw $e;
            }
        }

        try {
            switch ($resource->getType()) {
                case UtopiaDatabase::VAR_RELATIONSHIP:
                    if (
                        isset($relatedCollection)
                        && !$this->database->createRelationship(
                            collection: 'database_' . $database->getInternalId() . '_collection_' . $collection->getInternalId(),
                            relatedCollection: 'database_' . $database->getInternalId() . '_collection_' . $relatedCollection->getInternalId(),
                            type: $options['relationType'],
                            twoWay: $options['twoWay'],
                            id: $resource->getKey(),
                            twoWayKey: $options['twoWayKey'],
                            onDelete: $options['onDelete'],
                        )
                    ) {
                        throw new Exception(
                            resourceName: $resource->getName(),
                            resourceGroup: $resource->getGroup(),
                            resourceId: $resource->getId(),
                            message: 'Failed to create relationship',
                        );
                    }
                    break;
                default:
                    if (!$this->database->createAttribute(
                        'database_' . $database->getInternalId() . '_collection_' . $collection->getInternalId(),
                        $resource->getKey(),
                        $resource->getType(),
                        $resource->getSize(),
                        $resource->isRequired(),
                        $resource->getDefault(),
                        $resource->isSigned(),
                        $resource->isArray(),
                        $resource->getFormat(),
                        $resource->getFormatOptions(),
                        $resource->getFilters(),
                    )) {
                        throw new \Exception('Failed to create Attribute');
                    }
            }
        } catch (\Throwable) {
            $this->database->deleteDocument('attributes', $attribute->getId());

            if (isset($relatedAttribute)) {
                $this->database->deleteDocument('attributes', $relatedAttribute->getId());
            }
        }

        if ($resource->getType() === UtopiaDatabase::VAR_RELATIONSHIP && isset($relatedCollection) && $options['twoWay']) {
            $this->database->purgeCachedDocument('database_' . $database->getInternalId(), $relatedCollection->getId());
        }

        $this->database->purgeCachedDocument('database_' . $database->getInternalId(), $collection->getId());
        $this->database->purgeCachedCollection('database_' . $database->getInternalId() . '_collection_' . $collection->getInternalId());

        return true;
    }

    /**
     * @throws Exception
     * @throws \Throwable
     */
    protected function createIndex(Index $resource): bool
    {
        $database = $this->database->getDocument(
            'databases',
            $resource->getCollection()->getDatabase()->getId(),
        );
        if ($database->isEmpty()) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Database not found',
            );
        }

        $collection = $this->database->getDocument(
            'database_' . $database->getInternalId(),
            $resource->getCollection()->getId(),
        );
        if ($collection->isEmpty()) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Collection not found',
            );
        }

        $count = $this->database->count('indexes', [
            Query::equal('collectionInternalId', [$collection->getInternalId()]),
            Query::equal('databaseInternalId', [$database->getInternalId()])
        ], $this->database->getLimitForIndexes());

        if ($count >= $this->database->getLimitForIndexes()) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Index limit reached for collection',
            );
        }

        /**
         * @var array<UtopiaDocument> $collectionAttributes
         */
        $collectionAttributes = $collection->getAttribute('attributes');

        $oldAttributes = \array_map(
            fn ($attr) => $attr->getArrayCopy(),
            $collectionAttributes
        );

        $oldAttributes[] = [
            'key' => '$id',
            'type' => UtopiaDatabase::VAR_STRING,
            'status' => 'available',
            'required' => true,
            'array' => false,
            'default' => null,
            'size' => UtopiaDatabase::LENGTH_KEY
        ];
        $oldAttributes[] = [
            'key' => '$createdAt',
            'type' => UtopiaDatabase::VAR_DATETIME,
            'status' => 'available',
            'signed' => false,
            'required' => false,
            'array' => false,
            'default' => null,
            'size' => 0
        ];
        $oldAttributes[] = [
            'key' => '$updatedAt',
            'type' => UtopiaDatabase::VAR_DATETIME,
            'status' => 'available',
            'signed' => false,
            'required' => false,
            'array' => false,
            'default' => null,
            'size' => 0
        ];

        // Lengths hidden by default
        $lengths = [];

        foreach ($resource->getAttributes() as $i => $attribute) {
            // find attribute metadata in collection document
            $attributeIndex = \array_search(
                $attribute,
                \array_column($oldAttributes, 'key')
            );

            if ($attributeIndex === false) {
                throw new Exception(
                    resourceName: $resource->getName(),
                    resourceGroup: $resource->getGroup(),
                    resourceId: $resource->getId(),
                    message: 'Attribute not found in collection: ' . $attribute,
                );
            }

            $attributeStatus = $oldAttributes[$attributeIndex]['status'];
            $attributeType = $oldAttributes[$attributeIndex]['type'];
            $attributeSize = $oldAttributes[$attributeIndex]['size'];
            $attributeArray = $oldAttributes[$attributeIndex]['array'] ?? false;

            if ($attributeType === UtopiaDatabase::VAR_RELATIONSHIP) {
                throw new Exception(
                    resourceName: $resource->getName(),
                    resourceGroup: $resource->getGroup(),
                    resourceId: $resource->getId(),
                    message: 'Relationship attributes are not supported in indexes',
                );
            }

            // Ensure attribute is available
            if ($attributeStatus !== 'available') {
                throw new Exception(
                    resourceName: $resource->getName(),
                    resourceGroup: $resource->getGroup(),
                    resourceId: $resource->getId(),
                    message: 'Attribute not available: ' . $attribute,
                );
            }

            $lengths[$i] = null;

            if ($attributeType === UtopiaDatabase::VAR_STRING) {
                $lengths[$i] = $attributeSize; // set attribute size as index length only for strings
            }

            if ($attributeArray === true) {
                $lengths[$i] = UtopiaDatabase::ARRAY_INDEX_LENGTH;
                $orders[$i] = null;
            }
        }

        $index = new UtopiaDocument([
            '$id' => ID::custom($database->getInternalId() . '_' . $collection->getInternalId() . '_' . $resource->getKey()),
            'key' => $resource->getKey(),
            'status' => 'available', // processing, available, failed, deleting, stuck
            'databaseInternalId' => $database->getInternalId(),
            'databaseId' => $database->getId(),
            'collectionInternalId' => $collection->getInternalId(),
            'collectionId' => $collection->getId(),
            'type' => $resource->getType(),
            'attributes' => $resource->getAttributes(),
            'lengths' => $lengths,
            'orders' => $resource->getOrders(),
        ]);

        $validator = new IndexValidator(
            $collectionAttributes,
            $this->database->getAdapter()->getMaxIndexLength()
        );

        if (!$validator->isValid($index)) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Invalid index: ' . $validator->getDescription(),
            );
        }

        $index = $this->database->createDocument('indexes', $index);

        try {
            $result = $this->database->createIndex(
                'database_' . $database->getInternalId() . '_collection_' . $collection->getInternalId(),
                $resource->getKey(),
                $resource->getType(),
                $resource->getAttributes(),
                $lengths,
                $resource->getOrders()
            );

            if (!$result) {
                throw new Exception(
                    resourceName: $resource->getName(),
                    resourceGroup: $resource->getGroup(),
                    resourceId: $resource->getId(),
                    message: 'Failed to create index',
                );
            }
        } catch (\Throwable $th) {
            $this->database->deleteDocument('indexes', $index->getId());

            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Failed to create index',
            );
        }

        $this->database->purgeCachedDocument(
            'database_' . $database->getInternalId(),
            $collection->getId()
        );

        return true;
    }

    /**
     * @throws AuthorizationException
     * @throws DatabaseException
     * @throws StructureException
     */
    protected function createDocument(Document $resource, bool $isLast): bool
    {
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
            return false;
        }

        if (!$isLast) {
            $this->documentBuffer[] = new UtopiaDocument(\array_merge([
                '$id' => $resource->getId(),
                '$permissions' => $resource->getPermissions(),
            ], $resource->getData()));
            return true;
        }

        try {
            $this->database->createDocuments(
                $resource->getCollection()->getId(),
                $this->documentBuffer
            );
        } finally {
            $this->documentBuffer = [];
        }

        return true;
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

                $compression = match ($resource->getCompression()) {
                    'none' => Compression::NONE(),
                    'gzip' => Compression::GZIP(),
                    'zstd' => Compression::ZSTD(),
                    default => throw new \Exception('Invalid Compression: ' . $resource->getCompression()),
                };

                $response = $this->storage->createBucket(
                    $resource->getId(),
                    $resource->getBucketName(),
                    $resource->getPermissions(),
                    $resource->getFileSecurity(),
                    $resource->getEnabled(),
                    $resource->getMaxFileSize(),
                    $resource->getAllowedFileExtensions(),
                    $compression,
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
                    'file' => new \CurlFile('data://' . $file->getMimeType() . ';base64,' . base64_encode($file->getData()), $file->getMimeType(), $file->getFileName()),
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
                'content-range' => 'bytes ' . ($file->getStart()) . '-' . ($file->getEnd() == ($file->getSize() - 1) ? $file->getSize() : $file->getEnd()) . '/' . $file->getSize(),
                'x-appwrite-project' => $this->project,
                'x-appwrite-key' => $this->key,
            ],
            [
                'bucketId' => $bucketId,
                'fileId' => $file->getId(),
                'file' => new \CurlFile('data://' . $file->getMimeType() . ';base64,' . base64_encode($file->getData()), $file->getMimeType(), $file->getFileName()),
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
                if (!empty($resource->getPasswordHash())) {
                    $this->importPasswordUser($resource);
                } else {
                    $this->users->create(
                        $resource->getId(),
                        $resource->getEmail(),
                        $resource->getPhone(),
                        null,
                        $resource->getUsername()
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
                    PasswordHash::SHA256(),
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

                $runtime = match ($resource->getRuntime()) {
                    'node-14.5' => Runtime::NODE145(),
                    'node-16.0' => Runtime::NODE160(),
                    'node-18.0' => Runtime::NODE180(),
                    'node-19.0' => Runtime::NODE190(),
                    'node-20.0' => Runtime::NODE200(),
                    'node-21.0' => Runtime::NODE210(),
                    'php-8.0' => Runtime::PHP80(),
                    'php-8.1' => Runtime::PHP81(),
                    'php-8.2' => Runtime::PHP82(),
                    'php-8.3' => Runtime::PHP83(),
                    'ruby-3.0' => Runtime::RUBY30(),
                    'ruby-3.1' => Runtime::RUBY31(),
                    'ruby-3.2' => Runtime::RUBY32(),
                    'ruby-3.3' => Runtime::RUBY33(),
                    'python-3.8' => Runtime::PYTHON38(),
                    'python-3.9' => Runtime::PYTHON39(),
                    'python-3.10' => Runtime::PYTHON310(),
                    'python-3.11' => Runtime::PYTHON311(),
                    'python-3.12' => Runtime::PYTHON312(),
                    'python-ml-3.11' => Runtime::PYTHONML311(),
                    'dart-3.0' => Runtime::DART30(),
                    'dart-3.1' => Runtime::DART31(),
                    'dart-3.3' => Runtime::DART33(),
                    'dart-2.15' => Runtime::DART215(),
                    'dart-2.16' => Runtime::DART216(),
                    'dart-2.17' => Runtime::DART217(),
                    'dart-2.18' => Runtime::DART218(),
                    'deno-1.21' => Runtime::DENO121(),
                    'deno-1.24' => Runtime::DENO124(),
                    'deno-1.35' => Runtime::DENO135(),
                    'deno-1.40' => Runtime::DENO140(),
                    'dotnet-3.1' => Runtime::DOTNET31(),
                    'dotnet-6.0' => Runtime::DOTNET60(),
                    'dotnet-7.0' => Runtime::DOTNET70(),
                    'java-8.0' => Runtime::JAVA80(),
                    'java-11.0' => Runtime::JAVA110(),
                    'java-17.0' => Runtime::JAVA170(),
                    'java-18.0' => Runtime::JAVA180(),
                    'java-21.0' => Runtime::JAVA210(),
                    'swift-5.5' => Runtime::SWIFT55(),
                    'swift-5.8' => Runtime::SWIFT58(),
                    'swift-5.9' => Runtime::SWIFT59(),
                    'kotlin-1.6' => Runtime::KOTLIN16(),
                    'kotlin-1.8' => Runtime::KOTLIN18(),
                    'kotlin-1.9' => Runtime::KOTLIN19(),
                    'cpp-17' => Runtime::CPP17(),
                    'cpp-20' => Runtime::CPP20(),
                    'bun-1.0' => Runtime::BUN10(),
                    default => throw new \Exception('Invalid Runtime: ' . $resource->getRuntime()),
                };

                $this->functions->create(
                    $resource->getId(),
                    $resource->getFunctionName(),
                    $runtime,
                    $resource->getExecute(),
                    $resource->getEvents(),
                    $resource->getSchedule(),
                    $resource->getTimeout(),
                    $resource->getEnabled(),
                    entrypoint: $resource->getEntrypoint(),
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
                    'code' => new \CurlFile('data://application/gzip;base64,' . base64_encode($deployment->getData()), 'application/gzip', 'deployment.tar.gz'),
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
                'content-range' => 'bytes ' . ($deployment->getStart()) . '-' . ($deployment->getEnd() == ($deployment->getSize() - 1) ? $deployment->getSize() : $deployment->getEnd()) . '/' . $deployment->getSize(),
                'x-appwrite-id' => $deployment->getId(),
            ],
            [
                'functionId' => $functionId,
                'code' => new \CurlFile('data://application/gzip;base64,' . base64_encode($deployment->getData()), 'application/gzip', 'deployment.tar.gz'),
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
