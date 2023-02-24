<?php

namespace Utopia\Transfer\Sources;

use Utopia\Transfer\Source;
use Appwrite\Client;
use Appwrite\Query;
use Appwrite\Services\Databases;
use Appwrite\Services\Users;
use Utopia\Transfer\Resources\Attribute;
use Utopia\Transfer\Resources\Project;
use Utopia\Transfer\Resources\User;
use Utopia\Transfer\Transfer;
use Utopia\Transfer\Log;
use Utopia\Transfer\Resource;
use Utopia\Transfer\Resources\Attributes\BoolAttribute;
use Utopia\Transfer\Resources\Attributes\DateTimeAttribute;
use Utopia\Transfer\Resources\Attributes\EmailAttribute;
use Utopia\Transfer\Resources\Attributes\EnumAttribute;
use Utopia\Transfer\Resources\Attributes\FloatAttribute;
use Utopia\Transfer\Resources\Attributes\IntAttribute;
use Utopia\Transfer\Resources\Attributes\IPAttribute;
use Utopia\Transfer\Resources\Attributes\StringAttribute;
use Utopia\Transfer\Resources\Attributes\URLAttribute;
use Utopia\Transfer\Resources\Collection;
use Utopia\Transfer\Resources\Database;
use Utopia\Transfer\Resources\Hash;
use Utopia\Transfer\Resources\Index;

class Appwrite extends Source
{
    /**
     * @var Client|null
     */
    protected $client = null;

    /**
     * Constructor
     * 
     * @param string $project
     * @param string $endpoint
     * @param string $key
     * 
     * @return self
     */
    function __construct(string $project, string $endpoint, string $key)
    {
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
            Transfer::RESOURCE_USERS,
            Transfer::RESOURCE_DATABASES
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
        // We want to make sure the API key has the correct permissions.

        foreach ($resources as $resource) {
            switch ($resource) {
                case Transfer::RESOURCE_DATABASES:
                    $databases = new Databases($this->client);
                    try {
                        $databases->list();
                    } catch (\Throwable $e) {
                        if ($e->getCode() !== 403) {
                            $report['Databases'][] = 'API Key is missing scope: databases.read';
                        }
                    }
                    break;
                case Transfer::RESOURCE_USERS:
                    $auth = new Users($this->client);
                    try {
                        $auth->list();
                    } catch (\Throwable $e) {
                        if ($e->getCode() !== 403) {
                            $report['Users'][] = 'API Key is missing scope: users.read';
                        }
                    }
                    break;
                case Transfer::RESOURCE_DOCUMENTS:
                    $databases = new Databases($this->client);
                    try {
                        $databases->list();
                    } catch (\Throwable $e) {
                        if ($e->getCode() !== 403) {
                            $report['Databases'][] = 'API Key is missing scope: databases.read';
                        }
                    }

                    try {
                        $databases->create('', '');
                    } catch (\Throwable $e) {
                        if ($e->getCode() !== 403) {
                            $report['Databases'][] = 'API Key is missing scope: databases.write';
                        }
                    }

                    try {
                        $databases->listCollections('', [], '');
                    } catch (\Throwable $e) {
                        if ($e->getCode() !== 403) {
                            $report['Databases'][] = 'API Key is missing scope: collections.write';
                        }
                    }

                    try {
                        $databases->createCollection('', '', '', []);
                    } catch (\Throwable $e) {
                        if ($e->getCode() !== 403) {
                            $report['Databases'][] = 'API Key is missing scope: collections.write';
                        }
                    }

                    try {
                        $databases->listDocuments('', '', []);
                    } catch (\Throwable $e) {
                        if ($e->getCode() !== 403) {
                            $report['Databases'][] = 'API Key is missing scope: documents.write';
                        }
                    }

                    try {
                        $databases->createDocument('', '', '', [], []);
                    } catch (\Throwable $e) {
                        if ($e->getCode() !== 403) {
                            $report['Documents'][] = 'API Key is missing scope: documents.write';
                        }
                    }

                    try {
                        $databases->listIndexes('', '');
                    } catch (\Throwable $e) {
                        if ($e->getCode() !== 403) {
                            $report['Databases'][] = 'API Key is missing scope: indexes.read';
                        }
                    }

                    try {
                        $databases->createIndex('', '', '', '', [], []);
                    } catch (\Throwable $e) {
                        if ($e->getCode() !== 403) {
                            $report['Databases'][] = 'API Key is missing scope: indexes.write';
                        }
                    }

                    try {
                        $databases->listAttributes('', '');
                    } catch (\Throwable $e) {
                        if ($e->getCode() !== 403) {
                            $report['Databases'][] = 'API Key is missing scope: attributes.read';
                        }
                    }

                    try {
                        $databases->createStringAttribute('', '', '', 0, false, false);
                    } catch (\Throwable $e) {
                        if ($e->getCode() !== 403) {
                            $report['Databases'][] = 'API Key is missing scope: attributes.write';
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

            $queries = [
                Query::limit($batchSize)
            ];

            if ($lastDocument) {
                $queries[] = Query::cursorAfter($lastDocument);
            }

            $response = $usersClient->list($queries);

            foreach ($response['users'] as $user) {
                $users[] = new User(
                    $user['$id'],
                    $user['email'],
                    $user['name'],
                    new Hash($user['password'], $user['hash']),
                    $user['phone'],
                    $this->calculateTypes($user),
                    '',
                    $user['emailVerification'],
                    $user['phoneVerification'],
                    !$user['status'],
                    $user['prefs']
                );

                $lastDocument = $user['$id'];
            }

            $callback($users);

            if (count($users) < $batchSize) {
                break;
            }
        }
    }

    function convertAttribute(array $value): Attribute
    {
        switch ($value['type']) {
            case 'string': {
                    if (!isset($value['format']))
                        return new StringAttribute($value['key'], $value['required'], $value['array'], $value['default'], $value['size'] ?? 0);

                    switch ($value['format']) {
                        case 'email':
                            return new EmailAttribute($value['key'], $value['required'], $value['array'], $value['default']);
                        case 'enum':
                            return new EnumAttribute($value['key'], $value['elements'], $value['required'], $value['array'], $value['default']);
                        case 'url':
                            return new URLAttribute($value['key'], $value['required'], $value['array'], $value['default']);
                        case 'ip':
                            return new IPAttribute($value['key'], $value['required'], $value['array'], $value['default']);
                        case 'datetime':
                            return new DateTimeAttribute($value['key'], $value['required'], $value['array'], $value['default']);
                        default:
                            return new StringAttribute($value['key'], $value['required'], $value['array'], $value['default'], $value['size'] ?? 0);
                    }
                }
            case 'boolean':
                return new BoolAttribute($value['key'], $value['required'], $value['array'], $value['default']);
            case 'integer':
                return new IntAttribute($value['key'], $value['required'], $value['array'], $value['default'], $value['min'] ?? 0, $value['max'] ?? 0);
            case 'double':
                return new FloatAttribute($value['key'], $value['required'], $value['array'], $value['default'], $value['min'] ?? 0, $value['max'] ?? 0);
        }

        throw new \Exception('Unknown attribute type: ' . $value['type']);
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
            $queries = [
                Query::limit($batchSize)
            ];

            $databases = [];

            if ($lastDocument) {
                $queries[] = Query::cursorAfter($lastDocument);
            }

            $response = $databaseClient->list($queries);

            foreach ($response["databases"] as $database) {
                $newDatabase = new Database($database['name'], $database['$id']);

                $collections = $databaseClient->listCollections($database['$id']);

                $generalCollections = [];
                foreach ($collections['collections'] as $collection) {
                    $newCollection = new Collection($collection['name'], $collection['$id']);

                    $attributes = [];
                    $indexes = [];

                    foreach ($collection['attributes'] as $attribute) {
                        $attributes[] = $this->convertAttribute($attribute);
                    }

                    foreach ($collection['indexes'] as $index) {
                        $indexes[] = new Index($index['key'], $index['type'], $index['attributes'], $index['orders']);
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
     * Calculate Types
     * 
     * @param array $user
     * 
     * @return array
     */
    protected function calculateTypes(array $user): array
    {
        if (empty($user['email']) && empty($user['phone'])) {
            return [User::TYPE_ANONYMOUS];
        }

        $types = [];

        if (!empty($user['email'])) {
            $types[] = User::TYPE_EMAIL;
        }

        if (!empty($user['phone'])) {
            $types[] = User::TYPE_PHONE;
        }

        return $types;
    }
}
