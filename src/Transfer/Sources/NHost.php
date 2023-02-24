<?php

namespace Utopia\Transfer\Sources;

use Utopia\Transfer\Source;
use Utopia\Transfer\Resources\User;
use Utopia\Transfer\Transfer;
use Utopia\Transfer\Log;
use Utopia\Transfer\Resources\Attribute;
use Utopia\Transfer\Resources\Database;
use Utopia\Transfer\Resources\Hash;
use Utopia\Transfer\Resources\Attributes\BoolAttribute;
use Utopia\Transfer\Resources\Attributes\DateTimeAttribute;
use Utopia\Transfer\Resources\Attributes\FloatAttribute;
use Utopia\Transfer\Resources\Attributes\IntAttribute;
use Utopia\Transfer\Resources\Attributes\StringAttribute;
use Utopia\Transfer\Resources\Collection;

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
    function __construct(string $host, string $databaseName, string $username, string $password, string $port = '5432')
    {
        $this->host = $host;
        $this->databaseName = $databaseName;
        $this->username = $username;
        $this->password = $password;
        $this->port = $port;
        $this->pdo = new \PDO("pgsql:host=".$this->host.";port=".$this->port.";dbname=".$this->databaseName, $this->username, $this->password);
    }

    function getName(): string
    {
        return 'NHost';
    }

    function getSupportedResources(): array
    {
        return [
            Transfer::RESOURCE_USERS,
            Transfer::RESOURCE_DATABASES
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
    public function exportUsers(int $batchSize, callable $callback): void
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
     * Convert Collection
     * 
     * @param string $tableName
     * @return Collection
     */
    public function convertCollection(string $tableName): Collection
    {
        $statement = $this->pdo->prepare('SELECT * FROM information_schema."columns" where "table_name" = :tableName');
        $statement->bindValue(':tableName', $tableName, \PDO::PARAM_STR);
        $statement->execute();
        $databaseCollection = $statement->fetchAll(\PDO::FETCH_ASSOC);

        $convertedCollection = new Collection($tableName, $tableName);

        $attributes = [];

        foreach ($databaseCollection as $column) {
            $attributes[] = $this->convertAttribute($column);
        }
        $convertedCollection->setAttributes($attributes);

        return $convertedCollection;
    }

    /**
     * Convert Attribute
     * 
     * @param array $column
     * @return Attribute
     */
    public function convertAttribute(array $column): Attribute
    {
        $isArray = $column['data_type'] === 'ARRAY';

        switch ($isArray ? str_replace('_', '', $column['udt_name']) : $column['data_type']) {
                // Numbers
            case 'boolean':
            case 'bool':
                return new BoolAttribute($column['column_name'], $column['is_nullable'] === 'NO', $isArray, $column['column_default']);
            case 'smallint':
            case 'int2':
                return new IntAttribute($column['column_name'], $column['is_nullable'] === 'NO', $isArray, $column['column_default'], -32768, 32767);
            case 'integer':
            case 'int4':
                return new IntAttribute($column['column_name'], $column['is_nullable'] === 'NO', $isArray, $column['column_default'], -2147483648, 2147483647);
            case 'bigint':
            case 'int8':
            case 'numeric':
                return new IntAttribute($column['column_name'], $column['is_nullable'] === 'NO', $isArray, $column['column_default']);
            case 'decimal':
            case 'real':
            case 'double precision':
            case 'float4':
            case 'float8':
            case 'money':
                return new FloatAttribute($column['column_name'], $column['is_nullable'] === 'NO', $isArray, $column['column_default']);
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
                return new DateTimeAttribute($column['column_name'], $column['is_nullable'] === 'NO', $isArray, ($column['column_default'] === 'now()') ? 'now()' : ($column['column_default'] ?? null));
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
                    $column['is_nullable'] === 'NO',
                    $isArray,
                    $column['column_default'],
                    $column['character_maximum_length'] ?? $column['character_octet_length'] ?? 10485760
                );
                break;
            default: {
                    $this->logs[Log::WARNING][] = new Log('Unknown data type: ' . $column['data_type'] . ' for column: ' . $column['column_name'] . ' Falling back to string.', \time());
                    return new StringAttribute(
                        $column['column_name'],
                        $column['is_nullable'] === 'NO',
                        $isArray,
                        $column['column_default'],
                        $column['character_maximum_length'] ?? $column['character_octet_length'] ?? 10485760
                    );
                    break;
                }
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
        //TODO: Handle edge cases where there are user created databases.

        $transferDatabase = new Database('public', 'public');

        while ($offset < $total) {
            $statement = $this->pdo->prepare('SELECT table_name FROM information_schema.tables WHERE table_schema = \'public\' order by table_name LIMIT :limit OFFSET :offset');
            $statement->bindValue(':limit', $batchSize, \PDO::PARAM_INT);
            $statement->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $statement->execute();

            $tables = $statement->fetchAll(\PDO::FETCH_ASSOC);

            $offset += $batchSize;

            $transferCollections = [];

            foreach ($tables as $table) {
                $transferCollections[] = $this->convertCollection($table['table_name']);
            }

            $transferDatabase->setCollections($transferCollections);
        }

        $callback([$transferDatabase]);
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
            'Users' => true,
            'Databases' => true,
            'Documents' => true,
            'Files' => true,
            'Functions' => true
        ];

        if (empty($resources)) {
            $resources = $this->getSupportedResources();
        }

        if ($this->pdo->errorCode() !== '00000') {
            $report['Databases'][] = 'Failed to connect to database. Error: ' . $this->pdo->errorInfo()[2];
        }

        foreach ($resources as $resource) {
            switch ($resource) {
                case Transfer::RESOURCE_USERS:
                    $statement = $this->pdo->prepare('SELECT COUNT(*) FROM auth.users');
                    $statement->execute();

                    if ($statement->errorCode() !== '00000') {
                        $report['Users'][] = 'Failed to access users table. Error: ' . $statement->errorInfo()[2];
                    }

                    break;
                case Transfer::RESOURCE_DATABASES:
                    $statement = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = \'public\'');
                    $statement->execute();

                    if ($statement->errorCode() !== '00000') {
                        $report['Databases'][] = 'Failed to access tables table. Error: ' . $statement->errorInfo()[2];
                    }

                    break;
            }
        }

        return $report;
    }
}
