<?php

namespace Utopia\Transfer\Destinations;

use Appwrite\Client;
use Appwrite\Services\Users;
use Utopia\Transfer\Destination;
use Utopia\Transfer\Resources\Hash;
use Utopia\Transfer\Log;
use Utopia\Transfer\Progress;
use Utopia\Transfer\Resources\User;
use Utopia\Transfer\Transfer;

class Appwrite extends Destination {
    protected Client $client;

    public function __construct(protected string $projectID, string $endpoint, private string $apiKey) 
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
                    $userCounters = &$this->getCounter(Transfer::RESOURCE_USERS);
                    $userCounters['current']++;

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
            } catch (\Exception $e) {
                $this->logs[Log::ERROR][] = new Log($e->getMessage(), \time(), $user);
                $counter = &$this->getCounter(Transfer::RESOURCE_USERS);
                $counter['failed']++;
            }
        }
    }
}