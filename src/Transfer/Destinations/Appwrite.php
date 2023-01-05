<?php

namespace Utopia\Transfer\Destinations;

use Appwrite\Client;
use Appwrite\Services\Users;
use Utopia\Transfer\Destination;
use Utopia\Transfer\Hash;
use Utopia\Transfer\Log;
use Utopia\Transfer\Resources\User;
use Utopia\Transfer\Transfer;

class Appwrite extends Destination {
    protected Client $client;

    public function __construct(protected string $projectID, protected string $endpoint, private string $apiKey) 
    {
        $this->client = new Client();
        $this->client->setEndpoint($endpoint);
        $this->client->setProject($projectID);
        $this->client->setKey($apiKey);
    }

    /**
     * Get Name
     * 
     * @return string
     */
    public function getName(): string {
        return 'Appwrite';
    }

    /**
     * Get Supported Resources
     * 
     * @return array
     */
    public function getSupportedResources(): array {
        return [
            Transfer::RESOURCE_USERS,
            Transfer::RESOURCE_DATABASES,
            Transfer::RESOURCE_COLLECTIONS,
            Transfer::RESOURCE_FILES,
            Transfer::RESOURCE_FUNCTIONS
        ];
    }

    public function check(array $resources = []): bool
    {
        //TODO: Implement check() method.
        return true;
    }

    public function importPasswordUser(User $user): void
    {
        $authentication = new Users($this->client);
        $hash = $user->getPasswordHash();
        $result = null;

        switch ($hash->getAlgorithm()) {
            case Hash::SCRYPT_MODIFIED: 
                $result = $authentication->createScryptModifiedUser(
                    $user->getId(),
                    $user->getEmail(),
                    $hash->getHash(),
                    $hash->getSalt(),
                    $hash->getSeparator(),
                    $hash->getSigningKey(),
                    $user->getEmail()
                );
                break;
            case Hash::BCRYPT:
                $result = $authentication->createBcryptUser(
                    $user->getId(),
                    $user->getEmail(),
                    $hash->getHash(),
                    $user->getEmail()
                );
                break;
            case Hash::ARGON2:
                $result = $authentication->createArgon2User(
                    $user->getId(),
                    $user->getEmail(),
                    $hash->getHash(),
                    $user->getEmail()
                );
                break;
            case Hash::SHA:
                $result = $authentication->createShaUser(
                    $user->getId(),
                    $user->getEmail(),
                    $hash->getHash(),
                    $user->getEmail()
                );
                break;
            case Hash::PHPASS:
                $result = $authentication->createPHPassUser(
                    $user->getId(),
                    $user->getEmail(),
                    $hash->getHash(),
                    $user->getEmail()
                );
                break;
            case Hash::SCRYPT:
                $result = $authentication->createScryptUser(
                    $user->getId(),
                    $user->getEmail(),
                    $hash->getHash(),
                    $hash->getSalt(),
                    $hash->getPasswordCpu(),
                    $hash->getPasswordMemory(),
                    $hash->getPasswordParallel(),
                    $hash->getPasswordLength(),
                    $user->getEmail()
                );
                break;
        }

        if (!$result) {
            throw new \Exception('Failed to import user: "'.$user->getId().'"');
        }
    }

    public function importUsers(array $users): void
    {
        foreach ($users as $user) {
            /** @var \Utopia\Transfer\Resources\User $user */
            try {
                switch ($user->getType()) {
                    case User::AUTH_EMAIL:
                        $this->importPasswordUser($user);
                        break;
                    default:
                        $this->logs[] = new Log(Log::WARNING, 'Not copying user: "'.$user->getId().'" due to it being an account type: "'.$user->getType().'".', \time(), $user);
                    //TODO: Implement other auth types, talk to Eldadfux about API's requried (Might have to resort to using Console SDK).
                }
            } catch (\Exception $e) {
                $this->logs[] = new Log(Log::ERROR, $e->getMessage(), \time(), $user);
            }
        }
    }
}