<?php

namespace Utopia\Transfer\Sources;

use Utopia\Transfer\Source;
use Utopia\Transfer\Resources\User;
use Utopia\Transfer\Transfer;
use Utopia\Transfer\Log;
use Utopia\Transfer\Resources\Hash;

class NHost extends Source
{
    /**
     * @var \PDO
     */
    public $pdo;

    function __construct(private string $host, private string $databaseName, private string $username, private string $password, private string $port = '5432')
    {
        $this->pdo = new \PDO("pgsql:host={$this->host};port={$this->port};dbname={$this->databaseName}", $this->username, $this->password);
    }

    function getName(): string
    {
        return 'NHost';
    }

    function getSupportedResources(): array
    {
        return [
            Transfer::RESOURCE_USERS
        ];
    }

    /**
     * Export Users
     * 
     * @param int $batchSize Max 500
     * @param callable $callback Callback function to be called after each batch, $callback(user[] $batch);
     * 
     * @returns User[] 
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

    private function calculateUserTypes(array $user): array
    {
        if (empty($user['password_hash']) && empty($user['phone_number']))
        {
            return [User::TYPE_ANONYMOUS];
        }

        $types = [];

        if (!empty($user['password_hash']))
        {
            $types[] = User::TYPE_EMAIL;
        }

        if (!empty($user['phone_number']))
        {
            $types[] = User::TYPE_PHONE;
        }

        return $types;
    }

    function check(array $resources = []): bool
    {
        if ($this->pdo->errorCode() !== '00000') {
            $this->logs[Log::FATAL] = new Log('Failed to connect to database. Error: ' . $this->pdo->errorInfo()[2]);
        }

        foreach ($resources as $resource)
        {
            switch ($resource) {
                case Transfer::RESOURCE_USERS:
                    $statement = $this->pdo->prepare('SELECT COUNT(*) FROM auth.users');
                    $statement->execute();

                    if ($statement->errorCode() !== '00000') {
                        $this->logs[Log::FATAL] = new Log('Failed to access users table. Error: ' . $statement->errorInfo()[2]);
                    }
                    break;
            }
        }

        return true;
    }
}