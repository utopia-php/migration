<?php

namespace Utopia\Transfer\Sources;

use Utopia\Transfer\Source;
use Utopia\Transfer\Resources\Auth\User;
use Utopia\Transfer\Transfer;
use Utopia\Transfer\Resources\Database\Attribute;
use Utopia\Transfer\Resources\Database\Database;
use Utopia\Transfer\Resources\Auth\Hash;
use Utopia\Transfer\Resources\Database\Attributes\BoolAttribute;
use Utopia\Transfer\Resources\Database\Attributes\DateTimeAttribute;
use Utopia\Transfer\Resources\Database\Attributes\FloatAttribute;
use Utopia\Transfer\Resources\Database\Attributes\IntAttribute;
use Utopia\Transfer\Resources\Database\Attributes\StringAttribute;
use Utopia\Transfer\Resources\Database\Collection;
use Utopia\Transfer\Resources\Database\Document;
use Utopia\Transfer\Resources\Database\Index;

class NHost extends Source
{
    /**
     * @var \PDO
     */
    public $pdo;

    /**
     * @var string
     */
    public string $host;

    /**
     * @var string
     */
    public string $databaseName;

    /**
     * @var string
     */
    public string $username;

    /**
     * @var string
     */
    public string $password;

    /**
     * @var string
     */
    public string $port;

    /**
     * Constructor
     *
     * @param string $host
     * @param string $databaseName
     * @param string $username
     * @param string $password
     * @param string $port
     *
     * @return self
     */
    public function __construct(string $host, string $databaseName, string $username, string $password, string $port = '5432')
    {
        $this->host = $host;
        $this->databaseName = $databaseName;
        $this->username = $username;
        $this->password = $password;
        $this->port = $port;

        try {
            $this->pdo = new \PDO("pgsql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->databaseName, $this->username, $this->password);
        } catch (\PDOException $e) {
            throw new \Exception('Failed to connect to database: ' . $e->getMessage());
        }
    }

    public function getName(): string
    {
        return 'NHost';
    }

    public function getSupportedResources(): array
    {
        return [
            Transfer::GROUP_AUTH,
            Transfer::GROUP_DATABASES,
            Transfer::GROUP_DOCUMENTS,
        ];
    }

    /**
     * Export Users
     *
     * @param int $batchSize Max 500
     * @param callable $callback Callback function to be called after each batch, $callback(user[] $batch);
     *
     * @return User[]
     */
    public function exportAuth(int $batchSize, callable $callback): void
    {
        $total = $this->pdo->query('SELECT COUNT(*) FROM auth.users')->fetchColumn();

        $offset = 0;

        while ($offset < $total) {
            $statement = $this->pdo->prepare('SELECT * FROM auth.users order by created_at LIMIT :limit OFFSET :offset');
            $statement->bindValue(':limit', $batchSize, \PDO::PARAM_INT);
            $statement->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $statement->execute();

            $users = $statement->fetchAll(\PDO::FETCH_ASSOC);

            $offset += $batchSize;

            $transferUsers = [];

            foreach ($users as $user) {
                $transferUsers[] = new User(
                    $user['id'],
                    $user['email'] ?? '',
                    $user['display_name'] ?? '',
                    new Hash($user['password_hash'], '', Hash::BCRYPT),
                    $user['phone_number'] ?? '',
                    $this->calculateUserTypes($user),
                    '',
                    $user['email_verified'],
                    $user['phone_number_verified'],
                    $user['disabled'],
                    []
                );
            }

            $callback($transferUsers);
        }
    }

    /**
     * Convert Attribute
     *
     * @param array $column
     * @param Collection $collection
     * @return Attribute
     */
    public function convertAttribute(array $column, Collection $collection): Attribute
    {
        $isArray = $column['data_type'] === 'ARRAY';

        switch ($isArray ? str_replace('_', '', $column['udt_name']) : $column['data_type']) {
                // Numbers
            case 'boolean':
            case 'bool':
                return new BoolAttribute($column['column_name'], $collection, $column['is_nullable'] === 'NO', $isArray, $column['column_default']);
            case 'smallint':
            case 'int2':
                return new IntAttribute($column['column_name'], $collection, $column['is_nullable'] === 'NO', $isArray, $column['column_default'], -32768, 32767);
            case 'integer':
            case 'int4':
                return new IntAttribute($column['column_name'], $collection, $column['is_nullable'] === 'NO', $isArray, $column['column_default'], -2147483648, 2147483647);
            case 'bigint':
            case 'int8':
            case 'numeric':
                return new IntAttribute($column['column_name'], $collection, $column['is_nullable'] === 'NO', $isArray, $column['column_default']);
            case 'decimal':
            case 'real':
            case 'double precision':
            case 'float4':
            case 'float8':
            case 'money':
                return new FloatAttribute($column['column_name'], $collection, $column['is_nullable'] === 'NO', $isArray, $column['column_default']);
                // Time (Conversion happens with documents)
            case 'timestamp with time zone':
            case 'date':
            case 'time with time zone':
            case 'timestamp without time zone':
            case 'timestamptz':
            case 'timestamp':
            case 'time':
            case 'timetz':
            case 'interval':
                return new DateTimeAttribute($column['column_name'], $collection, $column['is_nullable'] === 'NO', $isArray, null);
                break;
                // Strings and Objects
            case 'uuid':
            case 'character varying':
            case 'text':
            case 'character':
            case 'json':
            case 'jsonb':
            case 'varchar':
            case 'bytea':
                return new StringAttribute(
                    $column['column_name'],
                    $collection,
                    $column['is_nullable'] === 'NO',
                    $isArray,
                    $column['column_default'],
                    $column['character_maximum_length'] ?? $column['character_octet_length'] ?? 10485760
                );
                break;
            default:
                // $this->logs[Log::WARNING][] = new Log('Unknown data type: ' . $column['data_type'] . ' for column: ' . $column['column_name'] . ' Falling back to string.', \time()); TODO: Figure out how to deal with warnings
                return new StringAttribute(
                    $column['column_name'],
                    $collection,
                    $column['is_nullable'] === 'NO',
                    $isArray,
                    $column['column_default'],
                    $column['character_maximum_length'] ?? $column['character_octet_length'] ?? 10485760
                );
                break;
        }
    }

    /**
     * Convert Index
     *
     * @param string $table
     * @param Collection $collection
     * @return Index|false
     */
    public function convertIndex(array $index, Collection $collection): Index|false
    {
        $pattern = "/CREATE (?<type>\w+)? INDEX (?<name>\w+) ON (?<table>\w+\.\w+) USING (?<method>\w+) \((?<columns>\w+)\)/";

        if (\preg_match($pattern, $index['indexdef'], $matches)) {
            // We only support BTree indexes
            if ($matches['method'] !== 'btree') {
                //TODO: Figure out how to deal with warnings
                // Add warning here for unsupported index type
                return false;
            }

            $type = '';

            if ($matches['type'] === 'UNIQUE') {
                $type = Index::TYPE_UNIQUE;
            } elseif ($matches['type'] === 'FULLTEXT') {
                $type = Index::TYPE_FULLTEXT;
            } else {
                $type = Index::TYPE_KEY;
            }

            $attributes = [];
            $order = [];

            $targets = explode(",", $matches['columns']);

            foreach ($targets as $target) {
                if (\strpos($target, ' ') !== false) {
                    $target = \explode(' ', $target);
                    $attributes[] = $target[0];
                    $order[] = $target[1];
                } else {
                    $attributes[] = $target;
                    $order[] = "ASC";
                }
            }

            return new Index($matches['name'], $matches['name'], $collection, $type, $attributes, $order);
        } else {
            // $this->logs[Log::ERROR][] = new Log('Skipping index due to unsupported format: ' . $index['indexdef'] . ' for index: ' . $index['indexname'] . '. Transfers only support BTree.', \time());
            // Add error here for unsupported index format

            return false;
        }
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
        $total = $this->pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = \'public\'')->fetchColumn();

        $offset = 0;

        // We'll only transfer the public database for now, since it's the only one that exists by default.
        //TODO: Handle edge cases where there are user created databases and data.

        $transferDatabase = new Database('public', 'public');
        $callback([$transferDatabase]);

        // Transfer Tables
        while ($offset < $total) {
            $statement = $this->pdo->prepare('SELECT table_name FROM information_schema.tables WHERE table_schema = \'public\' order by table_name LIMIT :limit OFFSET :offset');
            $statement->bindValue(':limit', $batchSize, \PDO::PARAM_INT);
            $statement->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $statement->execute();

            $tables = $statement->fetchAll(\PDO::FETCH_ASSOC);

            $offset += $batchSize;

            $transferCollections = [];

            foreach ($tables as $table) {
                $transferCollections[] = new Collection($transferDatabase, $table['table_name'], $table['table_name']);
            }

            $callback($transferCollections);
        }

        // Transfer Attributes and Indexes
        $collections = $this->resourceCache->get(Collection::class);

        foreach ($collections as $collection) {
            /** @var Collection $collection  */
            $statement = $this->pdo->prepare('SELECT * FROM information_schema."columns" where "table_name" = :tableName');
            $statement->bindValue(':tableName', $collection->getCollectionName(), \PDO::PARAM_STR);
            $statement->execute();
            $databaseCollection = $statement->fetchAll(\PDO::FETCH_ASSOC);

            $attributes = [];

            foreach ($databaseCollection as $column) {
                $attributes[] = $this->convertAttribute($column, $collection);
            }

            $callback($attributes);

            // Transfer Indexes
            $indexStatement = $this->pdo->prepare('SELECT indexname, indexdef FROM pg_indexes WHERE tablename = :tableName');
            $indexStatement->bindValue(':tableName', $collection->getCollectionName(), \PDO::PARAM_STR);
            $indexStatement->execute();

            $databaseIndexes = $indexStatement->fetchAll(\PDO::FETCH_ASSOC);
            $indexes = [];
            foreach ($databaseIndexes as $index) {
                $result = $this->convertIndex($index, $collection);

                $indexes[] = $result;
            }

            $callback($indexes);
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
        $databases = $this->resourceCache->get(Database::class);

        foreach ($databases as $database) {
            /** @var Database $database */
            $collections = $database->getCollections();

            foreach ($collections as $collection) {
                $total = $this->pdo->query('SELECT COUNT(*) FROM ' . $collection->getCollectionName())->fetchColumn();

                $offset = 0;

                while ($offset < $total) {
                    $statement = $this->pdo->prepare('SELECT row_to_json(t) FROM (SELECT * FROM ' . $collection->getCollectionName() . ' LIMIT :limit OFFSET :offset) t;');
                    $statement->bindValue(':limit', $batchSize, \PDO::PARAM_INT);
                    $statement->bindValue(':offset', $offset, \PDO::PARAM_INT);
                    $statement->execute();

                    $documents = $statement->fetchAll(\PDO::FETCH_ASSOC);

                    $offset += $batchSize;

                    $transferDocuments = [];

                    $attributes = $this->resourceCache->get(Attribute::class);
                    $collectionAttributes = array_filter($attributes, function (Attribute $attribute) use ($collection) {
                        return $attribute->getId() === $collection->getId();
                    });

                    foreach ($documents as $document) {
                        $data = json_decode($document['row_to_json'], true);

                        $processedData = [];
                        foreach ($collectionAttributes as $attribute) {
                            /** @var Attribute $attribute */
                            if (!$attribute->getArray() && \is_array($data[$attribute->getKey()])) {
                                $processedData[$attribute->getKey()] = json_encode($data[$attribute->getKey()]);
                            } else {
                                $processedData[$attribute->getKey()] = $data[$attribute->getKey()];
                            }
                        }

                        $transferDocuments[] = new Document('unique()', $database, $collection, $processedData);
                    }

                    $callback($transferDocuments);
                }
            }
        }
    }

    private function calculateUserTypes(array $user): array
    {
        if (empty($user['password_hash']) && empty($user['phone_number'])) {
            return [User::TYPE_ANONYMOUS];
        }

        $types = [];

        if (!empty($user['password_hash'])) {
            $types[] = User::TYPE_EMAIL;
        }

        if (!empty($user['phone_number'])) {
            $types[] = User::TYPE_PHONE;
        }

        return $types;
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

        try {
            $this->pdo = new \PDO("pgsql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->databaseName, $this->username, $this->password);
        } catch (\PDOException $e) {
            $report['Databases'][] = 'Failed to connect to database. PDO Code: ' . $e->getCode() . ' Error: ' . $e->getMessage();
        }

        if (!empty($this->pdo->errorCode())) {
            $report['Databases'][] = 'Failed to connect to database. PDO Code: ' . $this->pdo->errorCode() . (empty($this->pdo->errorInfo()[2]) ? '' : ' Error: ' . $this->pdo->errorInfo()[2]);
        }

        foreach ($resources as $resource) {
            switch ($resource) {
                case Transfer::GROUP_AUTH:
                    $statement = $this->pdo->prepare('SELECT COUNT(*) FROM auth.users');
                    $statement->execute();

                    if ($statement->errorCode() !== '00000') {
                        $report['Users'][] = 'Failed to access users table. Error: ' . $statement->errorInfo()[2];
                    }

                    break;
                case Transfer::GROUP_DATABASES:
                    $statement = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = \'public\'');
                    $statement->execute();

                    if ($statement->errorCode() !== '00000') {
                        $report['Databases'][] = 'Failed to access tables table. Error: ' . $statement->errorInfo()[2];
                    }

                    break;
                case Transfer::GROUP_DOCUMENTS:
                    if (!in_array(Transfer::GROUP_DATABASES, $resources)) {
                        $report['Documents'][] = 'Documents resource requires Databases resource to be enabled.';
                    }
            }
        }

        return $report;
    }

    public function exportFiles(int $batchSize, callable $callback): void
    {
        throw new \Exception('Not Implemented');
    }

    public function exportFunctions(int $batchSize, callable $callback): void
    {
        throw new \Exception('Not Implemented');
    }
}
