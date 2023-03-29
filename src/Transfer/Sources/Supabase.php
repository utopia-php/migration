<?php

namespace Utopia\Transfer\Sources;

use Utopia\Transfer\Resources\User;
use Utopia\Transfer\Transfer;
use Utopia\Transfer\Resources\Hash;

class Supabase extends NHost
{
    function getName(): string
    {
        return 'Supabase';
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
                    '',
                    new Hash($user['encrypted_password'], '', Hash::BCRYPT),
                    $user['phone'] ?? '',
                    $this->calculateAuthTypes($user),
                    '',
                    !empty($user['email_confirmed_at']),
                    !empty($user['phone_confirmed_at']),
                    false,
                    []
                );
            }

            $callback($transferUsers);
        }
    }

    private function calculateAuthTypes(array $user): array
    {
        if (empty($user['encrypted_password']) && empty($user['phone']))
        {
            return [User::TYPE_ANONYMOUS];
        }

        $types = [];

        if (!empty($user['encrypted_password']))
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