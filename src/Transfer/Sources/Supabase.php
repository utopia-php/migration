<?php

namespace Utopia\Transfer\Sources;

use Utopia\Transfer\Resources\Auth\User;
use Utopia\Transfer\Resources\Auth\Hash;
use Utopia\Transfer\Resources\Storage\Bucket;
use Utopia\Transfer\Resources\Storage\File;
use Utopia\Transfer\Resources\Storage\FileData;
use Utopia\Transfer\Transfer;

use function PHPUnit\Framework\callback;

class Supabase extends NHost
{
    public function getName(): string
    {
        return 'Supabase';
    }

    protected string $key;

    /**
     * Constructor
     *
     * @param string $endpoint
     * @param string $key
     * @param string $host
     * @param string $databaseName
     * @param string $username
     * @param string $password
     * @param string $port
     *
     * @return self
     */
    public function __construct(string $endpoint, string $key, string $host, string $databaseName, string $username, string $password, string $port = '5432')
    {
        $this->endpoint = $endpoint;
        $this->key = $key;
        $this->host = $host;
        $this->databaseName = $databaseName;
        $this->username = $username;
        $this->password = $password;
        $this->port = $port;

        $this->headers['Authorization'] = 'Bearer ' . $this->key;

        try {
            $this->pdo = new \PDO("pgsql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->databaseName, $this->username, $this->password);
        } catch (\PDOException $e) {
            throw new \Exception('Failed to connect to database: ' . $e->getMessage());
        }
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
        if (empty($user['encrypted_password']) && empty($user['phone'])) {
            return [User::TYPE_ANONYMOUS];
        }

        $types = [];

        if (!empty($user['encrypted_password'])) {
            $types[] = User::TYPE_EMAIL;
        }

        if (!empty($user['phone'])) {
            $types[] = User::TYPE_PHONE;
        }

        return $types;
    }

    public function exportFiles(int $batchSize, callable $callback): void
    {
        // Transfer Buckets
        $statement = $this->pdo->prepare('SELECT * FROM storage.buckets order by created_at');
        $statement->execute();

        $buckets = $statement->fetchAll(\PDO::FETCH_ASSOC);

        $transferBuckets = [];

        foreach ($buckets as $bucket) {
            $transferBuckets[] = new Bucket(
                $bucket['id'],
                [],
                false,
                $bucket['name'],
                true,
                $bucket['file_size_limit'] ?? 0,
                [], // $bucket['allowed_mime_type'], //TODO: Need to convert this to file extensions
            );
        }

        $callback($transferBuckets);

        // Transfer Files
        foreach ($transferBuckets as $bucket) {
            /** @var Bucket $bucket */
            $totalStatement = $this->pdo->prepare('SELECT COUNT(*) FROM storage.objects WHERE bucket_id=:bucketId');
            $totalStatement->execute([':bucketId' => $bucket->getId()]);
            $total = $totalStatement->fetchColumn();

            $offset = 0;
            while ($offset < $total) {
                $statement = $this->pdo->prepare('SELECT * FROM storage.objects WHERE bucket_id=:bucketId ORDER BY created_at LIMIT :limit OFFSET :offset');
                $statement->execute([
                    ':bucketId' => $bucket->getId(),
                    ':limit' => $batchSize,
                    ':offset' => $offset
                ]);

                $files = $statement->fetchAll(\PDO::FETCH_ASSOC);

                $offset += $batchSize;

                foreach ($files as $file) {
                    $metadata = json_decode($file['metadata'], true);

                    $this->handleFileDataTransfer(new File(
                        $file['id'],
                        $bucket,
                        $file['name'],
                        '',
                        $metadata['mimetype'],
                        [],
                        $metadata['size']
                    ), $callback);
                }
            }
        }
    }

    /**
     * Handle File Transfer
     * Streams a file to the destination
     *
     * @param File $file
     * @param callable $callback (array $data)
     *
     * @return void
     */
    protected function handleFileDataTransfer(File $file, callable $callback): void
    {
        // Set the chunk size (5MB)
        $start = 0;
        $end = Transfer::STORAGE_MAX_CHUNK_SIZE - 1;

        // Get the file size
        $fileSize = $file->getSize();

        if ($end > $fileSize) {
            $end = $fileSize - 1;
        }

        // Loop until the entire file is downloaded
        while ($start < $fileSize) {
            $chunkData = $this->call(
                'GET',
                '/storage/v1/object/' .
                rawurlencode($file->getBucket()->getId()) . '/' . rawurlencode($file->getFileName()),
                ['range' => "bytes=$start-$end"]
            );

            // Send the chunk to the callback function
            $callback([new FileData(
                $chunkData,
                $start,
                $end,
                $file
            )]);

            // Update the range
            $start += Transfer::STORAGE_MAX_CHUNK_SIZE;
            $end += Transfer::STORAGE_MAX_CHUNK_SIZE;

            if ($end > $fileSize) {
                $end = $fileSize - 1;
            }
        }
    }
}
