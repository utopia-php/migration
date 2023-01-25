<?php

namespace Utopia\Transfer\Sources;

use Utopia\Transfer\Source;
use Appwrite\Client;
use Appwrite\Query;
use Utopia\Transfer\Resources\Project;
use Utopia\Transfer\Resources\User;
use Utopia\Transfer\Transfer;
use Utopia\Transfer\Log;
use Utopia\Transfer\Resource;
use Utopia\Transfer\Resources\Hash;

class Appwrite extends Source
{
    /**
     * @var Client|null
     */
    protected $appwriteClient = null;

    /**
     * Constructor
     * 
     * @param string $endpoint
     * @param string $project
     * @param string $key
     * 
     * @returns self
     */
    function __construct(string $endpoint, string $project, string $key)
    {
        $this->appwriteClient = new Client();
        $this->appwriteClient
            ->setEndpoint($endpoint)
            ->setProject($project)
            ->setKey($key);
    }

    /**
     * Get Name
     * 
     * @returns string
     */
    public function getName(): string
    {
        return 'Appwrite';
    }

    /**
     * Get Supported Resources
     * 
     * @returns array
     */
    public function getSupportedResources(): array
    {
        return [
            Transfer::RESOURCE_USERS,
        ];
    }

    /**
     * Check
     * 
     * @param array $resources
     * 
     * @returns bool
     */
    public function check(array $resources = []): bool
    {
        return true;
    }

    /**
     * Export Users
     * 
     * @param int $batchSize Max 500
     * @param callable $callback Callback function to be called after each batch, $callback(user[] $batch);
     * 
     * @returns void
     */
    public function exportUsers(int $batchSize, callable $callback): void
    {
        $usersClient = new \Appwrite\Services\Users($this->appwriteClient);

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

    /**
     * Calculate Types
     * 
     * @param array $user
     * 
     * @returns array
     */
    protected function calculateTypes(array $user): array
    {
        if (empty($user['email']) && empty($user['phone']))
        {
            return [User::TYPE_ANONYMOUS];
        }

        $types = [];

        if (!empty($user['email']))
        {
            $types[] = User::TYPE_EMAIL;
        }

        if (!empty($user['phone']))
        {
            $types[] = User::TYPE_PHONE;
        }

        return $types;
    }
}
