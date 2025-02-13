<?php

namespace Utopia\Migration\Sources;

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
use Utopia\Migration\Resources\Storage\Bucket;
use Utopia\Migration\Resources\Storage\File;
use Utopia\Migration\Source;
use Utopia\Migration\Transfer;

class Firebase extends Source
{
    /**
     * @var array<string, mixed>
     */
    private array $serviceAccount;

    private string $projectID;

    private string $currentToken = '';

    private int $tokenExpires = 0;

    /**
     * @param array<string, mixed> $serviceAccount
     */
    public function __construct(array $serviceAccount)
    {
        $this->serviceAccount = $serviceAccount;
        $this->projectID = $serviceAccount['project_id'];
    }

    public static function getName(): string
    {
        return 'Firebase';
    }

    private function base64UrlEncode(string $data): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    private function calculateJWT(): string
    {
        $jwtClaim = [
            'iss' => $this->serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase https://www.googleapis.com/auth/cloud-platform https://www.googleapis.com/auth/datastore',
            'exp' => \time() + 3600,
            'iat' => \time(),
            'aud' => 'https://oauth2.googleapis.com/token',
        ];

        $jwtHeader = [
            'alg' => 'RS256',
            'typ' => 'JWT',
        ];

        $jwtPayload = $this->base64UrlEncode(json_encode($jwtHeader)).'.'.$this->base64UrlEncode(json_encode($jwtClaim));

        $jwtSignature = '';

        \openssl_sign(
            $jwtPayload,
            $jwtSignature,
            $this->serviceAccount['private_key'],
            'sha256'
        );

        $jwtSignature = $this->base64UrlEncode($jwtSignature);

        return $jwtPayload . '.' . $jwtSignature;
    }

    /**
     * Computes the JWT then fetches an auth token from the Google OAuth2 API which is valid for an hour
     */
    private function authenticate(): void
    {
        if (time() < $this->tokenExpires) {
            return;
        }

        try {
            $response = parent::call('POST', 'https://oauth2.googleapis.com/token', [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ], [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->calculateJWT(),
            ]);

            $this->currentToken = $response['access_token'];
            $this->tokenExpires = time() + $response['expires_in'];
            $this->headers['Authorization'] = 'Bearer '.$this->currentToken;
        } catch (\Throwable $e) {
            throw new \Exception('Failed to authenticate with Firebase: '.$e->getMessage());
        }
    }

    protected function call(string $method, string $path = '', array $headers = [], array $params = [], array &$responseHeaders = []): array|string
    {
        $this->authenticate();

        return parent::call($method, $path, $headers, $params, $responseHeaders);
    }

    /**
     * @return array<string>
     */
    public static function getSupportedResources(): array
    {
        return [
            // Auth
            Resource::TYPE_USER,

            // Database
            Resource::TYPE_DATABASE,
            Resource::TYPE_COLLECTION,
            Resource::TYPE_ATTRIBUTE,
            Resource::TYPE_DOCUMENT,

            // Storage
            Resource::TYPE_BUCKET,
            Resource::TYPE_FILE,
        ];
    }

    public function report(array $resources = []): array
    {
        // Check our service account is valid
        if (! isset($this->serviceAccount['project_id'])) {
            throw new \Exception('Invalid Firebase Service Account');
        }

        $this->authenticate();

        $scopes = $this->call('GET', 'https://www.googleapis.com/oauth2/v1/tokeninfo?access_token='.$this->currentToken)['scope'];

        $scopes = explode(' ', $scopes);

        if (! in_array('https://www.googleapis.com/auth/firebase', $scopes)) {
            throw new \Exception('Firebase Scope Missing');
        }

        if (! in_array('https://www.googleapis.com/auth/cloud-platform', $scopes)) {
            throw new \Exception('Cloud Platform Scope Missing');
        }

        if (! in_array('https://www.googleapis.com/auth/datastore', $scopes)) {
            throw new \Exception('Datastore Scope Missing');
        }

        return [];
    }

    protected function exportGroupAuth(int $batchSize, array $resources): void
    {
        // Check if Auth is enabled
        try {
            $this->call('GET', 'https://identitytoolkit.googleapis.com/v1/projects');
        } catch (\Throwable $e) {
            $message = json_decode($e->getMessage(), true);

            if (isset($message['error']['details']) && $message['error']['details'][1]['reason'] == 'SERVICE_DISABLED') {
                // IdentityKit is disabled
                return;
            }

            throw $e;
        }

        try {
            if (\in_array(Resource::TYPE_USER, $resources)) {
                $this->exportUsers($batchSize);
            }
        } catch (\Throwable $e) {
            $this->addError(
                new Exception(
                    Resource::TYPE_USER,
                    Transfer::GROUP_AUTH,
                    message: $e->getMessage(),
                    code: $e->getCode(),
                    previous: $e
                )
            );
        }
    }

    private function exportUsers(int $batchSize): void
    {
        // Fetch our Hash Config
        $hashConfig = ($this->call('GET', 'https://identitytoolkit.googleapis.com/admin/v2/projects/'.$this->projectID.'/config'))['signIn']['hashConfig'];

        $nextPageToken = null;

        // Transfer Users
        while (true) {
            $users = [];

            $request = [
                'targetProjectId' => $this->projectID,
                'maxResults' => $batchSize,
            ];

            if ($nextPageToken) {
                $request['nextPageToken'] = $nextPageToken;
            }

            $response = $this->call('POST', 'https://identitytoolkit.googleapis.com/identitytoolkit/v3/relyingparty/downloadAccount', [
                'Content-Type' => 'application/json',
            ], $request);

            if (! isset($response['users'])) {
                break;
            }

            $result = $response['users'];
            $nextPageToken = $response['nextPageToken'] ?? null;

            foreach ($result as $user) {
                $hash = null;

                if (array_key_exists('passwordHash', $user)) {
                    $hash = new Hash($user['passwordHash'], $user['salt'] ?? '', Hash::ALGORITHM_SCRYPT_MODIFIED, $hashConfig['saltSeparator'] ?? '', $hashConfig['signerKey'] ?? '');
                }

                $transferUser = new User(
                    $user['localId'] ?? '',
                    $user['email'] ?? null,
                    $user['displayName'] ?? $user['email'] ?? null,
                    $hash,
                    $user['phoneNumber'] ?? null,
                    [],
                    '',
                    $user['emailVerified'] ?? false,
                    false, // Can't get phone number status on firebase :/
                    $user['disabled'] ?? false
                );

                $users[] = $transferUser;
            }

            $this->callback($users);

            if (count($result) < $batchSize) {
                break;
            }
        }
    }

    protected function exportGroupDatabases(int $batchSize, array $resources): void
    {
        // Check if Firestore is enabled
        try {
            $this->call('GET', 'https://firestore.googleapis.com/v1/projects/'.$this->projectID.'/databases');
        } catch (\Exception $e) {
            $message = json_decode($e->getMessage(), true);

            if (isset($message['error']['details']) && $message['error']['details'][1]['reason'] == 'SERVICE_DISABLED') {
                // Firestore is disabled
                return;
            }

            throw $e;
        }

        try {
            if (\in_array(Resource::TYPE_DATABASE, $resources)) {
                $database = new Database('default', 'default');
                $database->setOriginalId('(default)');
                $this->callback([$database]);
            }
        } catch (\Throwable $e) {
            $this->addError(
                new Exception(
                    Resource::TYPE_DATABASE,
                    Transfer::GROUP_DATABASES,
                    message: $e->getMessage(),
                    code: $e->getCode(),
                    previous: $e
                )
            );
        }

        try {
            if (\in_array(Resource::TYPE_COLLECTION, $resources)) {
                $this->exportDB($batchSize, in_array(Resource::TYPE_DOCUMENT, $resources), $database);
            }
        } catch (\Throwable $e) {
            $this->addError(
                new Exception(
                    Resource::TYPE_COLLECTION,
                    Transfer::GROUP_DATABASES,
                    message: $e->getMessage(),
                    code: $e->getCode(),
                    previous: $e
                )
            );
        }
    }

    private function exportDB(int $batchSize, bool $pushDocuments, Database $database): void
    {
        $baseURL = "https://firestore.googleapis.com/v1/projects/{$this->projectID}/databases/(default)/documents";

        $nextPageToken = null;
        $allCollections = [];
        while (true) {
            $collections = [];

            try {
                $result = $this->call('POST', $baseURL.':listCollectionIds', [
                    'Content-Type' => 'application/json',
                ], [
                    'pageSize' => $batchSize,
                    'pageToken' => $nextPageToken,
                ]);

                if (! isset($result['collectionIds'])) {
                    break;
                }
            } catch (\Exception $e) {
                if ($e->getCode() == 403) {
                    $errorMessage = new Collection($database, 'firestore', 'firestore');

                    $errorMessage->setStatus(Resource::STATUS_ERROR);
                    $errorMessage->setMessage($e->getMessage());

                    $this->cache->add($errorMessage);
                }

                break;
            }

            // Transfer Collections
            foreach ($result['collectionIds'] as $collection) {
                $collections[] = new Collection($database, $collection, $collection);
            }

            if (count($collections) !== 0) {
                $allCollections = array_merge($allCollections, $collections);
                $this->callback($collections);
            } else {
                return;
            }

            // Transfer Documents and Calculate Schema
            foreach ($collections as $collection) {
                $this->exportCollection($collection, $batchSize, $pushDocuments);
            }

            if (count($result['collectionIds']) < $batchSize) {
                break;
            }

            $nextPageToken = $result['nextPageToken'] ?? null;
        }
    }

    /**
     * @throws \Exception
     */
    private function convertAttribute(Collection $collection, string $key, array $field, bool $array = false): Attribute
    {
        if (array_key_exists('booleanValue', $field)) {
            return new Boolean(
                $key,
                $collection,
                required:false,
                default: null,
                array: $array,
            );
        } elseif (array_key_exists('bytesValue', $field)) {
            return new Text(
                $key,
                $collection,
                required: false,
                default: null,
                array: $array,
                size: 1000000,
            );
        } elseif (array_key_exists('doubleValue', $field)) {
            return new Decimal(
                $key,
                $collection,
                required: false,
                default: null,
                array: $array,
            );
        } elseif (array_key_exists('integerValue', $field)) {
            return new Integer(
                $key,
                $collection,
                required: false,
                default: null,
                array: $array,
            );
        } elseif (array_key_exists('mapValue', $field)) {
            return new Text(
                $key,
                $collection,
                required: false,
                default: null,
                array: $array,
                size: 1000000,
            );
        } elseif (array_key_exists('nullValue', $field)) {
            return new Text(
                $key,
                $collection,
                required: false,
                default: null,
                array: $array,
                size: 1000000,
            );
        } elseif (array_key_exists('referenceValue', $field)) {
            return new Text(
                $key,
                $collection,
                required: false,
                default: null,
                array: $array,
                size: 1000000,
            ); //TODO: This should be a reference attribute
        } elseif (array_key_exists('stringValue', $field)) {
            return new Text(
                $key,
                $collection,
                required: false,
                default: null,
                array: $array,
                size: 1000000,
            );
        } elseif (array_key_exists('timestampValue', $field)) {
            return new DateTime(
                $key,
                $collection,
                required: false,
                default: null,
                array: $array,
            );
        } elseif (array_key_exists('geoPointValue', $field)) {
            return new Text(
                $key,
                $collection,
                required: false,
                default: null,
                array: $array,
                size: 1000000,
            );
        } elseif (array_key_exists('arrayValue', $field)) {
            return $this->calculateArrayType($collection, $key, $field['arrayValue']);
        } else {
            throw new \Exception('Unknown field type');
        }
    }

    private function calculateArrayType(Collection $collection, string $key, array $data): Attribute
    {
        $isSameType = true;
        $previousType = null;

        foreach ($data['values'] as $field) {
            if (! $previousType) {
                $previousType = $this->convertAttribute($collection, $key, $field, true);
            } elseif ($previousType->getName() != ($this->convertAttribute($collection, $key, $field))->getName()) {
                $isSameType = false;
                break;
            }
        }

        if ($isSameType) {
            return $previousType;
        } else {
            return new Text($key, $collection, false, true, true, 1000000);
        }
    }

    private function exportCollection(Collection $collection, int $batchSize, bool $transferDocuments): void
    {
        $resourceURL = 'https://firestore.googleapis.com/v1/projects/'.$this->projectID.'/databases/'.$collection->getDatabase()->getOriginalId().'/documents/'.$collection->getId();

        $nextPageToken = null;

        $documentSchema = [];
        $createdSchema = [];

        // Transfer Documents and Calculate Schemas
        while (true) {
            $documents = [];

            $result = $this->call('GET', $resourceURL, [
                'Content-Type' => 'application/json',
            ], [
                'pageSize' => $batchSize,
                'pageToken' => $nextPageToken,
            ]);

            if (empty($result)) {
                break;
            }

            foreach ($result['documents'] as $document) {
                if (! isset($document['fields'])) {
                    continue; //TODO: Transfer Empty Documents
                }

                foreach ($document['fields'] as $key => $field) {
                    if (! isset($documentSchema[$key])) {
                        $documentSchema[$key] = $this->convertAttribute($collection, $key, $field);
                    }
                }

                $documents[] = $this->convertDocument($collection, $document, $documentSchema);
            }

            // Transfer Documents
            if ($transferDocuments) {
                $cachedAtrributes = $this->cache->get(Attribute::getName());

                $attributesToCreate = $documentSchema;

                foreach ($documentSchema as $key => $attribute) {
                    foreach ($cachedAtrributes as $cachedAttribute) {
                        /** @var Attribute $cachedAttribute */
                        if ($cachedAttribute->getKey() == $attribute->getKey() && $cachedAttribute->getCollection()->getId() == $attribute->getCollection()->getId()) {
                            unset($attributesToCreate[$key]);
                        }
                    }
                }

                if (count($attributesToCreate) > 0) {
                    $this->callback(array_values($attributesToCreate));
                }

                $this->callback($documents);
            }

            if (count($result['documents']) < $batchSize) {
                break;
            }

            $nextPageToken = $result['nextPageToken'] ?? null;
        }
    }

    private function calculateValue(array $field)
    {
        if (array_key_exists('booleanValue', $field)) {
            return boolval($field['booleanValue']);
        } elseif (array_key_exists('bytesValue', $field)) {
            return $field['bytesValue'];
        } elseif (array_key_exists('doubleValue', $field)) {
            return floatval($field['doubleValue']);
        } elseif (array_key_exists('integerValue', $field)) {
            return intval($field['integerValue']);
        } elseif (array_key_exists('mapValue', $field)) {
            return json_encode($field['mapValue']);
        } elseif (array_key_exists('nullValue', $field)) {
            return $field['nullValue'];
        } elseif (array_key_exists('referenceValue', $field)) {
            return $field['referenceValue']; //TODO: This should be a reference attribute
        } elseif (array_key_exists('stringValue', $field)) {
            return $field['stringValue'];
        } elseif (array_key_exists('timestampValue', $field)) {
            return $field['timestampValue'];
        } elseif (array_key_exists('geoPointValue', $field)) {
            return json_encode($field['geoPointValue']);
        } elseif (array_key_exists('arrayValue', $field)) {
            $values = [];
            foreach ($field['arrayValue']['values'] as $value) {
                $values[] = $this->calculateValue($value);
            }
            return array_values($values);
        } elseif (array_key_exists('referenceValue', $field)) {
            //TODO:
        } else {
            throw new \Exception('Unknown field type');
        }
    }

    private function convertDocument(Collection $collection, array $document, array $documentSchema): Document
    {
        $data = [];
        foreach ($document['fields'] as $key => $field) {
            $value = $this->calculateValue($field);

            if ($documentSchema[$key]->getType() === Attribute::TYPE_STRING && is_array($value)) {
                $value = array_map(function ($item) {
                    return strval($item);
                }, $value);
            };

            $data[$key] = $value;
        }

        $documentId = explode('/', $document['name']);
        $documentId = end($documentId);
        // Strip non-alphanumeric except underscore and hyphen
        $documentId = preg_replace("/[^A-Za-z0-9\_\-]/", '', $documentId);
        $documentId = strtolower($documentId);

        return new Document($documentId, $collection, $data, []);
    }

    protected function exportGroupStorage(int $batchSize, array $resources): void
    {
        // Check if storage is enabled
        try {
            $this->call('GET', 'https://storage.googleapis.com/storage/v1/b', [], [
                'project' => $this->projectID,
                'maxResults' => 1,
                'alt' => 'json',
            ]);
        } catch (\Exception $e) {
            $message = json_decode($e->getMessage(), true);

            if (isset($message['error']['details']) && $message['error']['details'][1]['reason'] == 'SERVICE_DISABLED') {
                // Storage is disabled
                return;
            }

            throw $e;
        }

        try {
            if (\in_array(Resource::TYPE_BUCKET, $resources)) {
                $this->exportBuckets($batchSize);
            }
        } catch (\Throwable $e) {
            $this->addError(new Exception(
                Resource::TYPE_BUCKET,
                Transfer::GROUP_STORAGE,
                message: $e->getMessage(),
                code: $e->getCode(),
                previous: $e
            ));
        }

        try {
            if (\in_array(Resource::TYPE_FILE, $resources)) {
                $this->exportFiles($batchSize);
            }
        } catch (\Throwable $e) {
            $this->addError(new Exception(
                Resource::TYPE_FILE,
                Transfer::GROUP_STORAGE,
                message: $e->getMessage(),
                code: $e->getCode(),
                previous: $e
            ));
        }

    }

    private function exportBuckets(int $batchsize): void
    {
        $endpoint = 'https://storage.googleapis.com/storage/v1/b';

        $nextPageToken = null;

        while (true) {
            $result = $this->call('GET', $endpoint, [], [
                'project' => $this->projectID,
                'maxResults' => $batchsize,
                'pageToken' => $nextPageToken,
                'alt' => 'json',
            ]);

            if (empty($result)) {
                break;
            }

            if (! isset($result['items'])) {
                break;
            }

            $buckets = [];
            foreach ($result['items'] as $bucket) {
                $curBucket = new Bucket($this->sanitizeBucketId($bucket['id']), $bucket['name'], [], false);
                $curBucket->setOriginalId($bucket['id']);

                $buckets[] = $curBucket;
            }

            $this->callback($buckets);

            if (! isset($result['nextPageToken'])) {
                break;
            }

            $nextPageToken = $result['nextPageToken'] ?? null;
        }
    }

    private function sanitizeBucketId($id): array|string|null
    {
        // Step 1: Check if the ID looks like a URL (contains ".")
        if (strpos($id, '.') !== false) {
            // If it looks like a URL, try to extract the subdomain
            $parts = explode('.', $id);
            if (count($parts) > 0) {
                $id = $parts[0];
            }
        }

        // Step 2: Ensure the ID contains at most 36 characters
        $id = substr($id, 0, 36);

        // Step 3: Remove invalid characters using a regular expression
        $id = preg_replace('/[^a-zA-Z0-9\._-]/', '', $id);

        // Step 4: Ensure the ID doesn't start with a special character
        if (preg_match('/^[._-]/', $id)) {
            $id = 'a'.substr($id, 1);
        }

        return $id;
    }

    private function exportFiles(int $batchsize): void
    {
        $buckets = $this->cache->get(Bucket::getName());

        foreach ($buckets as $bucket) {
            /** @var Bucket $bucket */
            $endpoint = 'https://storage.googleapis.com/storage/v1/b/'.$bucket->getOriginalId().'/o';

            $nextPageToken = null;

            while (true) {
                $result = $this->call('GET', $endpoint, [
                    'Content-Type' => 'application/json',
                ], [
                    'pageSize' => $batchsize,
                    'pageToken' => $nextPageToken,
                ]);

                if (empty($result)) {
                    break;
                }

                if (! isset($result['items'])) {
                    break;
                }

                foreach ($result['items'] as $item) {
                    $this->exportFile(new File($item['name'], $bucket, $item['name']));
                }

                if (count($result['items']) < $batchsize) {
                    break;
                }

                $nextPageToken = $result['nextPageToken'] ?? null;
            }
        }
    }

    private function exportFile(File $file): void
    {
        $endpoint = 'https://storage.googleapis.com/storage/v1/b/'.$file->getBucket()->getOriginalId().'/o/'.$file->getId().'?alt=media';
        $start = 0;
        $end = Transfer::STORAGE_MAX_CHUNK_SIZE - 1;

        while (true) {
            $result = $this->call('GET', $endpoint, [
                'Range' => 'bytes='.$start.'-'.$end,
            ]);

            if (empty($result)) {
                break;
            }

            $file->setData($result)
                ->setStart($start)
                ->setEnd($end);

            $this->callback([$file]);

            if (strlen($result) < Transfer::STORAGE_MAX_CHUNK_SIZE) {
                break;
            }

            $start += Transfer::STORAGE_MAX_CHUNK_SIZE;
            $end += Transfer::STORAGE_MAX_CHUNK_SIZE;
        }
    }

    protected function exportGroupFunctions(int $batchSize, array $resources): void
    {
        throw new \Exception('Not implemented');
    }
}
