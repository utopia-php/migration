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
use Dom\Document;
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
use Utopia\Database\Validator\UID;
use Utopia\Migration\Destination;
use Utopia\Migration\Exception;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Auth\Hash;
use Utopia\Migration\Resources\Auth\Membership;
use Utopia\Migration\Resources\Auth\Team;
use Utopia\Migration\Resources\Auth\User;
use Utopia\Migration\Resources\Database\Attribute;
use Utopia\Migration\Resources\Database\Column;
use Utopia\Migration\Resources\Database\Database;
use Utopia\Migration\Resources\Database\Index;
use Utopia\Migration\Resources\Database\Row;
use Utopia\Migration\Resources\Database\Table;
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
     * @var callable(UtopiaDocument $database): UtopiaDatabase
    */
    protected $getDatabasesDB;

    /**
     * @var callable(string $databaseType):string
    */
    protected $getDatabaseDSN;

    /**
     * @var array<UtopiaDocument>
     */
    private array $rowBuffer = [];

    /**
     * @param string $project
     * @param string $endpoint
     * @param string $key
     * @param UtopiaDatabase $dbForProject
     * @param callable(UtopiaDocument $database):UtopiaDatabase $getDatabasesDB
     * @param callable(string $databaseType):string $getDatabasesDSN
     * @param array<array<string, mixed>> $collectionStructure
     */
    public function __construct(
        string $project,
        string $endpoint,
        string $key,
        protected UtopiaDatabase $dbForProject,
        callable $getDatabasesDB,
        callable $getDatabasesDSN,
        protected array $collectionStructure
    ) {
        $this->project = $project;
        $this->endpoint = $endpoint;
        $this->key = $key;

        $this->client = (new Client())
            ->setEndpoint($endpoint)
            ->setProject($project)
            ->setKey($key);

        $this->functions = new Functions($this->client);
        $this->storage = new Storage($this->client);
        $this->teams = new Teams($this->client);
        $this->users = new Users($this->client);

        $this->getDatabasesDB = $getDatabasesDB;
        $this->getDatabaseDSN = $getDatabasesDSN;
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
            Resource::TYPE_DATABASE_DOCUMENTSDB,
            Resource::TYPE_DATABASE_VECTORDB,
            Resource::TYPE_TABLE,
            Resource::TYPE_COLUMN,
            Resource::TYPE_INDEX,
            Resource::TYPE_ROW,

            // legacy
            Resource::TYPE_DOCUMENT,
            Resource::TYPE_ATTRIBUTE,
            Resource::TYPE_COLLECTION,

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
     * @param callable(array<Resource>): void $callback
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
                $this->dbForProject->setPreserveDates(true);

                $responseResource = match ($resource->getGroup()) {
                    Transfer::GROUP_DATABASES => $this->importDatabaseResource($resource, $isLast),
                    Transfer::GROUP_STORAGE => $this->importFileResource($resource),
                    Transfer::GROUP_AUTH => $this->importAuthResource($resource),
                    Transfer::GROUP_FUNCTIONS => $this->importFunctionResource($resource),
                    default => throw new \Exception('Invalid resource group'),
                };
            } catch (\Throwable $e) {
                $resource->setStatus(Resource::STATUS_ERROR, $e->getMessage());

                $this->addError(new Exception(
                    resourceName: $resource->getName(),
                    resourceGroup: $resource->getGroup(),
                    resourceId: $resource->getId(),
                    message: $e->getMessage(),
                    code: $e->getCode(),
                    previous: $e
                ));

                $responseResource = $resource;
            } finally {
                $this->dbForProject->setPreserveDates(false);
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
            case Resource::TYPE_DATABASE_DOCUMENTSDB:
            case Resource::TYPE_DATABASE_VECTORDB:
                /** @var Database $resource */
                $success = $this->createDatabase($resource);
                break;
            case Resource::TYPE_TABLE:
            case Resource::TYPE_COLLECTION:
                /** @var Table $resource */
                $success = $this->createEntity($resource);
                break;
            case Resource::TYPE_COLUMN:
            case Resource::TYPE_ATTRIBUTE:
                /** @var Column $resource */
                $success = $this->createField($resource);
                break;
            case Resource::TYPE_INDEX:
                /** @var Index $resource */
                $success = $this->createIndex($resource);
                break;
            case Resource::TYPE_ROW:
            case Resource::TYPE_DOCUMENT:
                /** @var Row $resource */
                $success = $this->createRecord($resource, $isLast);
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
     * @throws DatabaseException|Exception
     */
    protected function createDatabase(Database $resource): bool
    {
        if ($resource->getId() == 'unique()') {
            $resource->setId(ID::unique());
        }

        $validator = new UID();

        if (!$validator->isValid($resource->getId())) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: $validator->getDescription(),
            );
        }

        $database = $this->dbForProject->createDocument('databases', new UtopiaDocument([
            '$id' => $resource->getId(),
            'name' => $resource->getDatabaseName(),
            'enabled' => $resource->getEnabled(),
            'search' => implode(' ', [$resource->getId(), $resource->getDatabaseName()]),
            '$createdAt' => $resource->getCreatedAt(),
            '$updatedAt' => $resource->getUpdatedAt(),
            'originalId' => empty($resource->getOriginalId()) ? null : $resource->getOriginalId(),
            'type' => empty($resource->getType()) ? 'legacy' : $resource->getType(),
            // source and destination can be in different location
            'database' => ($this->getDatabaseDSN)($resource->getType())
        ]));

        $resource->setSequence($database->getSequence());

        $columns = \array_map(
            fn ($attr) => new UtopiaDocument($attr),
            $this->collectionStructure['attributes']
        );

        $indexes = \array_map(
            fn ($index) => new UtopiaDocument($index),
            $this->collectionStructure['indexes']
        );

        $this->dbForProject->createCollection(
            'database_' . $database->getSequence(),
            $columns,
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
    protected function createEntity(Table $resource): bool
    {
        if ($resource->getId() == 'unique()') {
            $resource->setId(ID::unique());
        }

        $validator = new UID();

        if (!$validator->isValid($resource->getId())) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: $validator->getDescription(),
            );
        }

        $database = $this->dbForProject->getDocument(
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

        $dbForDatabases = ($this->getDatabasesDB)($database);

        // passing null in creates only creates the metadata collection
        if (!$dbForDatabases->exists(null, UtopiaDatabase::METADATA)) {
            $dbForDatabases->create();
        }

        $table = $this->dbForProject->createDocument('database_' . $database->getSequence(), new UtopiaDocument([
            '$id' => $resource->getId(),
            'databaseInternalId' => $database->getSequence(),
            'databaseId' => $resource->getDatabase()->getId(),
            '$permissions' => Permission::aggregate($resource->getPermissions()),
            'documentSecurity' => $resource->getRowSecurity(),
            'enabled' => $resource->getEnabled(),
            'name' => $resource->getTableName(),
            'search' => implode(' ', [$resource->getId(), $resource->getTableName()]),
            '$createdAt' => $resource->getCreatedAt(),
            '$updatedAt' => $resource->getUpdatedAt(),
        ]));

        $resource->setSequence($table->getSequence());

        $dbForDatabases->createCollection(
            'database_' . $database->getSequence() . '_collection_' . $resource->getSequence(),
            permissions: $resource->getPermissions(),
            documentSecurity: $resource->getRowSecurity()
        );

        return true;
    }

    /**
     * @throws AppwriteException
     * @throws \Exception
     * @throws \Throwable
     */
    protected function createField(Column|Attribute $resource): bool
    {
        if ($resource->getTable()->getDatabase()->getType() === Resource::TYPE_DATABASE_DOCUMENTSDB) {
            $resource->setStatus(Resource::STATUS_SKIPPED, 'Columns not supported for DocumentsDB');
            return false;
        }

        $type = match ($resource->getType()) {
            Column::TYPE_DATETIME, Attribute::TYPE_DATETIME => UtopiaDatabase::VAR_DATETIME,
            Column::TYPE_BOOLEAN, Attribute::TYPE_BOOLEAN => UtopiaDatabase::VAR_BOOLEAN,
            Column::TYPE_INTEGER, Attribute::TYPE_INTEGER => UtopiaDatabase::VAR_INTEGER,
            Column::TYPE_FLOAT, Attribute::TYPE_FLOAT => UtopiaDatabase::VAR_FLOAT,
            Column::TYPE_RELATIONSHIP, Attribute::TYPE_RELATIONSHIP => UtopiaDatabase::VAR_RELATIONSHIP,
            Column::TYPE_STRING, Attribute::TYPE_STRING,
            Column::TYPE_IP, Attribute::TYPE_IP,
            Column::TYPE_EMAIL, Attribute::TYPE_EMAIL,
            Column::TYPE_URL, Attribute::TYPE_URL,
            Column::TYPE_ENUM, Attribute::TYPE_ENUM => UtopiaDatabase::VAR_STRING,
            Column::TYPE_POINT, Attribute::TYPE_POINT => UtopiaDatabase::VAR_POINT,
            Column::TYPE_LINE, Attribute::TYPE_LINE => UtopiaDatabase::VAR_LINESTRING,
            Column::TYPE_POLYGON, Attribute::TYPE_POLYGON => UtopiaDatabase::VAR_POLYGON,
            Column::TYPE_OBJECT, Attribute::TYPE_OBJECT => UtopiaDatabase::VAR_OBJECT,
            Column::TYPE_VECTOR, Attribute::TYPE_VECTOR => UtopiaDatabase::VAR_VECTOR,
            default => throw new \Exception('Invalid resource type '.$resource->getType()),
        };

        $database = $this->dbForProject->getDocument(
            'databases',
            $resource->getTable()->getDatabase()->getId(),
        );

        if ($database->isEmpty()) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Database not found',
            );
        }

        $table = $this->dbForProject->getDocument(
            'database_' . $database->getSequence(),
            $resource->getTable()->getId(),
        );

        if ($table->isEmpty()) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Table not found',
            );
        }

        if (!empty($resource->getFormat())) {
            if (!Structure::hasFormat($resource->getFormat(), $type)) {
                throw new Exception(
                    resourceName: $resource->getName(),
                    resourceGroup: $resource->getGroup(),
                    resourceId: $resource->getId(),
                    message: "Format {$resource->getFormat()} not available for column type {$type}",
                );
            }
        }

        if ($resource->isRequired() && $resource->getDefault() !== null) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Cannot set default value for required column',
            );
        }

        if ($resource->isArray() && $resource->getDefault() !== null) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Cannot set default value for array column',
            );
        }

        if ($type === UtopiaDatabase::VAR_RELATIONSHIP) {
            $resource->getOptions()['side'] = UtopiaDatabase::RELATION_SIDE_PARENT;
            $relatedTable = $this->dbForProject->getDocument(
                'database_' . $database->getSequence(),
                $resource->getOptions()['relatedCollection']
            );
            if ($relatedTable->isEmpty()) {
                throw new Exception(
                    resourceName: $resource->getName(),
                    resourceGroup: $resource->getGroup(),
                    resourceId: $resource->getId(),
                    message: 'Related table not found',
                );
            }
        }
        $dbForDatabases = ($this->getDatabasesDB)($database);
        try {
            $column = new UtopiaDocument([
                '$id' => ID::custom($database->getSequence() . '_' . $table->getSequence() . '_' . $resource->getKey()),
                'key' => $resource->getKey(),
                'databaseInternalId' => $database->getSequence(),
                'databaseId' => $database->getId(),
                'collectionInternalId' => $table->getSequence(),
                'collectionId' => $table->getId(),
                'type' => $type,
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
                '$createdAt' => $resource->getCreatedAt(),
                '$updatedAt' => $resource->getUpdatedAt(),
            ]);

            $this->dbForProject->checkAttribute($table, $column);

            $column = $this->dbForProject->createDocument('attributes', $column);
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
            $this->dbForProject->purgeCachedDocument('database_' . $database->getSequence(), $table->getId());
            $dbForDatabases->purgeCachedCollection('database_' . $database->getSequence() . '_collection_' . $table->getSequence());
            throw $e;
        }

        $this->dbForProject->purgeCachedDocument('database_' . $database->getSequence(), $table->getId());
        $dbForDatabases->purgeCachedCollection('database_' . $database->getSequence() . '_collection_' . $table->getSequence());
        $options = $resource->getOptions();

        $twoWayKey = null;

        if ($type === UtopiaDatabase::VAR_RELATIONSHIP && $options['twoWay']) {
            $twoWayKey = $options['twoWayKey'];
            $options['relatedCollection'] = $table->getId();
            $options['twoWayKey'] = $resource->getKey();
            $options['side'] = UtopiaDatabase::RELATION_SIDE_CHILD;

            try {
                $twoWayAttribute = new UtopiaDocument([
                    '$id' => ID::custom($database->getSequence() . '_' . $relatedTable->getSequence() . '_' . $twoWayKey),
                    'key' => $twoWayKey,
                    'databaseInternalId' => $database->getSequence(),
                    'databaseId' => $database->getId(),
                    'collectionInternalId' => $relatedTable->getSequence(),
                    'collectionId' => $relatedTable->getId(),
                    'type' => $type,
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
                    '$createdAt' => $resource->getCreatedAt(),
                    '$updatedAt' => $resource->getUpdatedAt(),
                ]);

                $this->dbForProject->createDocument('attributes', $twoWayAttribute);
            } catch (DuplicateException) {
                $this->dbForProject->deleteDocument('attributes', $column->getId());

                throw new Exception(
                    resourceName: $resource->getName(),
                    resourceGroup: $resource->getGroup(),
                    resourceId: $resource->getId(),
                    message: 'Attribute already exists',
                );
            } catch (LimitException) {
                $this->dbForProject->deleteDocument('attributes', $column->getId());

                throw new Exception(
                    resourceName: $resource->getName(),
                    resourceGroup: $resource->getGroup(),
                    resourceId: $resource->getId(),
                    message: 'Column limit exceeded',
                );
            } catch (\Throwable $e) {
                $this->dbForProject->purgeCachedDocument('database_' . $database->getSequence(), $relatedTable->getId());
                $dbForDatabases->purgeCachedCollection('database_' . $database->getSequence() . '_collection_' . $relatedTable->getSequence());
                throw $e;
            }
        }

        try {
            switch ($type) {
                case UtopiaDatabase::VAR_RELATIONSHIP:
                    if (!$dbForDatabases->createRelationship(
                        collection: 'database_' . $database->getSequence() . '_collection_' . $table->getSequence(),
                        relatedCollection: 'database_' . $database->getSequence() . '_collection_' . $relatedTable->getSequence(),
                        type: $options['relationType'],
                        twoWay: $options['twoWay'],
                        id: $resource->getKey(),
                        twoWayKey: $options['twoWay'] ? $twoWayKey : $options['twoWayKey'] ?? null,
                        onDelete: $options['onDelete'],
                    )) {
                        throw new Exception(
                            resourceName: $resource->getName(),
                            resourceGroup: $resource->getGroup(),
                            resourceId: $resource->getId(),
                            message: 'Failed to create relationship',
                        );
                    }
                    break;
                default:
                    if (!$dbForDatabases->createAttribute(
                        'database_' . $database->getSequence() . '_collection_' . $table->getSequence(),
                        $resource->getKey(),
                        $type,
                        $resource->getSize(),
                        $resource->isRequired(),
                        $resource->getDefault(),
                        $resource->isSigned(),
                        $resource->isArray(),
                        $resource->getFormat(),
                        $resource->getFormatOptions(),
                        $resource->getFilters(),
                    )) {
                        throw new \Exception('Failed to create Column');
                    }
            }
        } catch (\Throwable) {
            $this->dbForProject->deleteDocument('attributes', $column->getId());

            if (isset($twoWayAttribute)) {
                $this->dbForProject->deleteDocument('attributes', $twoWayAttribute->getId());
            }

            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Failed to create column',
            );
        }

        if ($type === UtopiaDatabase::VAR_RELATIONSHIP && $options['twoWay']) {
            $this->dbForProject->purgeCachedDocument('database_' . $database->getSequence(), $relatedTable->getId());
        }

        $this->dbForProject->purgeCachedDocument('database_' . $database->getSequence(), $table->getId());
        $dbForDatabases->purgeCachedCollection('database_' . $database->getSequence() . '_collection_' . $table->getSequence());

        return true;
    }

    /**
     * @throws Exception
     * @throws \Throwable
     */
    protected function createIndex(Index $resource): bool
    {
        $database = $this->dbForProject->getDocument(
            'databases',
            $resource->getTable()->getDatabase()->getId(),
        );
        if ($database->isEmpty()) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Database not found',
            );
        }

        $table = $this->dbForProject->getDocument(
            'database_' . $database->getSequence(),
            $resource->getTable()->getId(),
        );
        if ($table->isEmpty()) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Table not found',
            );
        }
        $dbForDatabases = ($this->getDatabasesDB)($database);

        $count = $this->dbForProject->count('indexes', [
            Query::equal('collectionInternalId', [$table->getSequence()]),
            Query::equal('databaseInternalId', [$database->getSequence()])
        ], $dbForDatabases->getLimitForIndexes());

        if ($count >= $dbForDatabases->getLimitForIndexes()) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Index limit reached for table',
            );
        }

        // Lengths hidden by default
        $lengths = [];

        if ($dbForDatabases->getAdapter()->getSupportForAttributes()) {
            $this->validateFieldsForIndexes($resource, $table, $lengths);
        }

        $index = new UtopiaDocument([
            '$id' => ID::custom($database->getSequence() . '_' . $table->getSequence() . '_' . $resource->getKey()),
            'key' => $resource->getKey(),
            'status' => 'available', // processing, available, failed, deleting, stuck
            'databaseInternalId' => $database->getSequence(),
            'databaseId' => $database->getId(),
            'collectionInternalId' => $table->getSequence(),
            'collectionId' => $table->getId(),
            'type' => $resource->getType(),
            'attributes' => $resource->getColumns(),
            'lengths' => $lengths,
            'orders' => $resource->getOrders(),
            '$createdAt' => $resource->getCreatedAt(),
            '$updatedAt' => $resource->getUpdatedAt(),
        ]);

        $maxIndexLength = $dbForDatabases->getAdapter()->getMaxIndexLength();
        $internalIndexesKeys = $dbForDatabases->getAdapter()->getInternalIndexesKeys();
        $supportForIndexArray = $dbForDatabases->getAdapter()->getSupportForIndexArray();
        $supportForSpatialAttributes = $dbForDatabases->getAdapter()->getSupportForSpatialAttributes();
        $supportForSpatialIndexNull = $dbForDatabases->getAdapter()->getSupportForSpatialIndexNull();
        $supportForSpatialIndexOrder = $dbForDatabases->getAdapter()->getSupportForSpatialIndexOrder();
        $supportForAttributes = $dbForDatabases->getAdapter()->getSupportForAttributes();
        $supportForMultipleFulltextIndexes = $dbForDatabases->getAdapter()->getSupportForMultipleFulltextIndexes();
        $supportForIdenticalIndexes = $dbForDatabases->getAdapter()->getSupportForIdenticalIndexes();
        $supportForVectorIndexes = $dbForDatabases->getAdapter()->getSupportForVectors();
        $supportForObjectIndexes = $dbForDatabases->getAdapter()->getSupportForObject();

        $validator = new IndexValidator(
            $table->getAttribute('attributes'),
            $table->getAttribute('indexes', []),
            $maxIndexLength,
            $internalIndexesKeys,
            $supportForIndexArray,
            $supportForSpatialIndexNull,
            $supportForSpatialIndexOrder,
            $supportForVectorIndexes,
            $supportForAttributes,
            $supportForMultipleFulltextIndexes,
            $supportForIdenticalIndexes,
            $supportForObjectIndexes,
        );


        if (!$validator->isValid($index)) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Invalid index: ' . $validator->getDescription(),
            );
        }

        $index = $this->dbForProject->createDocument('indexes', $index);

        try {
            $result = $dbForDatabases->createIndex(
                'database_' . $database->getSequence() . '_collection_' . $table->getSequence(),
                $resource->getKey(),
                $resource->getType(),
                $resource->getColumns(),
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
            $this->dbForProject->deleteDocument('indexes', $index->getId());

            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Failed to create index',
            );
        }

        $this->dbForProject->purgeCachedDocument(
            'database_' . $database->getSequence(),
            $table->getId()
        );

        return true;
    }

    /**
     * @throws AuthorizationException
     * @throws DatabaseException
     * @throws StructureException
     * @throws Exception
     */
    protected function createRecord(Row $resource, bool $isLast): bool
    {
        if ($resource->getId() == 'unique()') {
            $resource->setId(ID::unique());
        }

        $validator = new UID();

        if (!$validator->isValid($resource->getId())) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: $validator->getDescription(),
            );
        }

        // Check if document has already been created
        $exists = \array_key_exists(
            $resource->getId(),
            $this->cache->get($resource->getName())
        );

        if ($exists) {
            $resource->setStatus(
                Resource::STATUS_SKIPPED,
                'Row has already been created'
            );
            return false;
        }

        $this->rowBuffer[] = new UtopiaDocument(\array_merge([
            '$id' => $resource->getId(),
            '$permissions' => $resource->getPermissions(),
        ], $resource->getData()));

        if ($isLast) {
            try {
                $database = $this->dbForProject->getDocument(
                    'databases',
                    $resource->getTable()->getDatabase()->getId(),
                );

                $table = $this->dbForProject->getDocument(
                    'database_' . $database->getSequence(),
                    $resource->getTable()->getId(),
                );

                $databaseInternalId = $database->getSequence();
                $tableInternalId = $table->getSequence();
                $dbForDatabases = ($this->getDatabasesDB)($database);
                /**
                 * This is in case an attribute was deleted from Appwrite attributes collection but was not deleted from the table
                 * When creating an archive we select * which will include orphan attribute from the schema
                 */
                if ($dbForDatabases->getAdapter()->getSupportForAttributes()) {
                    foreach ($this->rowBuffer as $row) {
                        foreach ($row as $key => $value) {
                            if (\str_starts_with($key, '$')) {
                                continue;
                            }

                            /** @var \Utopia\Database\Document $attribute */
                            $found = false;
                            foreach ($table->getAttribute('attributes', []) as $attribute) {
                                if ($attribute->getAttribute('key') == $key) {
                                    $found = true;
                                    break;
                                }
                            }

                            if (! $found) {
                                $row->removeAttribute($key);
                            }
                        }
                    }
                }
                $dbForDatabases->skipRelationshipsExistCheck(fn () => $dbForDatabases->createDocuments(
                    'database_' . $databaseInternalId . '_collection_' . $tableInternalId,
                    $this->rowBuffer
                ));

            } finally {
                $this->rowBuffer = [];
            }
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
                    'file' => new \CURLFile('data://' . $file->getMimeType() . ';base64,' . base64_encode($file->getData()), $file->getMimeType(), $file->getFileName()),
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
                'file' => new \CURLFile('data://' . $file->getMimeType() . ';base64,' . base64_encode($file->getData()), $file->getMimeType(), $file->getFileName()),
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
                        null,
                        null,
                        null
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
                $result = $this->users->createSHAUser(
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
                    'node-22' => Runtime::NODE22(),
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
                    'python-ml-3.12' => Runtime::PYTHONML312(),
                    'dart-3.0' => Runtime::DART30(),
                    'dart-3.1' => Runtime::DART31(),
                    'dart-3.3' => Runtime::DART33(),
                    'dart-3.5' => Runtime::DART35(),
                    'dart-2.15' => Runtime::DART215(),
                    'dart-2.16' => Runtime::DART216(),
                    'dart-2.17' => Runtime::DART217(),
                    'dart-2.18' => Runtime::DART218(),
                    'dart-2.19' => Runtime::DART219(),
                    'deno-1.21' => Runtime::DENO121(),
                    'deno-1.24' => Runtime::DENO124(),
                    'deno-1.35' => Runtime::DENO135(),
                    'deno-1.40' => Runtime::DENO140(),
                    'deno-1.46' => Runtime::DENO146(),
                    'deno-2.0' => Runtime::DENO20(),
                    'dotnet-6.0' => Runtime::DOTNET60(),
                    'dotnet-7.0' => Runtime::DOTNET70(),
                    'dotnet-8.0' => Runtime::DOTNET80(),
                    'java-8.0' => Runtime::JAVA80(),
                    'java-11.0' => Runtime::JAVA110(),
                    'java-17.0' => Runtime::JAVA170(),
                    'java-18.0' => Runtime::JAVA180(),
                    'java-21.0' => Runtime::JAVA210(),
                    'java-22' => Runtime::JAVA22(),
                    'swift-5.5' => Runtime::SWIFT55(),
                    'swift-5.8' => Runtime::SWIFT58(),
                    'swift-5.9' => Runtime::SWIFT59(),
                    'swift-5.10' => Runtime::SWIFT510(),
                    'kotlin-1.6' => Runtime::KOTLIN16(),
                    'kotlin-1.8' => Runtime::KOTLIN18(),
                    'kotlin-1.9' => Runtime::KOTLIN19(),
                    'kotlin-2.0' => Runtime::KOTLIN20(),
                    'cpp-17' => Runtime::CPP17(),
                    'cpp-20' => Runtime::CPP20(),
                    'bun-1.0' => Runtime::BUN10(),
                    'bun-1.1' => Runtime::BUN11(),
                    'go-1.23' => Runtime::GO123(),
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
                    'code' => new \CURLFile('data://application/gzip;base64,' . base64_encode($deployment->getData()), 'application/gzip', 'deployment.tar.gz'),
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
                'code' => new \CURLFile('data://application/gzip;base64,' . base64_encode($deployment->getData()), 'application/gzip', 'deployment.tar.gz'),
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

    private function validateFieldsForIndexes(Index $resource, UtopiaDocument $table, array &$lengths)
    {
        /**
         * @var array<UtopiaDocument> $tableColumns
         */
        $tableColumns = $table->getAttribute('attributes', []);

        $oldColumns = \array_map(
            fn ($attr) => $attr->getArrayCopy(),
            $tableColumns
        );

        $oldColumns[] = [
            'key' => '$id',
            'type' => UtopiaDatabase::VAR_STRING,
            'status' => 'available',
            'required' => true,
            'array' => false,
            'default' => null,
            'size' => UtopiaDatabase::LENGTH_KEY
        ];

        $oldColumns[] = [
            'key' => '$createdAt',
            'type' => UtopiaDatabase::VAR_DATETIME,
            'status' => 'available',
            'signed' => false,
            'required' => false,
            'array' => false,
            'default' => null,
            'size' => 0
        ];

        $oldColumns[] = [
            'key' => '$updatedAt',
            'type' => UtopiaDatabase::VAR_DATETIME,
            'status' => 'available',
            'signed' => false,
            'required' => false,
            'array' => false,
            'default' => null,
            'size' => 0
        ];

        foreach ($resource->getColumns() as $i => $column) {
            // find attribute metadata in collection document
            $columnIndex = \array_search(
                $column,
                \array_column($oldColumns, 'key')
            );

            if ($columnIndex === false) {
                throw new Exception(
                    resourceName: $resource->getName(),
                    resourceGroup: $resource->getGroup(),
                    resourceId: $resource->getId(),
                    message: 'Column not found in table: ' . $column,
                );
            }

            $columnStatus = $oldColumns[$columnIndex]['status'];
            $columnType = $oldColumns[$columnIndex]['type'];
            $columnSize = $oldColumns[$columnIndex]['size'];
            $columnArray = $oldColumns[$columnIndex]['array'] ?? false;

            if ($columnType === UtopiaDatabase::VAR_RELATIONSHIP) {
                throw new Exception(
                    resourceName: $resource->getName(),
                    resourceGroup: $resource->getGroup(),
                    resourceId: $resource->getId(),
                    message: 'Relationship columns are not supported in indexes',
                );
            }

            // Ensure attribute is available
            if ($columnStatus !== 'available') {
                throw new Exception(
                    resourceName: $resource->getName(),
                    resourceGroup: $resource->getGroup(),
                    resourceId: $resource->getId(),
                    message: 'Column not available: ' . $column,
                );
            }

            $lengths[$i] = null;

            if ($columnArray === true) {
                $lengths[$i] = UtopiaDatabase::MAX_ARRAY_INDEX_LENGTH;
            }
        }
    }
}
