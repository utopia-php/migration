<?php

namespace Utopia\Migration\Sources;

use PDO;
use Utopia\Migration\Exception;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Auth\Hash;
use Utopia\Migration\Resources\Auth\User;
use Utopia\Migration\Resources\Database\Attribute;
use Utopia\Migration\Resources\Database\Attributes\Boolean;
use Utopia\Migration\Resources\Database\Attributes\DateTime;
use Utopia\Migration\Resources\Database\Attributes\Decimal;
use Utopia\Migration\Resources\Database\Attributes\Integer;
use Utopia\Migration\Resources\Database\Attributes\Text;
use Utopia\Migration\Resources\Database\Collection;
use Utopia\Migration\Resources\Database\Database;
use Utopia\Migration\Resources\Database\Document;
use Utopia\Migration\Resources\Database\Index;
use Utopia\Migration\Resources\Storage\Bucket;
use Utopia\Migration\Resources\Storage\File;
use Utopia\Migration\Source;
use Utopia\Migration\Transfer;

class NHost extends Source
{
    /**
     * @var \PDO
     */
    public $pdo;

    public string $subdomain;

    public string $region;

    public string $databaseName;

    public string $username;

    public string $password;

    public string $port;

    public string $adminSecret;

    public string $storageURL;

    public function __construct(string $subdomain, string $region, string $adminSecret, string $databaseName, string $username, string $password, string $port = '5432')
    {
        $this->subdomain = $subdomain;
        $this->region = $region;
        $this->adminSecret = $adminSecret;
        $this->databaseName = $databaseName;
        $this->username = $username;
        $this->password = $password;
        $this->port = $port;
        $this->storageURL = "https://{$this->subdomain}.storage.{$this->region}.nhost.run";
    }

    public function getDatabase(): PDO
    {
        if (! $this->pdo) {
            try {
                $this->pdo = new \PDO('pgsql:host='.$this->subdomain.'.db.'.$this->region.'.nhost.run'.';port='.$this->port.';dbname='.$this->databaseName, $this->username, $this->password);
            } catch (\PDOException $e) {
                throw new \Exception('Failed to connect to database: '.$e->getMessage());
            }
        }

        return $this->pdo;
    }

    public static function getName(): string
    {
        return 'NHost';
    }

    public static function getSupportedResources(): array
    {
        return [
            // Auth
            Resource::TYPE_USER,

            // Database
            Resource::TYPE_DATABASE,
            Resource::TYPE_COLLECTION,
            Resource::TYPE_ATTRIBUTE,
            Resource::TYPE_INDEX,
            Resource::TYPE_DOCUMENT,

            // Storage
            Resource::TYPE_BUCKET,
            Resource::TYPE_FILE,
        ];
    }

    public function report(array $resources = []): array
    {
        $report = [];

        if (empty($resources)) {
            $resources = $this->getSupportedResources();
        }

        try {
            $db = $this->getDatabase();
        } catch (\PDOException $e) {
            throw new \Exception('Failed to connect to database. PDO Code: '.$e->getCode().' Error: '.$e->getMessage());
        }

        if (! empty($db->errorCode())) {
            throw new \Exception('Failed to connect to database. PDO Code: '.$db->errorCode().(empty($db->errorInfo()[2]) ? '' : ' Error: '.$db->errorInfo()[2]));
        }

        // Auth
        if (\in_array(Resource::TYPE_USER, $resources)) {
            $statement = $db->prepare('SELECT COUNT(*) FROM auth.users');
            $statement->execute();

            if ($statement->errorCode() !== '00000') {
                throw new \Exception('Failed to access users table. Error: '.$statement->errorInfo()[2]);
            }

            $report[Resource::TYPE_USER] = $statement->fetchColumn();
        }

        // Databases
        if (\in_array(Resource::TYPE_DATABASE, $resources)) {
            $report[Resource::TYPE_DATABASE] = 1;
        }

        if (\in_array(Resource::TYPE_COLLECTION, $resources)) {
            $statement = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = \'public\'');
            $statement->execute();

            if ($statement->errorCode() !== '00000') {
                throw new \Exception('Failed to access tables table. Error: '.$statement->errorInfo()[2]);
            }

            $report[Resource::TYPE_COLLECTION] = $statement->fetchColumn();
        }

        if (\in_array(Resource::TYPE_ATTRIBUTE, $resources)) {
            $statement = $db->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = \'public\'');
            $statement->execute();

            if ($statement->errorCode() !== '00000') {
                throw new \Exception('Failed to access columns table. Error: '.$statement->errorInfo()[2]);
            }

            $report[Resource::TYPE_ATTRIBUTE] = $statement->fetchColumn();
        }

        if (\in_array(Resource::TYPE_INDEX, $resources)) {
            $statement = $db->prepare('SELECT COUNT(*) FROM pg_indexes WHERE schemaname = \'public\'');
            $statement->execute();

            if ($statement->errorCode() !== '00000') {
                throw new \Exception('Failed to access indexes table. Error: '.$statement->errorInfo()[2]);
            }

            $report[Resource::TYPE_INDEX] = $statement->fetchColumn();
        }

        if (\in_array(Resource::TYPE_DOCUMENT, $resources)) {
            $statement = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = \'public\'');
            $statement->execute();

            if ($statement->errorCode() !== '00000') {
                throw new \Exception('Failed to access tables table. Error: '.$statement->errorInfo()[2]);
            }

            $report[Resource::TYPE_DOCUMENT] = $statement->fetchColumn();
        }

        // Storage
        if (\in_array(Resource::TYPE_BUCKET, $resources)) {
            $statement = $db->prepare('SELECT COUNT(*) FROM storage.buckets');
            $statement->execute();

            if ($statement->errorCode() !== '00000') {
                throw new \Exception('Failed to access buckets table. Error: '.$statement->errorInfo()[2]);
            }

            $report[Resource::TYPE_BUCKET] = $statement->fetchColumn();
        }

        if (\in_array(Resource::TYPE_FILE, $resources)) {
            $statement = $db->prepare('SELECT COUNT(*) FROM storage.files');
            $statement->execute();

            if ($statement->errorCode() !== '00000') {
                throw new \Exception('Failed to access files table. Error: '.$statement->errorInfo()[2]);
            }

            $report[Resource::TYPE_FILE] = $statement->fetchColumn();

            $statement = $db->prepare('SELECT SUM(storage.files."size") from storage.files;');
            $statement->execute();

            if ($statement->errorCode() !== '00000') {
                throw new \Exception('Failed to access files table. Error: '.$statement->errorInfo()[2]);
            }

            $report['size'] = ($statement->fetchColumn()) / 1024 / 1024; // MB;
        }

        $this->previousReport = $report;

        return $report;
    }

    protected function exportGroupAuth(int $batchSize, array $resources): void
    {
        try {
            if (\in_array(Resource::TYPE_USER, $resources)) {
                $this->exportUsers($batchSize);
            }
        } catch (\Throwable $e) {
            $this->addError(new Exception(
                Resource::TYPE_USER,
                $e->getMessage()
            ));
        }
    }

    private function exportUsers(int $batchSize): void
    {
        $db = $this->getDatabase();

        $total = $db->query('SELECT COUNT(*) FROM auth.users')->fetchColumn();

        $offset = 0;

        while ($offset < $total) {
            $statement = $db->prepare('SELECT * FROM auth.users order by created_at LIMIT :limit OFFSET :offset');
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
                    new Hash($user['password_hash'], '', Hash::ALGORITHM_BCRYPT),
                    $user['phone_number'] ?? '',
                    $this->calculateUserTypes($user),
                    [],
                    '',
                    $user['email_verified'] ?? false,
                    $user['phone_number_verified'] ?? false,
                    $user['disabled'] ?? false,
                    []
                );
            }

            $this->callback($transferUsers);
        }
    }

    protected function exportGroupDatabases(int $batchSize, array $resources): void
    {
        try {
            if (\in_array(Resource::TYPE_DATABASE, $resources)) {
                $this->exportDatabases($batchSize);
            }
        } catch (\Throwable $e) {
            $this->addError(
                new Exception(
                    Resource::TYPE_DATABASE,
                    $e->getMessage()
                )
            );
        }

        try {
            if (\in_array(Resource::TYPE_COLLECTION, $resources)) {
                $this->exportCollections($batchSize);
            }
        } catch (\Throwable $e) {
            $this->addError(
                new Exception(
                    Resource::TYPE_COLLECTION,
                    $e->getMessage()
                )
            );
        }

        try {
            if (\in_array(Resource::TYPE_ATTRIBUTE, $resources)) {
                $this->exportAttributes($batchSize);
            }
        } catch (\Throwable $e) {
            $this->addError(
                new Exception(
                    Resource::TYPE_ATTRIBUTE,
                    $e->getMessage()
                )
            );
        }

        try {
            if (\in_array(Resource::TYPE_DOCUMENT, $resources)) {
                $this->exportDocuments($batchSize);
            }
        } catch (\Throwable $e) {
            $this->addError(
                new Exception(
                    Resource::TYPE_DOCUMENT,
                    $e->getMessage()
                )
            );
        }

        try {
            if (\in_array(Resource::TYPE_INDEX, $resources)) {
                $this->exportIndexes($batchSize);
            }
        } catch (\Throwable $e) {
            $this->addError(
                new Exception(
                    Resource::TYPE_INDEX,
                    $e->getMessage()
                )
            );
        }
    }

    private function exportDatabases(int $batchSize): void
    {
        // We'll only transfer the public database for now, since it's the only one that exists by default.
        //TODO: Handle edge cases where there are user created databases and data.
        $transferDatabase = new Database('public', 'public');
        $this->callback([$transferDatabase]);
    }

    private function exportCollections(int $batchSize): void
    {
        $databases = $this->cache->get(Database::getName());
        $db = $this->getDatabase();

        foreach ($databases as $database) {
            /** @var Database $database */
            $statement = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :database');
            $statement->execute([':database' => $database->getId()]);
            $total = $statement->fetchColumn(0);

            $offset = 0;

            while ($offset < $total) {
                $statement = $db->prepare('SELECT table_name FROM information_schema.tables WHERE table_schema = :database order by table_name LIMIT :limit OFFSET :offset');
                $statement->execute([':limit' => $batchSize, ':offset' => $offset, ':database' => $database->getId()]);

                $tables = $statement->fetchAll(\PDO::FETCH_ASSOC);

                $offset += $batchSize;

                $transferCollections = [];

                foreach ($tables as $table) {
                    $transferCollections[] = new Collection($database, $table['table_name'], $table['table_name']);
                }

                $this->callback($transferCollections);
            }
        }
    }

    private function exportAttributes(int $batchSize): void
    {
        $collections = $this->cache->get(Collection::getName());
        $db = $this->getDatabase();

        foreach ($collections as $collection) {
            /** @var Collection $collection */
            $statement = $db->prepare('SELECT * FROM information_schema."columns" where "table_name" = :tableName');
            $statement->bindValue(':tableName', $collection->getCollectionName(), \PDO::PARAM_STR);
            $statement->execute();
            $databaseCollection = $statement->fetchAll(\PDO::FETCH_ASSOC);

            $attributes = [];

            foreach ($databaseCollection as $column) {
                $attributes[] = $this->convertAttribute($column, $collection);
            }

            $this->callback($attributes);
        }
    }

    private function exportIndexes(int $batchSize): void
    {
        $collections = $this->cache->get(Collection::getName());
        $db = $this->getDatabase();

        foreach ($collections as $collection) {
            /** @var Collection $collection */
            $indexStatement = $db->prepare('SELECT indexname, indexdef FROM pg_indexes WHERE tablename = :tableName');
            $indexStatement->bindValue(':tableName', $collection->getCollectionName(), \PDO::PARAM_STR);
            $indexStatement->execute();

            $databaseIndexes = $indexStatement->fetchAll(\PDO::FETCH_ASSOC);
            $indexes = [];
            foreach ($databaseIndexes as $index) {
                $result = $this->convertIndex($index, $collection);

                if ($result) {
                    $indexes[] = $result;
                }
            }

            $this->callback($indexes);
        }
    }

    private function exportDocuments(int $batchSize): void
    {
        $databases = $this->cache->get(Database::getName());
        $collections = $this->cache->get(Collection::getName());
        $db = $this->getDatabase();

        foreach ($databases as $database) {
            /** @var Database $database */
            $collections = array_filter($collections, function (Collection $collection) use ($database) {
                return $collection->getDatabase()->getId() === $database->getId();
            });

            foreach ($collections as $collection) {
                /** @var Collection $collection */
                $total = $db->query('SELECT COUNT(*) FROM '.$collection->getDatabase()->getDBName().'."'.$collection->getCollectionName().'"')->fetchColumn();

                $offset = 0;

                while ($offset < $total) {
                    $statement = $db->prepare('SELECT row_to_json(t) FROM (SELECT * FROM '.$collection->getDatabase()->getDBName().'."'.$collection->getCollectionName().'" LIMIT :limit OFFSET :offset) t;');
                    $statement->bindValue(':limit', $batchSize, \PDO::PARAM_INT);
                    $statement->bindValue(':offset', $offset, \PDO::PARAM_INT);
                    $statement->execute();

                    $documents = $statement->fetchAll(\PDO::FETCH_ASSOC);

                    $offset += $batchSize;

                    $transferDocuments = [];

                    $attributes = $this->cache->get(Attribute::getName());
                    $collectionAttributes = array_filter($attributes, function (Attribute $attribute) use ($collection) {
                        return $attribute->getCollection()->getId() === $collection->getId();
                    });

                    foreach ($documents as $document) {
                        $data = json_decode($document['row_to_json'], true);

                        $processedData = [];
                        foreach ($collectionAttributes as $attribute) {
                            /** @var Attribute $attribute */
                            if (! $attribute->getArray() && \is_array($data[$attribute->getKey()])) {
                                $processedData[$attribute->getKey()] = json_encode($data[$attribute->getKey()]);
                            } else {
                                $processedData[$attribute->getKey()] = $data[$attribute->getKey()];
                            }
                        }

                        $transferDocuments[] = new Document('unique()', $database, $collection, $processedData);
                    }

                    $this->callback($transferDocuments);
                }
            }
        }
    }

    private function convertAttribute(array $column, Collection $collection): Attribute
    {
        $isArray = $column['data_type'] === 'ARRAY';

        switch ($isArray ? str_replace('_', '', $column['udt_name']) : $column['data_type']) {
            // Numbers
            case 'boolean':
            case 'bool':
                return new Boolean($column['column_name'], $collection, $column['is_nullable'] === 'NO', $isArray, $column['column_default']);
            case 'smallint':
            case 'int2':
                return new Integer($column['column_name'], $collection, $column['is_nullable'] === 'NO', $isArray, $column['column_default'], -32768, 32767);
            case 'integer':
            case 'int4':
                return new Integer($column['column_name'], $collection, $column['is_nullable'] === 'NO', $isArray, $column['column_default'], -2147483648, 2147483647);
            case 'bigint':
            case 'int8':
            case 'numeric':
                return new Integer($column['column_name'], $collection, $column['is_nullable'] === 'NO', $isArray, $column['column_default']);
            case 'decimal':
            case 'real':
            case 'double precision':
            case 'float4':
            case 'float8':
            case 'money':
                return new Decimal($column['column_name'], $collection, $column['is_nullable'] === 'NO', $isArray, $column['column_default']);
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
                return new DateTime($column['column_name'], $collection, $column['is_nullable'] === 'NO', $isArray, null);
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
                return new Text(
                    $column['column_name'],
                    $collection,
                    $column['is_nullable'] === 'NO',
                    $isArray,
                    $column['column_default'],
                    $column['character_maximum_length'] ?? $column['character_octet_length'] ?? 10485760
                );
                break;
            default:
                return new Text(
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

    private function convertIndex(array $index, Collection $collection): Index|false
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

            $targets = explode(',', $matches['columns']);

            foreach ($targets as $target) {
                if (\strpos($target, ' ') !== false) {
                    $target = \explode(' ', $target);
                    $attributes[] = $target[0];
                    $order[] = $target[1];
                } else {
                    $attributes[] = $target;
                    $order[] = 'ASC';
                }
            }

            return new Index($matches['name'], $matches['name'], $collection, $type, $attributes, $order);
        } else {
            return false;
        }
    }

    private function calculateUserTypes(array $user): array
    {
        if (empty($user['password_hash']) && empty($user['phone_number'])) {
            return [User::TYPE_ANONYMOUS];
        }

        $types = [];

        if (! empty($user['password_hash'])) {
            $types[] = User::TYPE_PASSWORD;
        }

        if (! empty($user['phone_number'])) {
            $types[] = User::TYPE_PHONE;
        }

        return $types;
    }

    protected function exportGroupStorage(int $batchSize, array $resources): void
    {
        try {
            if (\in_array(Resource::TYPE_BUCKET, $resources)) {
                $this->exportBuckets($batchSize);
            }
        } catch (\Throwable $e) {
            $this->addError(
                new Exception(
                    Resource::TYPE_BUCKET,
                    $e->getMessage()
                )
            );
        }

        try {
            if (\in_array(Resource::TYPE_FILE, $resources)) {
                $this->exportFiles($batchSize);
            }
        } catch (\Throwable $e) {
            $this->addError(
                new Exception(
                    Resource::TYPE_FILE,
                    $e->getMessage()
                )
            );
        }
    }

    protected function exportBuckets(int $batchSize): void
    {
        $db = $this->getDatabase();
        $total = $db->query('SELECT COUNT(*) FROM storage.buckets')->fetchColumn();

        $offset = 0;

        while ($offset < $total) {
            $statement = $db->prepare('SELECT * FROM storage.buckets order by created_at LIMIT :limit OFFSET :offset');
            $statement->bindValue(':limit', $batchSize, \PDO::PARAM_INT);
            $statement->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $statement->execute();

            $buckets = $statement->fetchAll(\PDO::FETCH_ASSOC);

            $offset += $batchSize;

            $transferBuckets = [];

            foreach ($buckets as $bucket) {
                $transferBuckets[] = new Bucket(
                    $bucket['id'],
                    $bucket['id']
                ); //TODO: To add file_size transfer then we need to be able to see the destination's limit on files.
            }

            $this->callback($transferBuckets);
        }
    }

    private function exportFiles(int $batchSize): void
    {
        $buckets = $this->cache->get(Bucket::getName());
        $db = $this->getDatabase();

        foreach ($buckets as $bucket) {
            /** @var Bucket $bucket */
            $totalStatement = $db->prepare('SELECT COUNT(*) FROM storage.files WHERE bucket_id=:bucketId');
            $totalStatement->execute([':bucketId' => $bucket->getId()]);
            $total = $totalStatement->fetchColumn();

            $offset = 0;
            while ($offset < $total) {
                $statement = $db->prepare('SELECT * FROM storage.files WHERE bucket_id=:bucketId order by created_at LIMIT :limit OFFSET :offset');
                $statement->bindValue(':limit', $batchSize, \PDO::PARAM_INT);
                $statement->bindValue(':offset', $offset, \PDO::PARAM_INT);
                $statement->bindValue(':bucketId', $bucket->getId(), \PDO::PARAM_STR);
                $statement->execute();

                $files = $statement->fetchAll(\PDO::FETCH_ASSOC);

                $offset += $batchSize;

                foreach ($files as $file) {
                    $this->exportFile(new File(
                        $file['id'],
                        $bucket,
                        $file['name'],
                        '',
                        $file['mime_type'],
                        [],
                        $file['size'],
                    ));
                }
            }
        }
    }

    private function exportFile(File $file): void
    {
        $start = 0;
        $end = Transfer::STORAGE_MAX_CHUNK_SIZE - 1;

        $fileSize = $file->getSize();
        $response = $this->call('GET', $this->storageURL."/v1/files/{$file->getId()}/presignedurl", [
            'X-Hasura-Admin-Secret' => $this->adminSecret,
        ]);

        $fileUrl = $response['url'];
        $refreshTime = \time() + $response['expiration'];

        if ($end > $fileSize) {
            $end = $fileSize - 1;
        }

        while ($start < $fileSize) {
            if (\time() > $refreshTime) {
                $response = $this->call('GET', "/v1/files/{$file->getId()}/presignedurl", [
                    'X-Hasura-Admin-Secret' => $this->adminSecret,
                ]);

                $fileUrl = $response['url'];
                $refreshTime = \time() + $response['expiration'];
            }

            $chunkData = $this->call(
                'GET',
                $fileUrl,
                ['range' => "bytes=$start-$end"]
            );

            $file->setData($chunkData)
                ->setStart($start)
                ->setEnd($end);

            $this->callback([$file]);

            $start += Transfer::STORAGE_MAX_CHUNK_SIZE;
            $end += Transfer::STORAGE_MAX_CHUNK_SIZE;

            if ($end > $fileSize) {
                $end = $fileSize - 1;
            }
        }
    }

    protected function exportGroupFunctions(int $batchSize, array $resources)
    {
        throw new \Exception('Not Implemented');
    }
}
