<?php

namespace Utopia\Migration\Destinations;

use Appwrite\AppwriteException;
use Appwrite\Client;
use Appwrite\Enums\Adapter;
use Appwrite\Enums\BuildRuntime;
use Appwrite\Enums\Compression;
use Appwrite\Enums\Framework;
use Appwrite\Enums\PasswordHash;
use Appwrite\Enums\Runtime;
use Appwrite\Enums\SmtpEncryption;
use Appwrite\InputFile;
use Appwrite\Services\Functions;
use Appwrite\Services\Messaging;
use Appwrite\Services\Sites;
use Appwrite\Services\Storage;
use Appwrite\Services\Teams;
use Appwrite\Services\Users;
use Override;
use Utopia\Database\Database as UtopiaDatabase;
use Utopia\Database\DateTime;
use Utopia\Database\Document as UtopiaDocument;
use Utopia\Database\Exception as DatabaseException;
use Utopia\Database\Exception\Authorization as AuthorizationException;
use Utopia\Database\Exception\Duplicate as DuplicateException;
use Utopia\Database\Exception\Limit as LimitException;
use Utopia\Database\Exception\Structure as StructureException;
use Utopia\Database\Helpers\ID;
use Utopia\Database\Helpers\Permission;
use Utopia\Database\Helpers\Role;
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
use Utopia\Migration\Resources\Messaging\Message;
use Utopia\Migration\Resources\Messaging\Provider;
use Utopia\Migration\Resources\Messaging\Subscriber;
use Utopia\Migration\Resources\Messaging\Topic;
use Utopia\Migration\Resources\Sites\Deployment as SiteDeployment;
use Utopia\Migration\Resources\Sites\EnvVar as SiteEnvVar;
use Utopia\Migration\Resources\Sites\Site;
use Utopia\Migration\Resources\Storage\Bucket;
use Utopia\Migration\Resources\Storage\File;
use Utopia\Migration\Transfer;

class Appwrite extends Destination
{
    /** Names of the platform-DB collections holding Appwrite schema metadata. */
    private const META_DATABASES = 'databases';
    private const META_ATTRIBUTES = 'attributes';
    private const META_INDEXES = 'indexes';

    /** Fields the SDK's per-type updateXAttribute endpoints don't expose; a change here forces DropAndRecreate. */
    private const ATTRIBUTE_NON_SDK_FIELDS = [
        'type',
        'array',
        'signed',
        'format',
        'formatOptions',
        'filters',
    ];

    /** Fields the SDK's updateRelationshipAttribute doesn't expose (only onDelete/newKey are SDK-reachable). */
    private const RELATIONSHIP_STRUCTURAL_FIELDS = [
        'relationType',
        'twoWay',
        'twoWayKey',
        'relatedCollection',
    ];

    protected Client $client;
    protected string $project;

    protected string $key;

    private Functions $functions;
    private Messaging $messaging;
    private Sites $sites;
    private Storage $storage;
    private Teams $teams;
    private Users $users;

    /**
     * @var callable(UtopiaDocument $database): UtopiaDatabase
    */
    protected $getDatabasesDB;

    /**
     * @var array<UtopiaDocument>
     */
    private array $rowBuffer = [];

    /**
     * Upsert-mode orphan tracking, keyed by (database, table). Orphans are
     * destination keys not in `attributeKeys` / `indexKeys`. Entries removed
     * after their cleanup runs so the end-of-run sweep only visits tables
     * that had no rows.
     *
     * @var array<string, array{
     *   database: UtopiaDocument,
     *   table: UtopiaDocument,
     *   dbForDatabases: UtopiaDatabase,
     *   attributeKeys: list<string>,
     *   indexKeys: list<string>,
     * }>
     */
    private array $orphansByTable = [];

    /**
     * Two-way pairs already reconciled this run; partner pass short-circuits.
     *
     * @var array<string, true>
     */
    private array $processedTwoWayPairs = [];

    /**
     * @param string $project
     * @param string $endpoint
     * @param string $key
     * @param UtopiaDatabase $dbForProject
     * @param callable(UtopiaDocument $database):UtopiaDatabase $getDatabasesDB
     * @param array<array<string, mixed>> $collectionStructure
     * @param OnDuplicate $onDuplicate Behavior when a row with an existing $id is encountered.
     */
    public function __construct(
        string $project,
        string $endpoint,
        string $key,
        protected UtopiaDatabase $dbForProject,
        callable $getDatabasesDB,
        protected array $collectionStructure,
        protected OnDuplicate $onDuplicate = OnDuplicate::Fail,
    ) {
        $this->project = $project;
        $this->endpoint = $endpoint;
        $this->key = $key;

        $this->client = (new Client())
            ->setEndpoint($endpoint)
            ->setProject($project)
            ->setKey($key);

        $this->functions = new Functions($this->client);
        $this->messaging = new Messaging($this->client);
        $this->sites = new Sites($this->client);
        $this->storage = new Storage($this->client);
        $this->teams = new Teams($this->client);
        $this->users = new Users($this->client);

        $this->getDatabasesDB = $getDatabasesDB;
    }

    /** Orphan cleanup runs only after a successful migration — a mid-run throw preserves the destination as-is. */
    #[Override]
    public function run(
        array $resources,
        callable $callback,
        string $rootResourceId = '',
        string $rootResourceType = '',
    ): void {
        $this->resetRunState();
        parent::run($resources, $callback, $rootResourceId, $rootResourceType);
        $this->cleanupUpsertOrphans();
    }

    /** Per-run state must not leak across run() invocations on a reused instance. */
    private function resetRunState(): void
    {
        $this->rowBuffer = [];
        $this->orphansByTable = [];
        $this->processedTwoWayPairs = [];
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
            Resource::TYPE_DATABASE_VECTORSDB,
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

            // Messaging
            Resource::TYPE_PROVIDER,
            Resource::TYPE_TOPIC,
            Resource::TYPE_SUBSCRIBER,
            Resource::TYPE_MESSAGE,

            // Sites
            Resource::TYPE_SITE,
            Resource::TYPE_SITE_DEPLOYMENT,
            Resource::TYPE_SITE_VARIABLE,
        ];
    }

    /**
     * @param array<string> $resources
     * @return array<string, int>
     * @throws AppwriteException
     */
    #[Override]
    public function report(array $resources = [], array $resourceIds = []): array
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

            // Messaging
            if (\in_array(Resource::TYPE_PROVIDER, $resources)) {
                $scope = 'providers.read';
                $this->messaging->listProviders();

                $scope = 'providers.write';
                $this->messaging->createSendgridProvider('', '');
            }

            if (\in_array(Resource::TYPE_TOPIC, $resources)) {
                $scope = 'topics.read';
                $this->messaging->listTopics();

                $scope = 'topics.write';
                $this->messaging->createTopic('', '');
            }

            if (\in_array(Resource::TYPE_SUBSCRIBER, $resources)) {
                $scope = 'subscribers.read';
                $this->messaging->listSubscribers('');

                $scope = 'subscribers.write';
                $this->messaging->createSubscriber('', '', '');
            }

            if (\in_array(Resource::TYPE_MESSAGE, $resources)) {
                $scope = 'messages.read';
                $this->messaging->listMessages();

                $scope = 'messages.write';
                $this->messaging->createEmail('', '', '', draft: true);
            }

            // Sites
            if (\in_array(Resource::TYPE_SITE, $resources)) {
                $scope = 'sites.read';
                $this->sites->list();

                $scope = 'sites.write';
                $this->sites->create('', '', Framework::OTHER(), BuildRuntime::STATIC1());
            }

        } catch (AppwriteException $e) {
            if ($e->getCode() === 403) {
                throw new \Exception(
                    'Missing scope: ' . $scope,
                    (int) $e->getCode() ?: Exception::CODE_FORBIDDEN,
                    $e
                );
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
                    Transfer::GROUP_MESSAGING => $this->importMessagingResource($resource),
                    Transfer::GROUP_SITES => $this->importSiteResource($resource),
                    default => throw new \Exception('Invalid resource group', Exception::CODE_VALIDATION),
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
            case Resource::TYPE_DATABASE_VECTORSDB:
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

        $createdAt = $this->normalizeDateTime($resource->getCreatedAt());
        $updatedAt = $this->normalizeDateTime($resource->getUpdatedAt(), $createdAt);

        if ($this->onDuplicate !== OnDuplicate::Fail) {
            $existing = $this->dbForProject->getDocument(self::META_DATABASES, $resource->getId());
            $action = $this->onDuplicate->resolveSchemaAction(
                !$existing->isEmpty(),
                $createdAt,
                $existing->getCreatedAt(),
                $updatedAt,
                $existing->getUpdatedAt(),
                canDrop: false,
            );
            if ($action !== SchemaAction::Create && $this->databaseSpecMatches($existing, $resource)) {
                $action = SchemaAction::Tolerate;
            }

            $earlyReturn = match ($action) {
                SchemaAction::Tolerate => (function () use ($resource, $existing): bool {
                    $resource->setSequence($existing->getSequence());
                    $resource->setStatus(Resource::STATUS_SKIPPED, 'Already exists on destination');
                    return false;
                })(),
                SchemaAction::UpdateInPlace => (function () use ($resource, $existing, $updatedAt): bool {
                    $this->dbForProject->updateDocument(self::META_DATABASES, $existing->getId(), new UtopiaDocument([
                        'name' => $resource->getDatabaseName(),
                        'search' => implode(' ', [$resource->getId(), $resource->getDatabaseName()]),
                        'enabled' => $resource->getEnabled(),
                        'type' => empty($resource->getType()) ? 'legacy' : $resource->getType(),
                        'originalId' => empty($resource->getOriginalId()) ? null : $resource->getOriginalId(),
                        'database' => $resource->getDatabase(),
                        '$updatedAt' => $updatedAt,
                    ]));
                    $resource->setSequence($existing->getSequence());
                    return true;
                })(),
                SchemaAction::Create, SchemaAction::DropAndRecreate => null,
            };
            if ($earlyReturn !== null) {
                return $earlyReturn;
            }
        }

        $database = $this->dbForProject->createDocument(self::META_DATABASES, new UtopiaDocument([
            '$id' => $resource->getId(),
            'name' => $resource->getDatabaseName(),
            'enabled' => $resource->getEnabled(),
            'search' => implode(' ', [$resource->getId(), $resource->getDatabaseName()]),
            '$createdAt' => $createdAt,
            '$updatedAt' => $updatedAt,
            'originalId' => empty($resource->getOriginalId()) ? null : $resource->getOriginalId(),
            'type' => empty($resource->getType()) ? 'legacy' : $resource->getType(),
            // source and destination can be in different location
            'database' => $resource->getDatabase()
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
            $this->databaseCollectionId($database),
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
            self::META_DATABASES,
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

        $createdAt = $this->normalizeDateTime($resource->getCreatedAt());
        $updatedAt = $this->normalizeDateTime($resource->getUpdatedAt(), $createdAt);

        $dbForDatabases = ($this->getDatabasesDB)($database);

        // passing null in creates only creates the metadata collection
        if (!$dbForDatabases->exists(null, UtopiaDatabase::METADATA)) {
            $dbForDatabases->create();
        }

        if ($this->onDuplicate !== OnDuplicate::Fail) {
            $existing = $this->dbForProject->getDocument(
                $this->databaseCollectionId($database),
                $resource->getId()
            );
            $action = $this->onDuplicate->resolveSchemaAction(
                !$existing->isEmpty(),
                $createdAt,
                $existing->getCreatedAt(),
                $updatedAt,
                $existing->getUpdatedAt(),
                canDrop: false,
            );
            if ($action !== SchemaAction::Create && $this->tableSpecMatches($existing, $resource)) {
                $action = SchemaAction::Tolerate;
            }

            $earlyReturn = match ($action) {
                SchemaAction::Tolerate => (function () use ($resource, $existing): bool {
                    $resource->setSequence($existing->getSequence());
                    $resource->setStatus(Resource::STATUS_SKIPPED, 'Already exists on destination');
                    return false;
                })(),
                SchemaAction::UpdateInPlace => (function () use ($resource, $existing, $database, $updatedAt): bool {
                    $this->dbForProject->updateDocument(
                        $this->databaseCollectionId($database),
                        $existing->getId(),
                        new UtopiaDocument([
                            'name' => $resource->getTableName(),
                            'search' => implode(' ', [$resource->getId(), $resource->getTableName()]),
                            'enabled' => $resource->getEnabled(),
                            '$permissions' => Permission::aggregate($resource->getPermissions()),
                            'documentSecurity' => $resource->getRowSecurity(),
                            '$updatedAt' => $updatedAt,
                        ])
                    );
                    $resource->setSequence($existing->getSequence());
                    return true;
                })(),
                SchemaAction::Create, SchemaAction::DropAndRecreate => null,
            };
            if ($earlyReturn !== null) {
                return $earlyReturn;
            }
        }

        $table = $this->dbForProject->createDocument($this->databaseCollectionId($database), new UtopiaDocument([
            '$id' => $resource->getId(),
            'databaseInternalId' => $database->getSequence(),
            'databaseId' => $resource->getDatabase()->getId(),
            '$permissions' => Permission::aggregate($resource->getPermissions()),
            'documentSecurity' => $resource->getRowSecurity(),
            'enabled' => $resource->getEnabled(),
            'name' => $resource->getTableName(),
            'search' => implode(' ', [$resource->getId(), $resource->getTableName()]),
            '$createdAt' => $createdAt,
            '$updatedAt' => $updatedAt,
        ]));

        $resource->setSequence($table->getSequence());

        $dbForDatabases->createCollection(
            $this->tableCollectionId($database, $table),
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
        // column will be matching attribute as well
        // column type will be matching attribute type as well
        $type = match ($resource->getType()) {
            Column::TYPE_DATETIME => UtopiaDatabase::VAR_DATETIME,
            Column::TYPE_BOOLEAN => UtopiaDatabase::VAR_BOOLEAN,
            Column::TYPE_INTEGER => UtopiaDatabase::VAR_INTEGER,
            Column::TYPE_FLOAT => UtopiaDatabase::VAR_FLOAT,
            Column::TYPE_RELATIONSHIP => UtopiaDatabase::VAR_RELATIONSHIP,

            Column::TYPE_STRING,
            Column::TYPE_IP,
            Column::TYPE_EMAIL,
            Column::TYPE_URL,
            Column::TYPE_ENUM => UtopiaDatabase::VAR_STRING,

            Column::TYPE_POINT => UtopiaDatabase::VAR_POINT,
            Column::TYPE_LINE => UtopiaDatabase::VAR_LINESTRING,
            Column::TYPE_POLYGON => UtopiaDatabase::VAR_POLYGON,
            Column::TYPE_TEXT => UtopiaDatabase::VAR_TEXT,
            Column::TYPE_VARCHAR => UtopiaDatabase::VAR_VARCHAR,
            Column::TYPE_MEDIUMTEXT => UtopiaDatabase::VAR_MEDIUMTEXT,
            Column::TYPE_LONGTEXT => UtopiaDatabase::VAR_LONGTEXT,
            Column::TYPE_OBJECT => UtopiaDatabase::VAR_OBJECT,
            Column::TYPE_VECTOR => UtopiaDatabase::VAR_VECTOR,

            default => throw new \Exception('Invalid resource type ' . $resource->getType(), Exception::CODE_VALIDATION),
        };

        $database = $this->dbForProject->getDocument(
            self::META_DATABASES,
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
            $this->databaseCollectionId($database),
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
                $this->databaseCollectionId($database),
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

        $createdAt = $this->normalizeDateTime($resource->getCreatedAt());
        $updatedAt = $this->normalizeDateTime($resource->getUpdatedAt(), $createdAt);
        $dbForDatabases = ($this->getDatabasesDB)($database);

        $this->trackOrphanCandidate($database, $table, 'attributeKeys', $resource->getKey(), $dbForDatabases);

        $isRelationship = $type === UtopiaDatabase::VAR_RELATIONSHIP;

        // Source emits both sides of a two-way; processing one side reconciles both. Partner skip.
        $twoWayPairKey = $this->twoWayPairKey($database, $table, $resource, $type);
        if ($twoWayPairKey !== null && isset($this->processedTwoWayPairs[$twoWayPairKey])) {
            $resource->setStatus(Resource::STATUS_SKIPPED, 'Two-way partner already reconciled');
            return false;
        }

        $attributeMetaId = $this->attributeIndexMetaId($database, $table, $resource->getKey());
        if ($this->onDuplicate !== OnDuplicate::Fail) {
            $existingAttr = $this->dbForProject->getDocument(self::META_ATTRIBUTES, $attributeMetaId);
            $action = $this->onDuplicate->resolveSchemaAction(
                !$existingAttr->isEmpty(),
                $createdAt,
                $existingAttr->getCreatedAt(),
                $updatedAt,
                $existingAttr->getUpdatedAt(),
                canDrop: true,
            );
            if ($action !== SchemaAction::Create && $this->attributeSpecMatches($existingAttr, $resource, $type, $isRelationship)) {
                $action = SchemaAction::Tolerate;
            }

            $earlyReturn = match ($action) {
                SchemaAction::Tolerate => (function () use ($resource, $database, $table, $dbForDatabases): bool {
                    $this->purgeTableCaches($database, $table, $dbForDatabases);
                    $resource->setStatus(Resource::STATUS_SKIPPED, 'Already exists on destination');
                    return false;
                })(),
                SchemaAction::UpdateInPlace => ($isRelationship
                    ? $this->updateRelationshipInPlace($database, $table, $resource, $type, $updatedAt, $existingAttr, $dbForDatabases)
                    : $this->updateAttributeInPlace($database, $table, $resource, $type, $updatedAt, $existingAttr, $dbForDatabases))
                    ? true
                    : null,
                SchemaAction::DropAndRecreate, SchemaAction::Create => null,
            };
            if ($earlyReturn !== null) {
                if ($twoWayPairKey !== null) {
                    $this->processedTwoWayPairs[$twoWayPairKey] = true;
                }
                return $earlyReturn;
            }

            if ($action === SchemaAction::DropAndRecreate || $action === SchemaAction::UpdateInPlace) {
                $this->dropAttributeForRecreate($database, $table, $resource, $dbForDatabases);
            }
        }

        try {
            $column = new UtopiaDocument([
                '$id' => ID::custom($attributeMetaId),
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
                '$createdAt' => $createdAt,
                '$updatedAt' => $updatedAt,
            ]);

            $this->dbForProject->checkAttribute($table, $column);

            $column = $this->dbForProject->createDocument(self::META_ATTRIBUTES, $column);
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
            $this->purgeTableCaches($database, $table, $dbForDatabases);
            throw $e;
        }

        $this->purgeTableCaches($database, $table, $dbForDatabases);
        $options = $resource->getOptions();

        $twoWayKey = null;

        if ($type === UtopiaDatabase::VAR_RELATIONSHIP && $options['twoWay']) {
            $twoWayKey = $options['twoWayKey'];
            $options['relatedCollection'] = $table->getId();
            $options['twoWayKey'] = $resource->getKey();
            $options['side'] = UtopiaDatabase::RELATION_SIDE_CHILD;

            try {
                $twoWayAttribute = new UtopiaDocument([
                    '$id' => ID::custom($this->attributeIndexMetaId($database, $relatedTable, $twoWayKey)),
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
                    '$createdAt' => $createdAt,
                    '$updatedAt' => $updatedAt,
                ]);

                $this->dbForProject->createDocument(self::META_ATTRIBUTES, $twoWayAttribute);
            } catch (DuplicateException) {
                $this->dbForProject->deleteDocument(self::META_ATTRIBUTES, $column->getId());

                throw new Exception(
                    resourceName: $resource->getName(),
                    resourceGroup: $resource->getGroup(),
                    resourceId: $resource->getId(),
                    message: 'Attribute already exists',
                );
            } catch (LimitException) {
                $this->dbForProject->deleteDocument(self::META_ATTRIBUTES, $column->getId());

                throw new Exception(
                    resourceName: $resource->getName(),
                    resourceGroup: $resource->getGroup(),
                    resourceId: $resource->getId(),
                    message: 'Attribute limit exceeded',
                );
            } catch (\Throwable $e) {
                $this->purgeTableCaches($database, $relatedTable, $dbForDatabases);
                throw $e;
            }
        }

        try {
            switch ($type) {
                case UtopiaDatabase::VAR_RELATIONSHIP:
                    if (!$dbForDatabases->createRelationship(
                        collection: $this->tableCollectionId($database, $table),
                        relatedCollection: $this->tableCollectionId($database, $relatedTable),
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
                        $this->tableCollectionId($database, $table),
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
                        throw new \Exception('Failed to create Column', Exception::CODE_INTERNAL);
                    }
            }
        } catch (\Throwable) {
            $this->dbForProject->deleteDocument(self::META_ATTRIBUTES, $column->getId());

            if (isset($twoWayAttribute)) {
                $this->dbForProject->deleteDocument(self::META_ATTRIBUTES, $twoWayAttribute->getId());
            }

            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Failed to create column',
            );
        }

        if ($type === UtopiaDatabase::VAR_RELATIONSHIP && $options['twoWay']) {
            $this->dbForProject->purgeCachedDocument($this->databaseCollectionId($database), $relatedTable->getId());
        }

        $this->purgeTableCaches($database, $table, $dbForDatabases);

        if ($twoWayPairKey !== null) {
            $this->processedTwoWayPairs[$twoWayPairKey] = true;
        }

        return true;
    }

    /**
     * @throws Exception
     * @throws \Throwable
     */
    protected function createIndex(Index $resource): bool
    {
        $database = $this->dbForProject->getDocument(
            self::META_DATABASES,
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
            $this->databaseCollectionId($database),
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

        $count = $this->dbForProject->count(self::META_INDEXES, [
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

        $createdAt = $this->normalizeDateTime($resource->getCreatedAt());
        $updatedAt = $this->normalizeDateTime($resource->getUpdatedAt(), $createdAt);

        $this->trackOrphanCandidate($database, $table, 'indexKeys', $resource->getKey(), $dbForDatabases);

        $index = new UtopiaDocument([
            '$id' => ID::custom($this->attributeIndexMetaId($database, $table, $resource->getKey())),
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
            '$createdAt' => $createdAt,
            '$updatedAt' => $updatedAt,
        ]);

        /**
         * @var array<UtopiaDocument> $tableColumns
         */
        $tableColumns = $table->getAttribute('attributes', []);
        $tableIndexes = $table->getAttribute('indexes', []);

        $validator = new IndexValidator(
            $tableColumns,
            $tableIndexes,
            $dbForDatabases->getAdapter()->getMaxIndexLength(),
            $dbForDatabases->getAdapter()->getInternalIndexesKeys(),
            $dbForDatabases->getAdapter()->getSupportForIndexArray(),
            $dbForDatabases->getAdapter()->getSupportForSpatialIndexNull(),
            $dbForDatabases->getAdapter()->getSupportForSpatialIndexOrder(),
            $dbForDatabases->getAdapter()->getSupportForVectors(),
            $dbForDatabases->getAdapter()->getSupportForAttributes(),
            $dbForDatabases->getAdapter()->getSupportForMultipleFulltextIndexes(),
            $dbForDatabases->getAdapter()->getSupportForIdenticalIndexes(),
            $dbForDatabases->getAdapter()->getSupportForObjectIndexes(),
            $dbForDatabases->getAdapter()->getSupportForTrigramIndex(),
            $dbForDatabases->getAdapter()->getSupportForSpatialAttributes(),
            $dbForDatabases->getAdapter()->getSupportForIndex(),
            $dbForDatabases->getAdapter()->getSupportForUniqueIndex(),
            $dbForDatabases->getAdapter()->getSupportForFulltextIndex()
        );


        if (!$validator->isValid($index)) {
            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Invalid index: ' . $validator->getDescription(),
            );
        }

        $indexMetaId = $this->attributeIndexMetaId($database, $table, $resource->getKey());
        if ($this->onDuplicate !== OnDuplicate::Fail) {
            $existingIdx = $this->dbForProject->getDocument(self::META_INDEXES, $indexMetaId);
            $action = $this->onDuplicate->resolveSchemaAction(
                !$existingIdx->isEmpty(),
                $createdAt,
                $existingIdx->getCreatedAt(),
                $updatedAt,
                $existingIdx->getUpdatedAt(),
                canDrop: true,
            );
            if ($action !== SchemaAction::Create && $this->indexSpecMatches($existingIdx, $resource)) {
                $action = SchemaAction::Tolerate;
            }

            // Indexes have no in-place primitive — any action other than Tolerate falls through to drop+recreate.
            $earlyReturn = match ($action) {
                SchemaAction::Tolerate => (function () use ($resource, $database, $table): bool {
                    $this->dbForProject->purgeCachedDocument($this->databaseCollectionId($database), $table->getId());
                    $resource->setStatus(Resource::STATUS_SKIPPED, 'Already exists on destination');
                    return false;
                })(),
                SchemaAction::UpdateInPlace, SchemaAction::DropAndRecreate, SchemaAction::Create => null,
            };
            if ($earlyReturn !== null) {
                return $earlyReturn;
            }

            if ($action === SchemaAction::DropAndRecreate || $action === SchemaAction::UpdateInPlace) {
                $dbForDatabases->deleteIndex($this->tableCollectionId($database, $table), $resource->getKey());
                $this->dbForProject->deleteDocument(self::META_INDEXES, $indexMetaId);
                $this->dbForProject->purgeCachedDocument($this->databaseCollectionId($database), $table->getId());
            }
        }

        $index = $this->dbForProject->createDocument(self::META_INDEXES, $index);

        try {
            $result = $dbForDatabases->createIndex(
                $this->tableCollectionId($database, $table),
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
            $this->dbForProject->deleteDocument(self::META_INDEXES, $index->getId());

            throw new Exception(
                resourceName: $resource->getName(),
                resourceGroup: $resource->getGroup(),
                resourceId: $resource->getId(),
                message: 'Failed to create index',
            );
        }

        $this->dbForProject->purgeCachedDocument($this->databaseCollectionId($database), $table->getId());

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

        $data = $resource->getData();

        $hasCreatedAt = !empty($data['$createdAt']);
        $hasUpdatedAt = !empty($data['$updatedAt']);

        if (! $hasCreatedAt) {
            $createdAt = $resource->getCreatedAt();
            if (empty($createdAt)) {
                $createdAt = $data['created_at'] ?? $data['createdAt'] ?? null;
            }

            $data['$createdAt'] = $this->normalizeDateTime($createdAt);
        }

        if (! $hasUpdatedAt) {
            $updatedAt = $resource->getUpdatedAt();
            if (empty($updatedAt)) {
                $updatedAt = $data['updated_at'] ?? $data['updatedAt'] ?? null;
            }

            if (empty($updatedAt)) {
                $data['$updatedAt'] = (string) $data['$createdAt'];
            } else {
                $data['$updatedAt'] = $this->normalizeDateTime($updatedAt, $data['$createdAt']);
            }
        }

        $this->rowBuffer[] = new UtopiaDocument(\array_merge([
            '$id' => $resource->getId(),
            '$permissions' => $resource->getPermissions(),
        ], $data));

        if ($isLast) {
            try {
                $database = $this->dbForProject->getDocument(
                    self::META_DATABASES,
                    $resource->getTable()->getDatabase()->getId(),
                );

                $table = $this->dbForProject->getDocument(
                    $this->databaseCollectionId($database),
                    $resource->getTable()->getId(),
                );

                $dbForDatabases = ($this->getDatabasesDB)($database);

                // Drop schema orphans before rows land so the Structure validator doesn't reject on orphan required columns.
                $this->cleanupUpsertOrphansForTable($this->tableIdentity($database, $table));
                // Strip row payload fields the table doesn't declare — guards against orphans surviving in source archives.
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
                $collectionId = $this->tableCollectionId($database, $table);

                match ($this->onDuplicate) {
                    OnDuplicate::Upsert => $dbForDatabases->skipRelationshipsExistCheck(
                        fn () => $dbForDatabases->upsertDocuments($collectionId, $this->rowBuffer)
                    ),
                    OnDuplicate::Skip => $dbForDatabases->skipDuplicates(
                        fn () => $dbForDatabases->skipRelationshipsExistCheck(
                            fn () => $dbForDatabases->createDocuments($collectionId, $this->rowBuffer)
                        )
                    ),
                    OnDuplicate::Fail => $dbForDatabases->skipRelationshipsExistCheck(
                        fn () => $dbForDatabases->createDocuments($collectionId, $this->rowBuffer)
                    ),
                };

            } finally {
                $this->rowBuffer = [];
            }
        }


        return true;
    }

    /**
     * Drop attribute (meta doc + physical column). Relationships route through
     * deleteRelationship since deleteAttribute throws for VAR_RELATIONSHIP.
     */
    private function dropAttributeForRecreate(
        UtopiaDocument $database,
        UtopiaDocument $table,
        Column|Attribute $resource,
        UtopiaDatabase $dbForDatabases,
    ): void {
        $collectionId = $this->tableCollectionId($database, $table);
        $attributeMetaId = $this->attributeIndexMetaId($database, $table, $resource->getKey());
        $isRelationship = $resource->getType() === Column::TYPE_RELATIONSHIP;

        if ($isRelationship) {
            $dbForDatabases->deleteRelationship($collectionId, $resource->getKey());
        } else {
            $dbForDatabases->deleteAttribute($collectionId, $resource->getKey());
        }

        $this->dbForProject->deleteDocument(self::META_ATTRIBUTES, $attributeMetaId);

        // deleteRelationship only touches utopia's _metadata; clear the Appwrite-level partner meta doc too.
        if ($isRelationship) {
            $partner = $this->resolveTwoWayPartner($database, $resource->getOptions());
            if ($partner !== null) {
                $this->bestEffort(fn () => $this->dbForProject->deleteDocument(self::META_ATTRIBUTES, $partner['partnerMetaId']));
            }
        }

        $this->purgeTableCaches($database, $table, $dbForDatabases);
    }

    /** Returns false when the source change isn't SDK-expressible — caller falls through to DropAndRecreate. */
    private function updateAttributeInPlace(
        UtopiaDocument $database,
        UtopiaDocument $table,
        Column|Attribute $resource,
        string $type,
        string $updatedAt,
        UtopiaDocument $existingAttr,
        UtopiaDatabase $dbForDatabases,
    ): bool {
        $sourceFields = [
            'type'          => $type,
            'array'         => $resource->isArray(),
            'signed'        => $resource->isSigned(),
            'format'        => $resource->getFormat(),
            'formatOptions' => $resource->getFormatOptions(),
            'filters'       => $resource->getFilters(),
        ];

        $existingFields = [];
        foreach (self::ATTRIBUTE_NON_SDK_FIELDS as $field) {
            $existingFields[$field] = $existingAttr->getAttribute($field);
        }

        if ($this->arraysDifferOnKeys($sourceFields, $existingFields, self::ATTRIBUTE_NON_SDK_FIELDS)) {
            return false;
        }

        // Pass existing values for non-SDK fields so utopia doesn't trigger an ALTER for unchanged fields.
        $dbForDatabases->updateAttribute(
            collection: $this->tableCollectionId($database, $table),
            id: $resource->getKey(),
            type: $type,
            size: $resource->getSize(),
            required: $resource->isRequired(),
            default: $resource->getDefault(),
            signed: $existingAttr->getAttribute('signed'),
            array: $existingAttr->getAttribute('array'),
            format: $resource->getFormat(),
            formatOptions: $resource->getFormatOptions(),
            filters: $existingAttr->getAttribute('filters'),
        );

        $this->dbForProject->updateDocument(self::META_ATTRIBUTES, $existingAttr->getId(), new UtopiaDocument([
            'key' => $resource->getKey(),
            'type' => $type,
            'size' => $resource->getSize(),
            'required' => $resource->isRequired(),
            'signed' => $resource->isSigned(),
            'default' => $resource->getDefault(),
            'array' => $resource->isArray(),
            'format' => $resource->getFormat(),
            'formatOptions' => $resource->getFormatOptions(),
            'filters' => $resource->getFilters(),
            '$updatedAt' => $updatedAt,
        ]));

        $this->purgeTableCaches($database, $table, $dbForDatabases);
        return true;
    }

    /**
     * Returns false when the source change isn't SDK-expressible — caller falls through to DropAndRecreate.
     * One-way + onDelete change is also rejected: utopia's updateRelationship partner-cascade throws on one-way.
     */
    private function updateRelationshipInPlace(
        UtopiaDocument $database,
        UtopiaDocument $table,
        Column|Attribute $resource,
        string $type,
        string $updatedAt,
        UtopiaDocument $existingAttr,
        UtopiaDatabase $dbForDatabases,
    ): bool {
        $sourceOptions = $resource->getOptions();
        $destOptions = $existingAttr->getAttribute('options', []);

        if ($this->arraysDifferOnKeys($sourceOptions, $destOptions, self::RELATIONSHIP_STRUCTURAL_FIELDS)) {
            return false;
        }

        $isTwoWay = (bool) ($destOptions['twoWay'] ?? false);
        $onDeleteChanged = ($sourceOptions['onDelete'] ?? null) !== ($destOptions['onDelete'] ?? null);

        if (!$isTwoWay && $onDeleteChanged) {
            return false;
        }

        if ($onDeleteChanged) {
            $dbForDatabases->updateRelationship(
                collection: $this->tableCollectionId($database, $table),
                id: $resource->getKey(),
                onDelete: (string) ($sourceOptions['onDelete'] ?? ''),
            );
        }

        $this->dbForProject->updateDocument(self::META_ATTRIBUTES, $existingAttr->getId(), new UtopiaDocument([
            'key' => $resource->getKey(),
            'type' => $type,
            'options' => array_merge($destOptions, [
                'onDelete' => $sourceOptions['onDelete'] ?? $destOptions['onDelete'] ?? null,
            ]),
            '$updatedAt' => $updatedAt,
        ]));

        $this->purgeTableCaches($database, $table, $dbForDatabases);

        // utopia syncs both physical sides; partner's Appwrite-level meta doc has to be refreshed by hand.
        if ($isTwoWay) {
            $this->refreshTwoWayPartnerOnDelete($database, $destOptions, $sourceOptions, $updatedAt, $dbForDatabases);
        }

        return true;
    }

    /**
     * @param array<string, mixed> $destOptions
     * @param array<string, mixed> $sourceOptions
     */
    private function refreshTwoWayPartnerOnDelete(
        UtopiaDocument $database,
        array $destOptions,
        array $sourceOptions,
        string $updatedAt,
        UtopiaDatabase $dbForDatabases,
    ): void {
        $partner = $this->resolveTwoWayPartner($database, $destOptions);
        if ($partner === null) {
            return;
        }

        $partnerMeta = $this->dbForProject->getDocument(self::META_ATTRIBUTES, $partner['partnerMetaId']);
        if ($partnerMeta->isEmpty()) {
            return;
        }

        $partnerOptions = $partnerMeta->getAttribute('options', []);
        $this->dbForProject->updateDocument(self::META_ATTRIBUTES, $partnerMeta->getId(), new UtopiaDocument([
            'options' => array_merge($partnerOptions, [
                'onDelete' => $sourceOptions['onDelete'] ?? $partnerOptions['onDelete'] ?? null,
            ]),
            '$updatedAt' => $updatedAt,
        ]));
        $this->purgeTableCaches($database, $partner['relatedTable'], $dbForDatabases);
    }

    private function databaseSpecMatches(UtopiaDocument $existing, Database $resource): bool
    {
        $sourceType = empty($resource->getType()) ? 'legacy' : $resource->getType();
        $sourceOriginalId = empty($resource->getOriginalId()) ? null : $resource->getOriginalId();

        return $existing->getAttribute('name')       === $resource->getDatabaseName()
            && $existing->getAttribute('enabled')    === $resource->getEnabled()
            && $existing->getAttribute('type')       === $sourceType
            && $existing->getAttribute('originalId') === $sourceOriginalId
            && $existing->getAttribute('database')   === $resource->getDatabase();
    }

    private function tableSpecMatches(UtopiaDocument $existing, Table $resource): bool
    {
        if ($existing->getAttribute('name') !== $resource->getTableName()
            || $existing->getAttribute('enabled') !== $resource->getEnabled()
            || $existing->getAttribute('documentSecurity') !== $resource->getRowSecurity()) {
            return false;
        }

        $sourcePerms = Permission::aggregate($resource->getPermissions());
        /** @var list<string> $destPerms */
        $destPerms = $existing->getAttribute('$permissions', []);
        \sort($sourcePerms);
        \sort($destPerms);
        return $sourcePerms === $destPerms;
    }

    /** Full-spec equality: short-circuits both DropAndRecreate and UpdateInPlace to Tolerate when nothing changed. */
    private function attributeSpecMatches(UtopiaDocument $existing, Column|Attribute $resource, string $type, bool $isRelationship): bool
    {
        if ($existing->getAttribute('type') !== $type) {
            return false;
        }
        if ($isRelationship) {
            $sourceOptions = $resource->getOptions();
            /** @var array<string, mixed> $destOptions */
            $destOptions = $existing->getAttribute('options', []);
            foreach (self::RELATIONSHIP_STRUCTURAL_FIELDS as $field) {
                if (($sourceOptions[$field] ?? null) !== ($destOptions[$field] ?? null)) {
                    return false;
                }
            }
            return ($sourceOptions['onDelete'] ?? null) === ($destOptions['onDelete'] ?? null);
        }

        return $existing->getAttribute('size')          === $resource->getSize()
            && $existing->getAttribute('required')      === $resource->isRequired()
            && $existing->getAttribute('default')       === $resource->getDefault()
            && $existing->getAttribute('array')         === $resource->isArray()
            && $existing->getAttribute('signed')        === $resource->isSigned()
            && $existing->getAttribute('format')        === $resource->getFormat()
            && $existing->getAttribute('formatOptions') === $resource->getFormatOptions()
            && $existing->getAttribute('filters')       === $resource->getFilters();
    }

    /**
     * `lengths` is intentionally not compared: it's derived per-column from
     * the adapter's index validator and not settable on the Index resource.
     */
    private function indexSpecMatches(UtopiaDocument $existingIdx, Index $resource): bool
    {
        return $existingIdx->getAttribute('type')       === $resource->getType()
            && $existingIdx->getAttribute('attributes') === $resource->getColumns()
            && $existingIdx->getAttribute('orders')     === $resource->getOrders();
    }

    private function tableIdentity(UtopiaDocument $database, UtopiaDocument $table): string
    {
        return $database->getSequence() . ':' . $table->getSequence();
    }

    /**
     * @param array<string, mixed> $options
     * @return array{relatedTable: UtopiaDocument, partnerKey: string, partnerMetaId: string}|null
     */
    private function resolveTwoWayPartner(UtopiaDocument $database, array $options): ?array
    {
        if (empty($options['twoWay'])) {
            return null;
        }
        $relatedTableId = (string) ($options['relatedCollection'] ?? '');
        $partnerKey = (string) ($options['twoWayKey'] ?? '');
        if ($relatedTableId === '' || $partnerKey === '') {
            return null;
        }
        $relatedTable = $this->dbForProject->getDocument(
            $this->databaseCollectionId($database),
            $relatedTableId,
        );
        if ($relatedTable->isEmpty()) {
            return null;
        }
        return [
            'relatedTable' => $relatedTable,
            'partnerKey' => $partnerKey,
            'partnerMetaId' => $this->attributeIndexMetaId($database, $relatedTable, $partnerKey),
        ];
    }

    /** Canonical pair-key — same string regardless of which side is being processed. */
    private function twoWayPairKey(
        UtopiaDocument $database,
        UtopiaDocument $table,
        Column|Attribute $resource,
        string $type,
    ): ?string {
        if ($type !== UtopiaDatabase::VAR_RELATIONSHIP) {
            return null;
        }
        $options = $resource->getOptions();
        if (empty($options['twoWay'])) {
            return null;
        }
        $twoWayKey = (string) ($options['twoWayKey'] ?? '');
        $relatedTableId = (string) ($options['relatedCollection'] ?? '');
        if ($twoWayKey === '' || $relatedTableId === '') {
            return null;
        }

        $thisSide = $table->getId() . '::' . $resource->getKey();
        $partnerSide = $relatedTableId . '::' . $twoWayKey;
        $pair = [$thisSide, $partnerSide];
        \sort($pair);
        return $database->getSequence() . '@' . \implode('<->', $pair);
    }

    private function databaseCollectionId(UtopiaDocument $database): string
    {
        return 'database_' . $database->getSequence();
    }

    private function tableCollectionId(UtopiaDocument $database, UtopiaDocument $table): string
    {
        return $this->databaseCollectionId($database) . '_collection_' . $table->getSequence();
    }

    private function attributeIndexMetaId(UtopiaDocument $database, UtopiaDocument $table, string $key): string
    {
        return $database->getSequence() . '_' . $table->getSequence() . '_' . $key;
    }

    /** Stale platform/per-database cache shows the pre-change schema; evict after every structural change. */
    private function purgeTableCaches(
        UtopiaDocument $database,
        UtopiaDocument $table,
        UtopiaDatabase $dbForDatabases,
    ): void {
        $this->dbForProject->purgeCachedDocument($this->databaseCollectionId($database), $table->getId());
        $dbForDatabases->purgeCachedCollection($this->tableCollectionId($database, $table));
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     * @param list<string> $keys
     */
    private function arraysDifferOnKeys(array $a, array $b, array $keys): bool
    {
        foreach ($keys as $key) {
            if (($a[$key] ?? null) !== ($b[$key] ?? null)) {
                return true;
            }
        }
        return false;
    }

    /** $kind is 'attributeKeys' or 'indexKeys'. */
    private function trackOrphanCandidate(
        UtopiaDocument $database,
        UtopiaDocument $table,
        string $kind,
        string $key,
        UtopiaDatabase $dbForDatabases,
    ): void {
        if ($this->onDuplicate !== OnDuplicate::Upsert) {
            return;
        }
        $tableId = $this->tableIdentity($database, $table);
        if (!isset($this->orphansByTable[$tableId])) {
            $this->orphansByTable[$tableId] = [
                'database' => $database,
                'table' => $table,
                'dbForDatabases' => $dbForDatabases,
                'attributeKeys' => [],
                'indexKeys' => [],
            ];
        }
        $this->orphansByTable[$tableId][$kind][] = $key;
    }

    /** End-of-migration sweep — only visits tables that had no rows (rest were cleaned per-table in createRecord). */
    private function cleanupUpsertOrphans(): void
    {
        foreach (\array_keys($this->orphansByTable) as $tableId) {
            $this->cleanupUpsertOrphansForTable($tableId);
        }
    }

    /** Called per-table from createRecord before rows land so Structure validator sees the post-cleanup schema. */
    private function cleanupUpsertOrphansForTable(string $tableId): void
    {
        if ($this->onDuplicate !== OnDuplicate::Upsert) {
            return;
        }
        if (!isset($this->orphansByTable[$tableId])) {
            return;
        }

        $tracked = $this->orphansByTable[$tableId];
        $database = $tracked['database'];
        $table = $tracked['table'];
        $dbForDatabases = $tracked['dbForDatabases'];

        $this->dropOrphansByKind(
            self::META_ATTRIBUTES,
            $tracked['attributeKeys'],
            $database,
            $table,
            fn (UtopiaDocument $doc) => $this->dropOrphanAttribute($database, $table, $doc, $dbForDatabases),
        );

        $this->dropOrphansByKind(
            self::META_INDEXES,
            $tracked['indexKeys'],
            $database,
            $table,
            fn (UtopiaDocument $doc) => $this->dropOrphanIndex(
                $database,
                $table,
                (string) $doc->getAttribute('key'),
                $dbForDatabases,
            ),
        );

        unset($this->orphansByTable[$tableId]);
    }

    /**
     * @param list<string> $processedKeys
     * @param callable(UtopiaDocument): void $drop
     */
    private function dropOrphansByKind(
        string $metaCollection,
        array $processedKeys,
        UtopiaDocument $database,
        UtopiaDocument $table,
        callable $drop,
    ): void {
        $destDocs = $this->dbForProject->find($metaCollection, [
            Query::equal('databaseInternalId', [$database->getSequence()]),
            Query::equal('collectionInternalId', [$table->getSequence()]),
            Query::limit(PHP_INT_MAX),
        ]);
        foreach ($destDocs as $destDoc) {
            if (!\in_array($destDoc->getAttribute('key'), $processedKeys, true)) {
                $drop($destDoc);
            }
        }
    }

    /** Reads dest's own meta doc as source of truth — there's no source resource for an orphan. */
    private function dropOrphanAttribute(
        UtopiaDocument $database,
        UtopiaDocument $table,
        UtopiaDocument $attrDoc,
        UtopiaDatabase $dbForDatabases,
    ): void {
        $key = (string) $attrDoc->getAttribute('key');
        $type = (string) $attrDoc->getAttribute('type');
        $options = $attrDoc->getAttribute('options', []);
        $collectionId = $this->tableCollectionId($database, $table);

        if ($type === UtopiaDatabase::VAR_RELATIONSHIP) {
            $this->bestEffort(fn () => $dbForDatabases->deleteRelationship($collectionId, $key));
        } else {
            $this->bestEffort(fn () => $dbForDatabases->deleteAttribute($collectionId, $key));
        }
        $this->bestEffort(fn () => $this->dbForProject->deleteDocument(self::META_ATTRIBUTES, $attrDoc->getId()));
        $this->dbForProject->purgeCachedDocument($this->databaseCollectionId($database), $table->getId());
        $dbForDatabases->purgeCachedCollection($collectionId);

        if ($type !== UtopiaDatabase::VAR_RELATIONSHIP) {
            return;
        }
        $partner = $this->resolveTwoWayPartner($database, $options);
        if ($partner === null) {
            return;
        }

        // deleteRelationship already dropped the partner physical column; only the Appwrite-level meta doc remains.
        $this->bestEffort(fn () => $this->dbForProject->deleteDocument(self::META_ATTRIBUTES, $partner['partnerMetaId']));
        $this->purgeTableCaches($database, $partner['relatedTable'], $dbForDatabases);
    }

    private function dropOrphanIndex(
        UtopiaDocument $database,
        UtopiaDocument $table,
        string $indexKey,
        UtopiaDatabase $dbForDatabases,
    ): void {
        $collectionId = $this->tableCollectionId($database, $table);
        $indexMetaId = $this->attributeIndexMetaId($database, $table, $indexKey);

        $this->bestEffort(fn () => $dbForDatabases->deleteIndex($collectionId, $indexKey));
        $this->bestEffort(fn () => $this->dbForProject->deleteDocument(self::META_INDEXES, $indexMetaId));
        $this->dbForProject->purgeCachedDocument($this->databaseCollectionId($database), $table->getId());
    }

    /** Swallows deletion errors — a prior run or relationship cascade may have already removed the target. */
    private function bestEffort(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable) {
            // already gone
        }
    }

    /**
     * @throws \Exception
     */
    private function normalizeDateTime(mixed $value, mixed $fallback = null): string
    {
        $resolved = $this->stringifyDateValue($value)
            ?? $this->stringifyDateValue($fallback);

        if (empty($resolved)) {
            return DateTime::format(new \DateTime());
        }

        if (\is_numeric($resolved) && \strlen($resolved) === 10 && (int) $resolved > 0) { // Unix timestamp
            $resolved = '@' . $resolved;
        }

        return DateTime::format(new \DateTime($resolved));
    }

    private function stringifyDateValue(mixed $value): ?string
    {
        if (\is_string($value)) {
            return $value;
        }
        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }

        return null;
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
                    default => throw new \Exception('Invalid Compression: ' . $resource->getCompression(), Exception::CODE_VALIDATION),
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
                    $resource->getAntiVirus(),
                    $resource->getTransformations()
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

                if (!empty($resource->getTargets())) {
                    $this->importUserTargets($resource->getId(), $resource->getTargets());
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
            throw new \Exception('Password hash is missing', Exception::CODE_VALIDATION);
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
                    default => throw new \Exception('Invalid Runtime: ' . $resource->getRuntime(), Exception::CODE_VALIDATION),
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
                    $resource->getLogging(),
                    $resource->getEntrypoint(),
                    $resource->getCommands(),
                    $resource->getScopes(),
                    specification: $resource->getSpecification() ?: null,
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
        $function = $deployment->getFunction();

        // Deployment API always creates a new deployment, so unlike other resources
        // there's no duplicate detection. Skip if the parent function wasn't imported successfully.
        if ($function->getStatus() !== Resource::STATUS_SUCCESS) {
            $deployment->setStatus(Resource::STATUS_SKIPPED, 'Parent function "' . $function->getId() . '" failed to import');

            return $deployment;
        }

        $functionId = $function->getId();

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
                'activate' => $deployment->getActivated() ? 'true' : 'false',
                'entrypoint' => $deployment->getEntrypoint(),
            ]
        );

        if (!\is_array($response) || !isset($response['$id'])) {
            throw new \Exception('Deployment creation failed', Exception::CODE_INTERNAL);
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

    /**
     * @throws AppwriteException
     * @throws \Exception
     */
    public function importMessagingResource(Resource $resource): Resource
    {
        switch ($resource->getName()) {
            case Resource::TYPE_PROVIDER:
                /** @var Provider $resource */
                $this->createProvider($resource);
                break;
            case Resource::TYPE_TOPIC:
                /** @var Topic $resource */
                $this->createTopic($resource);
                break;
            case Resource::TYPE_SUBSCRIBER:
                /** @var Subscriber $resource */
                $this->createSubscriber($resource);
                break;
            case Resource::TYPE_MESSAGE:
                /** @var Message $resource */
                $this->createMessage($resource);
                break;
            default:
                throw new \Exception('Unknown messaging resource type: ' . $resource->getName());
        }

        $resource->setStatus(Resource::STATUS_SUCCESS);

        return $resource;
    }

    /**
     * @throws AppwriteException
     */
    public function importSiteResource(Resource $resource): Resource
    {
        switch ($resource->getName()) {
            case Resource::TYPE_SITE:
                /** @var Site $resource */

                $buildRuntime = match ($resource->getBuildRuntime()) {
                    'node-14.5' => BuildRuntime::NODE145(),
                    'node-16.0' => BuildRuntime::NODE160(),
                    'node-18.0' => BuildRuntime::NODE180(),
                    'node-19.0' => BuildRuntime::NODE190(),
                    'node-20.0' => BuildRuntime::NODE200(),
                    'node-21.0' => BuildRuntime::NODE210(),
                    'node-22' => BuildRuntime::NODE22(),
                    'php-8.0' => BuildRuntime::PHP80(),
                    'php-8.1' => BuildRuntime::PHP81(),
                    'php-8.2' => BuildRuntime::PHP82(),
                    'php-8.3' => BuildRuntime::PHP83(),
                    'ruby-3.0' => BuildRuntime::RUBY30(),
                    'ruby-3.1' => BuildRuntime::RUBY31(),
                    'ruby-3.2' => BuildRuntime::RUBY32(),
                    'ruby-3.3' => BuildRuntime::RUBY33(),
                    'python-3.8' => BuildRuntime::PYTHON38(),
                    'python-3.9' => BuildRuntime::PYTHON39(),
                    'python-3.10' => BuildRuntime::PYTHON310(),
                    'python-3.11' => BuildRuntime::PYTHON311(),
                    'python-3.12' => BuildRuntime::PYTHON312(),
                    'python-ml-3.11' => BuildRuntime::PYTHONML311(),
                    'python-ml-3.12' => BuildRuntime::PYTHONML312(),
                    'dart-3.0' => BuildRuntime::DART30(),
                    'dart-3.1' => BuildRuntime::DART31(),
                    'dart-3.3' => BuildRuntime::DART33(),
                    'dart-3.5' => BuildRuntime::DART35(),
                    'dart-3.8' => BuildRuntime::DART38(),
                    'dart-3.9' => BuildRuntime::DART39(),
                    'dart-2.15' => BuildRuntime::DART215(),
                    'dart-2.16' => BuildRuntime::DART216(),
                    'dart-2.17' => BuildRuntime::DART217(),
                    'dart-2.18' => BuildRuntime::DART218(),
                    'dart-2.19' => BuildRuntime::DART219(),
                    'deno-1.21' => BuildRuntime::DENO121(),
                    'deno-1.24' => BuildRuntime::DENO124(),
                    'deno-1.35' => BuildRuntime::DENO135(),
                    'deno-1.40' => BuildRuntime::DENO140(),
                    'deno-1.46' => BuildRuntime::DENO146(),
                    'deno-2.0' => BuildRuntime::DENO20(),
                    'dotnet-6.0' => BuildRuntime::DOTNET60(),
                    'dotnet-7.0' => BuildRuntime::DOTNET70(),
                    'dotnet-8.0' => BuildRuntime::DOTNET80(),
                    'java-8.0' => BuildRuntime::JAVA80(),
                    'java-11.0' => BuildRuntime::JAVA110(),
                    'java-17.0' => BuildRuntime::JAVA170(),
                    'java-18.0' => BuildRuntime::JAVA180(),
                    'java-21.0' => BuildRuntime::JAVA210(),
                    'java-22' => BuildRuntime::JAVA22(),
                    'swift-5.5' => BuildRuntime::SWIFT55(),
                    'swift-5.8' => BuildRuntime::SWIFT58(),
                    'swift-5.9' => BuildRuntime::SWIFT59(),
                    'swift-5.10' => BuildRuntime::SWIFT510(),
                    'kotlin-1.6' => BuildRuntime::KOTLIN16(),
                    'kotlin-1.8' => BuildRuntime::KOTLIN18(),
                    'kotlin-1.9' => BuildRuntime::KOTLIN19(),
                    'kotlin-2.0' => BuildRuntime::KOTLIN20(),
                    'cpp-17' => BuildRuntime::CPP17(),
                    'cpp-20' => BuildRuntime::CPP20(),
                    'bun-1.0' => BuildRuntime::BUN10(),
                    'bun-1.1' => BuildRuntime::BUN11(),
                    'go-1.23' => BuildRuntime::GO123(),
                    'static-1' => BuildRuntime::STATIC1(),
                    'flutter-3.24' => BuildRuntime::FLUTTER324(),
                    'flutter-3.27' => BuildRuntime::FLUTTER327(),
                    'flutter-3.29' => BuildRuntime::FLUTTER329(),
                    'flutter-3.32' => BuildRuntime::FLUTTER332(),
                    'flutter-3.35' => BuildRuntime::FLUTTER335(),
                    default => throw new \Exception('Invalid Build Runtime: ' . $resource->getBuildRuntime(), Exception::CODE_VALIDATION),
                };

                $framework = match ($resource->getFramework()) {
                    'analog' => Framework::ANALOG(),
                    'angular' => Framework::ANGULAR(),
                    'astro' => Framework::ASTRO(),
                    'flutter', 'flutter-web' => Framework::FLUTTER(),
                    'lynx' => Framework::LYNX(),
                    'nextjs' => Framework::NEXTJS(),
                    'nuxt' => Framework::NUXT(),
                    'react' => Framework::REACT(),
                    'react-native' => Framework::REACTNATIVE(),
                    'remix' => Framework::REMIX(),
                    'svelte-kit' => Framework::SVELTEKIT(),
                    'tanstack-start' => Framework::TANSTACKSTART(),
                    'vite' => Framework::VITE(),
                    'vue' => Framework::VUE(),
                    default => Framework::OTHER(),
                };

                $adapter = match ($resource->getAdapter()) {
                    'static' => Adapter::STATIC(),
                    'ssr' => Adapter::SSR(),
                    default => null,
                };

                $this->sites->create(
                    $resource->getId(),
                    $resource->getSiteName(),
                    $framework,
                    $buildRuntime,
                    $resource->getEnabled(),
                    $resource->getLogging(),
                    $resource->getTimeout(),
                    $resource->getInstallCommand(),
                    $resource->getBuildCommand(),
                    $resource->getOutputDirectory(),
                    $adapter,
                    fallbackFile: $resource->getFallbackFile(),
                    specification: $resource->getSpecification(),
                );
                break;
            case Resource::TYPE_SITE_VARIABLE:
                /** @var SiteEnvVar $resource */
                $this->sites->createVariable(
                    $resource->getSite()->getId(),
                    $resource->getKey(),
                    $resource->getValue()
                );
                break;
            case Resource::TYPE_SITE_DEPLOYMENT:
                /** @var SiteDeployment $resource */
                return $this->importSiteDeployment($resource);
        }

        $resource->setStatus(Resource::STATUS_SUCCESS);

        return $resource;
    }

    /**
     * Import user targets not auto-created by the server (e.g. push).
     * providerInternalId is resolved later in createProvider().
     *
     * @param array<array<string, mixed>> $targets
     */
    protected function importUserTargets(string $userId, array $targets): void
    {
        $userDoc = null;

        foreach ($targets as $target) {
            switch ($target['providerType'] ?? '') {
                case 'email':
                case 'sms':
                    // Auto-created by the server when a user is created with an email/phone
                    break;
                case 'push':
                    $userDoc ??= $this->dbForProject->getDocument('users', $userId);

                    $createdAt = $this->normalizeDateTime($target['$createdAt'] ?? null);
                    $updatedAt = $this->normalizeDateTime($target['$updatedAt'] ?? null, $createdAt);

                    $this->dbForProject->createDocument('targets', new UtopiaDocument([
                        '$id' => $target['$id'],
                        '$createdAt' => $createdAt,
                        '$updatedAt' => $updatedAt,
                        '$permissions' => [
                            Permission::read(Role::user($userId)),
                            Permission::update(Role::user($userId)),
                            Permission::delete(Role::user($userId)),
                        ],
                        'userId' => $userId,
                        'userInternalId' => $userDoc->getSequence(),
                        'providerType' => $target['providerType'],
                        'providerId' => $target['providerId'] ?? null,
                        'identifier' => $target['identifier'],
                        'name' => $target['name'] ?? null,
                        'expired' => $target['expired'] ?? false,
                    ]));
                    break;
            }
        }

        if ($userDoc !== null) {
            $this->dbForProject->purgeCachedDocument('users', $userId);
        }
    }

    /**
     * @throws AppwriteException
     * @throws \Exception
     */
    protected function createProvider(Provider $resource): void
    {
        $credentials = $resource->getCredentials();
        $options = $resource->getOptions();
        $id = $resource->getId();
        $name = $resource->getProviderName();
        $enabled = $resource->getEnabled();

        match ($resource->getProvider()) {
            'mailgun' => $this->messaging->createMailgunProvider(
                $id,
                $name,
                $credentials['apiKey'] ?? null,
                $credentials['domain'] ?? null,
                $credentials['isEuRegion'] ?? null,
                ($options['fromName'] ?? '') ?: null,
                ($options['fromEmail'] ?? '') ?: null,
                ($options['replyToName'] ?? '') ?: null,
                ($options['replyToEmail'] ?? '') ?: null,
                $enabled,
            ),
            'sendgrid' => $this->messaging->createSendgridProvider(
                $id,
                $name,
                $credentials['apiKey'] ?? null,
                ($options['fromName'] ?? '') ?: null,
                ($options['fromEmail'] ?? '') ?: null,
                ($options['replyToName'] ?? '') ?: null,
                ($options['replyToEmail'] ?? '') ?: null,
                $enabled,
            ),
            'resend' => $this->messaging->createResendProvider(
                $id,
                $name,
                $credentials['apiKey'] ?? null,
                ($options['fromName'] ?? '') ?: null,
                ($options['fromEmail'] ?? '') ?: null,
                ($options['replyToName'] ?? '') ?: null,
                ($options['replyToEmail'] ?? '') ?: null,
                $enabled,
            ),
            'smtp' => $this->messaging->createSMTPProvider(
                $id,
                $name,
                $credentials['host'] ?? '',
                $credentials['port'] ?? null,
                ($credentials['username'] ?? '') ?: null,
                ($credentials['password'] ?? '') ?: null,
                match ($options['encryption'] ?? '') {
                    'ssl' => SmtpEncryption::SSL(),
                    'tls' => SmtpEncryption::TLS(),
                    default => SmtpEncryption::NONE(),
                },
                $options['autoTLS'] ?? null,
                ($options['mailer'] ?? '') ?: null,
                ($options['fromName'] ?? '') ?: null,
                ($options['fromEmail'] ?? '') ?: null,
                ($options['replyToName'] ?? '') ?: null,
                ($options['replyToEmail'] ?? '') ?: null,
                $enabled,
            ),
            'msg91' => $this->messaging->createMsg91Provider(
                $id,
                $name,
                $credentials['templateId'] ?? null,
                $credentials['senderId'] ?? null,
                $credentials['authKey'] ?? null,
                $enabled,
            ),
            'telesign' => $this->messaging->createTelesignProvider(
                $id,
                $name,
                ($options['from'] ?? '') ?: null,
                $credentials['customerId'] ?? null,
                $credentials['apiKey'] ?? null,
                $enabled,
            ),
            'textmagic' => $this->messaging->createTextmagicProvider(
                $id,
                $name,
                ($options['from'] ?? '') ?: null,
                $credentials['username'] ?? null,
                $credentials['apiKey'] ?? null,
                $enabled,
            ),
            'twilio' => $this->messaging->createTwilioProvider(
                $id,
                $name,
                ($options['from'] ?? '') ?: null,
                $credentials['accountSid'] ?? null,
                $credentials['authToken'] ?? null,
                $enabled,
            ),
            'vonage' => $this->messaging->createVonageProvider(
                $id,
                $name,
                ($options['from'] ?? '') ?: null,
                $credentials['apiKey'] ?? null,
                $credentials['apiSecret'] ?? null,
                $enabled,
            ),
            'fcm' => $this->messaging->createFCMProvider(
                $id,
                $name,
                $credentials['serviceAccountJSON'] ?? null,
                $enabled,
            ),
            'apns' => $this->messaging->createAPNSProvider(
                $id,
                $name,
                $credentials['authKey'] ?? null,
                $credentials['authKeyId'] ?? null,
                $credentials['teamId'] ?? null,
                $credentials['bundleId'] ?? null,
                $options['sandbox'] ?? null,
                $enabled,
            ),
            default => throw new \Exception('Unknown provider: ' . $resource->getProvider()),
        };

        // Resolve providerInternalId for push targets that were written during GROUP_AUTH
        // before the provider existed on the destination.
        $provider = $this->dbForProject->getDocument('providers', $id);
        $targets = $this->dbForProject->find('targets', [
            Query::equal('providerId', [$id]),
            Query::isNull('providerInternalId'),
        ]);

        $userIds = [];

        foreach ($targets as $target) {
            $target->setAttribute('providerInternalId', $provider->getSequence());
            $this->dbForProject->updateDocument('targets', $target->getId(), $target);
            $userIds[$target->getAttribute('userId')] = true;
        }

        foreach (array_keys($userIds) as $userId) {
            $this->dbForProject->purgeCachedDocument('users', $userId);
        }
    }

    /**
     * @throws AppwriteException
     */
    protected function createTopic(Topic $resource): void
    {
        $this->messaging->createTopic(
            $resource->getId(),
            $resource->getTopicName(),
            $resource->getSubscribe(),
        );
    }

    /**
     * @throws AuthorizationException
     * @throws StructureException
     * @throws DatabaseException|\Exception
     */
    protected function createSubscriber(Subscriber $resource): void
    {
        $target = match ($resource->getProviderType()) {
            'push' => $this->dbForProject->getDocument('targets', $resource->getTargetId()),
            'email', 'sms' => $this->dbForProject->findOne('targets', [
                Query::equal('userId', [$resource->getUserId()]),
                Query::equal('providerType', [$resource->getProviderType()]),
            ]),
            default => throw new \Exception('Unknown provider type: ' . $resource->getProviderType()),
        };

        if (!$target || $target->isEmpty()) {
            throw new \Exception('Target not found for subscriber: ' . $resource->getId());
        }

        $topic = $this->dbForProject->getDocument('topics', $resource->getTopicId());
        if ($topic->isEmpty()) {
            throw new \Exception('Topic not found: ' . $resource->getTopicId());
        }

        $user = $this->dbForProject->getDocument('users', $resource->getUserId());
        if ($user->isEmpty()) {
            throw new \Exception('User not found: ' . $resource->getUserId());
        }

        $createdAt = $this->normalizeDateTime($resource->getCreatedAt());
        $updatedAt = $this->normalizeDateTime($resource->getUpdatedAt(), $createdAt);

        $this->dbForProject->createDocument('subscribers', new UtopiaDocument([
            '$id' => $resource->getId(),
            '$createdAt' => $createdAt,
            '$updatedAt' => $updatedAt,
            '$permissions' => [
                Permission::read(Role::user($resource->getUserId())),
                Permission::delete(Role::user($resource->getUserId())),
            ],
            'topicId' => $topic->getId(),
            'topicInternalId' => $topic->getSequence(),
            'targetId' => $target->getId(),
            'targetInternalId' => $target->getSequence(),
            'userId' => $user->getId(),
            'userInternalId' => $user->getSequence(),
            'providerType' => $resource->getProviderType(),
            'search' => implode(' ', [
                $resource->getId(),
                $target->getId(),
                $user->getId(),
                $resource->getProviderType(),
            ]),
        ]));

        $totalAttribute = match ($resource->getProviderType()) {
            'email' => 'emailTotal',
            'sms' => 'smsTotal',
            'push' => 'pushTotal',
            default => throw new \Exception('Unknown provider type: ' . $resource->getProviderType()),
        };

        $this->dbForProject->increaseDocumentAttribute('topics', $resource->getTopicId(), $totalAttribute);
    }

    /**
     * @throws AppwriteException
     * @throws \Exception
     */
    protected function createMessage(Message $resource): void
    {
        $resolvedTargets = $resource->getTargets();
        $status = $resource->getMessageStatus();

        if ($status === 'scheduled') {
            $scheduledAt = $resource->getScheduledAt();

            if (!empty($scheduledAt) && new \DateTime($scheduledAt) > new \DateTime()) {
                $this->createScheduledMessage($resource, $resolvedTargets);

                return;
            }

            $status = 'draft';
        }

        if ($status === 'processing') {
            $status = 'draft';
        }

        $createdAt = $this->normalizeDateTime($resource->getCreatedAt());
        $updatedAt = $this->normalizeDateTime($resource->getUpdatedAt(), $createdAt);

        $data = $resource->getData();
        $searchContent = match ($resource->getProviderType()) {
            'email' => $data['subject'] ?? '',
            'sms' => $data['content'] ?? '',
            'push' => $data['title'] ?? '',
            default => '',
        };

        $this->dbForProject->createDocument('messages', new UtopiaDocument([
            '$id' => $resource->getId(),
            '$createdAt' => $createdAt,
            '$updatedAt' => $updatedAt,
            '$permissions' => [],
            'providerType' => $resource->getProviderType(),
            'topics' => $resource->getTopics(),
            'users' => $resource->getUsers(),
            'targets' => $resolvedTargets,
            'scheduledAt' => null,
            'deliveredAt' => $resource->getDeliveredAt() ?: null,
            'deliveryErrors' => $resource->getDeliveryErrors(),
            'deliveredTotal' => $resource->getDeliveredTotal(),
            'data' => $data,
            'status' => $status,
            'search' => implode(' ', array_filter([
                $resource->getId(),
                $data['description'] ?? '',
                $status,
                $searchContent,
                $resource->getProviderType(),
            ])),
        ]));
    }

    /**
     * @param array<string> $resolvedTargets
     * @throws AppwriteException
     * @throws \Exception
     */
    protected function createScheduledMessage(Message $resource, array $resolvedTargets): void
    {
        $data = $resource->getData();
        $topics = $resource->getTopics() ?: null;
        $users = $resource->getUsers() ?: null;
        $targets = $resolvedTargets ?: null;
        $scheduledAt = $resource->getScheduledAt();

        match ($resource->getProviderType()) {
            'email' => $this->messaging->createEmail(
                $resource->getId(),
                $data['subject'] ?? '',
                $data['content'] ?? '',
                $topics,
                $users,
                $targets,
                $data['cc'] ?? null,
                $data['bcc'] ?? null,
                null,
                false,
                $data['html'] ?? null,
                $scheduledAt,
            ),
            'sms' => $this->messaging->createSMS(
                $resource->getId(),
                $data['content'] ?? '',
                $topics,
                $users,
                $targets,
                false,
                $scheduledAt,
            ),
            'push' => $this->messaging->createPush(
                $resource->getId(),
                $data['title'] ?? null,
                $data['body'] ?? null,
                $topics,
                $users,
                $targets,
                $data['data'] ?? null,
                $data['action'] ?? null,
                $data['image'] ?? null,
                $data['icon'] ?? null,
                $data['sound'] ?? null,
                $data['color'] ?? null,
                $data['tag'] ?? null,
                $data['badge'] ?? null,
                false,
                $scheduledAt,
                $data['contentAvailable'] ?? null,
                $data['critical'] ?? null,
                null,
            ),
            default => throw new \Exception('Unknown provider type: ' . $resource->getProviderType()),
        };
    }

    private function importSiteDeployment(SiteDeployment $deployment): Resource
    {
        $site = $deployment->getSite();

        // Deployment API always creates a new deployment, so unlike other resources
        // there's no duplicate detection. Skip if the parent site wasn't imported successfully.
        if ($site->getStatus() !== Resource::STATUS_SUCCESS) {
            $deployment->setStatus(Resource::STATUS_SKIPPED, 'Parent site "' . $site->getId() . '" failed to import');

            return $deployment;
        }

        $siteId = $site->getId();

        if ($deployment->getSize() <= Transfer::STORAGE_MAX_CHUNK_SIZE) {
            $response = $this->client->call(
                'POST',
                "/sites/{$siteId}/deployments",
                [
                    'content-type' => 'multipart/form-data',
                ],
                [
                    'siteId' => $siteId,
                    'code' => new \CURLFile('data://application/gzip;base64,' . base64_encode($deployment->getData()), 'application/gzip', 'deployment.tar.gz'),
                    'activate' => $deployment->getActivated() ? 'true' : 'false',
                ]
            );

            if (!\is_array($response) || !isset($response['$id'])) {
                throw new \Exception('Site deployment creation failed', Exception::CODE_INTERNAL);
            }

            $deployment->setStatus(Resource::STATUS_SUCCESS);

            return $deployment;
        }

        $response = $this->client->call(
            'POST',
            "/sites/{$siteId}/deployments",
            [
                'content-type' => 'multipart/form-data',
                'content-range' => 'bytes ' . ($deployment->getStart()) . '-' . ($deployment->getEnd() == ($deployment->getSize() - 1) ? $deployment->getSize() : $deployment->getEnd()) . '/' . $deployment->getSize(),
                'x-appwrite-id' => $deployment->getId(),
            ],
            [
                'siteId' => $siteId,
                'code' => new \CURLFile('data://application/gzip;base64,' . base64_encode($deployment->getData()), 'application/gzip', 'deployment.tar.gz'),
                'activate' => $deployment->getActivated() ? 'true' : 'false',
            ]
        );

        if (!\is_array($response) || !isset($response['$id'])) {
            throw new \Exception('Site deployment creation failed', Exception::CODE_INTERNAL);
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
