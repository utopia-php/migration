<?php

namespace Utopia\Transfer\Destinations;

use Appwrite\AppwriteException;
use Appwrite\Client;
use Appwrite\Services\Users;
use Appwrite\Services\Databases as DatabasesService;
use Utopia\Transfer\Destination;
use Utopia\Transfer\Resources\Hash;
use Utopia\Transfer\Log;
use Utopia\Transfer\Progress;
use Utopia\Transfer\Resources\Attribute;
use Utopia\Transfer\Resources\User;
use Utopia\Transfer\Transfer;
use Utopia\Transfer\Resources\Database;
use Utopia\Transfer\Resources\Collection;
use Utopia\Transfer\Resources\Attributes\BoolAttribute;
use Utopia\Transfer\Resources\Attributes\DateTimeAttribute;
use Utopia\Transfer\Resources\Attributes\EmailAttribute;
use Utopia\Transfer\Resources\Attributes\EnumAttribute;
use Utopia\Transfer\Resources\Attributes\FloatAttribute;
use Utopia\Transfer\Resources\Attributes\IntAttribute;
use Utopia\Transfer\Resources\Attributes\IPAttribute;
use Utopia\Transfer\Resources\Attributes\StringAttribute;
use Utopia\Transfer\Resources\Attributes\URLAttribute;
use Utopia\Transfer\Resources\Index;

class Appwrite extends Destination
{
    protected Client $client;

    protected string $project;
    protected string $endpoint;
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
            Transfer::RESOURCE_USERS,
            Transfer::RESOURCE_DATABASES,
            Transfer::RESOURCE_DOCUMENTS,
            Transfer::RESOURCE_FILES,
            Transfer::RESOURCE_FUNCTIONS
        ];
    }

    public function check(array $resources = []): bool
    {
        $auth = new Users($this->client);

        try {
            $auth->list();
        } catch (\Exception $e) {
            $this->logs[Log::ERROR] = new Log($e->getMessage());
            return false;
        }

        return true;
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

    public function importUsers(array $users, callable $callback): void
    {
        $userCounters = &$this->getCounter(Transfer::RESOURCE_USERS);
        $auth = new Users($this->client);

        foreach ($users as $user) {
            /** @var \Utopia\Transfer\Resources\User $user */
            try {
                $createdUser = in_array(User::TYPE_EMAIL, $user->getTypes()) ? $this->importPasswordUser($user) : $auth->create($user->getId(), $user->getEmail(), $user->getPhone(), null, $user->getName());

                if (!$createdUser) {
                    $this->logs[Log::ERROR][] = new Log('Failed to import user', \time(), $user);
                    $userCounters = &$this->getCounter(Transfer::RESOURCE_USERS);
                    $userCounters['failed']++;
                } else {
                    // Add more data to the user
                    if ($user->getUsername()) {
                        $auth->updateName($user->getId(), $user->getUsername());
                    }

                    if ($user->getPhone()) {
                        $auth->updatePhone($user->getId(), $user->getPhone());
                    }

                    if ($user->getEmailVerified()) {
                        $auth->updateEmailVerification($user->getId(), $user->getEmailVerified());
                    }

                    if ($user->getPhoneVerified()) {
                        $auth->updatePhoneVerification($user->getId(), $user->getPhoneVerified());
                    }

                    if ($user->getDisabled()) {
                        $auth->updateStatus($user->getId(), !$user->getDisabled());
                    }

                    $this->logs[Log::SUCCESS][] = new Log('User imported successfully', \time(), $user);
                    $userCounters['current']++;
                }
            } catch (\Exception $e) {
                $this->logs[Log::ERROR][] = new Log($e->getMessage(), \time(), $user);
                $counter = &$this->getCounter(Transfer::RESOURCE_USERS);
                $counter['failed']++;
            }
        }

        $callback(
            new Progress(
                Transfer::RESOURCE_USERS,
                time(),
                $userCounters['total'],
                $userCounters['current'],
                $userCounters['failed'],
                $userCounters['skipped']
            )
        );
    }

    public function createAttribute(Attribute $attribute, Collection $collection, Database $database): void
    {
        $databaseService = new DatabasesService($this->client);

        try {
            switch ($attribute->getName()) {
                case Attribute::TYPE_STRING:
                    /** @var StringAttribute $attribute */
                    $databaseService->createStringAttribute($database->getId(), $collection->getId(), $attribute->getKey(), $attribute->getSize(), $attribute->getRequired(), $attribute->getDefault(), $attribute->getArray());
                    break;
                case Attribute::TYPE_INTEGER:
                    /** @var IntAttribute $attribute */
                    $databaseService->createIntegerAttribute($database->getId(), $collection->getId(), $attribute->getKey(), $attribute->getRequired(), $attribute->getMin(), $attribute->getMax(), $attribute->getDefault(), $attribute->getArray());
                    break;
                case Attribute::TYPE_FLOAT:
                    /** @var FloatAttribute $attribute */
                    $databaseService->createFloatAttribute($database->getId(), $collection->getId(), $attribute->getKey(), $attribute->getRequired(), $attribute->getDefault(), $attribute->getArray());
                    break;
                case Attribute::TYPE_BOOLEAN:
                    /** @var BoolAttribute $attribute */
                    $databaseService->createBooleanAttribute($database->getId(), $collection->getId(), $attribute->getKey(), $attribute->getRequired(), $attribute->getDefault(), $attribute->getArray());
                    break;
                case Attribute::TYPE_DATETIME:
                    /** @var DateTimeAttribute $attribute */
                    $databaseService->createDateTimeAttribute($database->getId(), $collection->getId(), $attribute->getKey(), $attribute->getRequired(), $attribute->getDefault(), $attribute->getArray());
                    break;
                case Attribute::TYPE_EMAIL:
                    /** @var EmailAttribute $attribute */
                    $databaseService->createEmailAttribute($database->getId(), $collection->getId(), $attribute->getKey(), $attribute->getRequired(), $attribute->getDefault(), $attribute->getArray());
                    break;
                case Attribute::TYPE_IP:
                    /** @var IPAttribute $attribute */
                    $databaseService->createIPAttribute($database->getId(), $collection->getId(), $attribute->getKey(), $attribute->getRequired(), $attribute->getDefault(), $attribute->getArray());
                    break;
                case Attribute::TYPE_URL:
                    /** @var URLAttribute $attribute */
                    $databaseService->createUrlAttribute($database->getId(), $collection->getId(), $attribute->getKey(), $attribute->getRequired(), $attribute->getDefault(), $attribute->getArray());
                    break;
                case Attribute::TYPE_ENUM:
                    /** @var EnumAttribute $attribute */
                    $databaseService->createEnumAttribute($database->getId(), $collection->getId(), $attribute->getKey(), $attribute->getElements(), $attribute->getRequired(), $attribute->getDefault(), $attribute->getArray());
                    break;
            }
        } catch (\Exception $e) {
            $this->logs[Log::ERROR][] = new Log($e->getMessage(), \time(), $attribute);
        }
    }

    /**
     * Validate Attributes Creation
     * 
     * @param Attribute[] $attributes
     * @param Collection $collection
     * @param Database $database
     * 
     * @return bool
     */
    public function validateAttributesCreation(array $attributes, Collection $collection, Database $database): bool
    {
        $databaseService = new DatabasesService($this->client);
        $destinationAttributes = $databaseService->listAttributes($database->getId(), $collection->getId())['attributes'];

        foreach ($attributes as $attribute) {
            /** @var Attribute $attribute */
            $foundAttribute = null;

            foreach ($destinationAttributes as $destinationAttribute) {
                if ($destinationAttribute['key'] === $attribute->getKey()) {
                    $foundAttribute = $destinationAttribute;
                    break;
                }
            }

            if ($foundAttribute) {
                if ($foundAttribute['status'] !== 'available') {
                    return false;
                } else {
                    continue;
                }
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Import Databases
     * 
     * @param array $databases
     * @param callable $callback
     * 
     * @return void
     */
    public function importDatabases(array $databases, callable $callback): void
    {
        $databaseCounters = &$this->getCounter(Transfer::RESOURCE_DATABASES);
        $databaseService = new DatabasesService($this->client);

        foreach ($databases as $database) {
            /** @var Database $database */
            try {
                $databaseService->create($database->getId(), $database->getDBName());

                $createdCollections = [];

                foreach ($database->getCollections() as $collection) {
                    /** @var Collection $collection */
                    $createdAttributes = [];

                    // Get filename and parent directory
                    $path = \explode('/', $collection->getCollectionName());

                    $collectionName = $path[count($path) - 1];

                    if (isset($path[count($path) - 2])) {
                        $collectionName = $path[count($path) - 2]."/".$collectionName;
                    }

                    // Handle special chars
                    $collectionName = \str_replace([' ', '(', ')', '[', ']', '{', '}', '<', '>', ':', ';', ',', '.', '?', '\\', '|', '=', '+', '*', '&', '^', '%', '$', '#', '@', '!', '~', '`', '"', "'"], '_', $collectionName);

                    // Check name length
                    if (\strlen($collectionName) > 120) {
                        $collectionName = \substr($collectionName, 0, 120);
                    }

                    $newCollection = $databaseService->createCollection($database->getId(), "unique()", $collectionName);
                    $collection->setId($newCollection['$id']);

                    // Remove duplicate attributes
                    $filteredAttributes = \array_filter($collection->getAttributes(), function ($attribute) use (&$createdAttributes) {
                        if (\in_array($attribute->getKey(), $createdAttributes)) {
                            return false;
                        }

                        $createdAttributes[] = $attribute->getKey();

                        return true;
                    });

                    $filteredAttributes[] = new StringAttribute($collection->getId(), false, false, null, 1000000);

                    foreach ($filteredAttributes as $attribute) {
                        /** @var Attribute $attribute */
                        $this->createAttribute($attribute, $collection, $database);
                    }

                    // We need to wait for all the attributes to be created before creating the indexes.
                    $timeout = 0;

                    while (!$this->validateAttributesCreation($collection->getAttributes(), $collection, $database)) {
                        if ($timeout > 60) {
                            throw new AppwriteException('Timeout while waiting for attributes to be created');
                        }

                        $timeout++;
                        \sleep(1);
                    }

                    foreach ($collection->getIndexes() as $index) {
                        /** @var Index $index */
                        $databaseService->createIndex($database->getId(), $collection->getId(), $index->getKey(), $index->getType(), $index->getAttributes(), $index->getOrders());
                    }

                    $createdCollections[] = $collection;
                }

                $refCollectionID = $databaseService->createCollection($database->getId(), 'refs', 'References')['$id'];
                $databaseService->createStringAttribute($database->getId(), $refCollectionID, 'original_name', 1000000, true);

                sleep(2);

                foreach ($createdCollections as $collection) {
                    /** @var Collection $collection */
                    $result = $databaseService->createDocument($database->getId(), $refCollectionID, $collection->getId(), [
                        'original_name' => $collection->getCollectionName()
                    ]);
                }

                $this->logs[Log::SUCCESS][] = new Log('Database imported successfully', \time(), $database);
                $databaseCounters['current']++;
            } catch (AppwriteException $e) {
                $this->logs[Log::ERROR][] = new Log($e->getMessage(), \time(), $database);
                $databaseCounters['failed']++;
            }
        }

        $callback(
            new Progress(
                Transfer::RESOURCE_DATABASES,
                time(),
                $databaseCounters['total'],
                $databaseCounters['current'],
                $databaseCounters['failed'],
                $databaseCounters['skipped']
            )
        );
    }
}
