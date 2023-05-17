<?php

namespace Utopia\Transfer\Sources;

use Utopia\Transfer\Resource;
use Utopia\Transfer\Source;
use Utopia\Transfer\Transfer;
use Utopia\Transfer\Resources\Auth\Hash;
use Utopia\Transfer\Resources\Auth\User;
use Utopia\Transfer\Resources\Database\Attribute;
use Utopia\Transfer\Resources\Database\Attributes\BoolAttribute;
use Utopia\Transfer\Resources\Database\Attributes\DateTimeAttribute;
use Utopia\Transfer\Resources\Database\Attributes\FloatAttribute;
use Utopia\Transfer\Resources\Database\Attributes\IntAttribute;
use Utopia\Transfer\Resources\Database\Attributes\StringAttribute;
use Utopia\Transfer\Resources\Database\Collection;
use Utopia\Transfer\Resources\Database\Database;
use Utopia\Transfer\Resources\Database\Document;
use Utopia\Transfer\Resources\Storage\Bucket;
use Utopia\Transfer\Resources\Storage\File;
use Utopia\Transfer\Resources\Storage\FileData;

class Firebase extends Source
{
    private array $serviceAccount;
    private string $projectID;
    private string $currentToken = '';
    private int $tokenExpires = 0;

    public function __construct(array $serviceAccount)
    {
        $this->serviceAccount = $serviceAccount;
        $this->projectID = $serviceAccount['project_id'];
    }

    static function getName(): string
    {
        return 'Firebase';
    }

    function base64url_encode($data)
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    function calculateJWT(): string
    {
        $jwtClaim = [
            'iss' => $this->serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase https://www.googleapis.com/auth/cloud-platform https://www.googleapis.com/auth/datastore',
            'exp' => time() + 3600,
            'iat' => time(),
            'aud' => 'https://oauth2.googleapis.com/token'
        ];

        $jwtHeader = [
            'alg' => 'RS256',
            'typ' => 'JWT'
        ];

        $jwtPayload = $this->base64url_encode(json_encode($jwtHeader)) . '.' . $this->base64url_encode(json_encode($jwtClaim));

        $jwtSignature = '';
        openssl_sign($jwtPayload, $jwtSignature, $this->serviceAccount['private_key'], 'sha256');
        $jwtSignature = $this->base64url_encode($jwtSignature);

        return $jwtPayload . '.' . $jwtSignature;
    }

    /**
     * Computes the JWT then fetches an auth token from the Google OAuth2 API which is valid for an hour
     */
    function authenticate()
    {
        if (time() < $this->tokenExpires) {
            return;
        }

        try {
            $response = parent::call('POST', 'https://oauth2.googleapis.com/token', [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ], [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->calculateJWT()
            ]);

            $this->currentToken = $response['access_token'];
            $this->tokenExpires = time() + $response['expires_in'];
            $this->headers['Authorization'] = 'Bearer ' . $this->currentToken;
        } catch (\Exception $e) {
            throw new \Exception('Failed to authenticate with Firebase: ' . $e->getMessage());
        }
    }

    public function call(string $method, string $path = '', array $headers = array(), array $params = array()): array|string
    {
        $this->authenticate();

        return parent::call($method, $path, $headers, $params);
    }

    /**
     * Get Supported Resources
     *
     * @return array
     */
    public function getSupportedResources(): array
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
            Resource::TYPE_FILEDATA
        ];
    }

    public function report(array $resources = []): array
    {
        throw new \Exception('Not implemented');
    }

    public function exportAuthGroup(int $batchSize, array $resources)
    {
        if (in_array(Resource::TYPE_USER, $resources)) {
            $this->exportUsers($batchSize);
        }
    }

    function exportUsers(int $batchSize)
    {
        // Fetch our Hash Config
        $hashConfig = ($this->call('GET', 'https://identitytoolkit.googleapis.com/admin/v2/projects/' . $this->projectID . '/config'))["signIn"]["hashConfig"];

        $nextPageToken = null;

        // Transfer Users
        while (true) {
            $users = [];

            $request = [
                "targetProjectId" => $this->projectID,
                "maxResults" => $batchSize,
            ];

            if ($nextPageToken) {
                $request["nextPageToken"] = $nextPageToken;
            }

            $response = $this->call('POST', 'https://identitytoolkit.googleapis.com/identitytoolkit/v3/relyingparty/downloadAccount', [
                'Content-Type' => 'application/json',
            ], $request);

            $result = $response["users"];
            $nextPageToken = $response["nextPageToken"] ?? null;

            foreach ($result as $user) {
                $users[] = new User(
                    $user["localId"] ?? '',
                    $user["email"] ?? '',
                    $user["displayName"] ?? $user["email"] ?? '',
                    new Hash($user["passwordHash"] ?? '', $user["salt"] ?? '', Hash::SCRYPT_MODIFIED, $hashConfig["saltSeparator"], $hashConfig["signerKey"]),
                    $user["phoneNumber"] ?? '',
                    $this->calculateUserType($user['providerUserInfo'] ?? []),
                    '',
                    $user["emailVerified"],
                    false, // Can't get phone number status on firebase :/
                    $user["disabled"]
                );
            }

            $this->callback($users);

            if (count($result) < $batchSize) {
                break;
            }
        }
    }

    function calculateUserType(array $providerData): array
    {
        if (count($providerData) === 0) {
            return [User::TYPE_ANONYMOUS];
        }

        $types = [];

        foreach ($providerData as $provider) {
            switch ($provider["providerId"]) {
                case 'password':
                    $types[] = User::TYPE_EMAIL;
                    break;
                case 'phone':
                    $types[] = User::TYPE_PHONE;
                    break;
                default:
                    $types[] = User::TYPE_OAUTH;
                    break;
            }
        }

        return $types;
    }

    public function exportDatabasesGroup(int $batchSize, array $resources)
    {
        if (in_array(Resource::TYPE_DATABASE, $resources)) {
            $database = new Database('default', '(default)');
            $this->callback([$database]);
        }

        if (in_array(Resource::TYPE_COLLECTION, $resources)) {
            $this->handleDBData($batchSize, in_array(Resource::TYPE_DOCUMENT, $resources), $database);
        }
    }

    function handleDBData(int $batchSize, bool $pushDocuments, Database $database)
    {
        $baseURL = "https://firestore.googleapis.com/v1/{$this->projectID}/databases/(default)";

        $nextPageToken = null;
        $allCollections = [];
        while (true) {
            $collections = [];

            $result = $this->call('POST', $baseURL . ':listCollectionIds', [
                'Content-Type' => 'application/json',
            ], [
                'pageSize' => $batchSize,
                'pageToken' => $nextPageToken
            ]);

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
                $this->handleCollection($collection, $batchSize, $pushDocuments);
            }

            if (count($result['collectionIds']) < $batchSize) {
                break;
            }

            $nextPageToken = $result['nextPageToken'] ?? null;
        }
    }

    function convertAttribute(Collection $collection, string $key, array $field): Attribute
    {
        if (array_key_exists("booleanValue", $field)) {
            return new BoolAttribute($key, $collection, false, false, null);
        } elseif (array_key_exists("bytesValue", $field)) {
            return new StringAttribute($key, $collection, false, false, null, 1000000);
        } elseif (array_key_exists("doubleValue", $field)) {
            return new FloatAttribute($key, $collection, false, false, null);
        } elseif (array_key_exists("integerValue", $field)) {
            return new IntAttribute($key, $collection, false, false, null);
        } elseif (array_key_exists("mapValue", $field)) {
            return new StringAttribute($key, $collection, false, false, null, 1000000);
        } elseif (array_key_exists("nullValue", $field)) {
            return new StringAttribute($key, $collection, false, false, null, 1000000);
        } elseif (array_key_exists("referenceValue", $field)) {
            return new StringAttribute($key, $collection, false, false, null, 1000000); //TODO: This should be a reference attribute
        } elseif (array_key_exists("stringValue", $field)) {
            return new StringAttribute($key, $collection, false, false, null, 1000000);
        } elseif (array_key_exists("timestampValue", $field)) {
            return new DateTimeAttribute($key, $collection, false, false, null);
        } elseif (array_key_exists("geoPointValue", $field)) {
            return new StringAttribute($key, $collection, false, false, null, 1000000);
        } elseif (array_key_exists("arrayValue", $field)) {
            return $this->calculateArrayType($collection, $key, $field["arrayValue"]);
        } else {
            throw new \Exception('Unknown field type');
        }
    }

    function calculateArrayType(Collection $collection, string $key, array $data): Attribute
    {
        $isSameType = true;
        $previousType = null;

        foreach ($data["values"] as $field) {
            if (!$previousType) {
                $previousType = $this->convertAttribute($collection, $key, $field);
            } elseif ($previousType->getName() != ($this->convertAttribute($collection, $key, $field))->getName()) {
                $isSameType = false;
                break;
            }
        }

        if ($isSameType) {
            $previousType->setArray(true);
            return $previousType;
        } else {
            return new StringAttribute($key, $collection, false, true, null, 1000000);
        }
    }

    function handleCollection(Collection $collection, int $batchSize, bool $transferDocuments)
    {
        $resourceURL = 'https://firestore.googleapis.com/v1/projects/' . $this->projectID . '/databases/' . $collection->getDatabase()->getId() . '/documents/' . $collection->getId();

        $nextPageToken = null;

        $knownSchema = [];

        // Transfer Documents and Calculate Schemas
        while (true) {
            $documents = [];

            $result = $this->call('GET', $resourceURL, [
                'Content-Type' => 'application/json',
            ], [
                'pageSize' => $batchSize,
                'pageToken' => $nextPageToken
            ]);

            if (empty($result)) {
                break;
            }

            // Calculate Schema and handle subcollections
            $documentSchema = [];
            foreach ($result['documents'] as $document) {
                if (!isset($document['fields'])) {
                    continue; //TODO: Transfer Empty Documents
                }

                foreach ($document['fields'] as $key => $field) {
                    if (!isset($documentSchema[$key])) {
                        $documentSchema[$key] = $this->convertAttribute($collection, $key, $field);
                    }
                }

                $documents[] = $this->convertDocument($collection, $document);
            }

            // Transfer Documents   
            if ($transferDocuments) {
                $this->callback($documents);
            }

            if (count($result['documents']) < $batchSize) {
                break;
            }

            $nextPageToken = $result['nextPageToken'] ?? null;
        }
    }

    function calculateValue(array $field)
    {
        if (array_key_exists("booleanValue", $field)) {
            return $field['booleanValue'];
        } elseif (array_key_exists("bytesValue", $field)) {
            return $field['bytesValue'];
        } elseif (array_key_exists("doubleValue", $field)) {
            return $field['doubleValue'];
        } elseif (array_key_exists("integerValue", $field)) {
            return $field['integerValue'];
        } elseif (array_key_exists("mapValue", $field)) {
            return $field['mapValue'];
        } elseif (array_key_exists("nullValue", $field)) {
            return $field['nullValue'];
        } elseif (array_key_exists("referenceValue", $field)) {
            return $field['referenceValue']; //TODO: This should be a reference attribute
        } elseif (array_key_exists("stringValue", $field)) {
            return $field['stringValue'];
        } elseif (array_key_exists("timestampValue", $field)) {
            return $field['timestampValue'];
        } elseif (array_key_exists("geoPointValue", $field)) {
            return $field['geoPointValue'];
        } elseif (array_key_exists("arrayValue", $field)) {
            //TODO: 
        } else {
            throw new \Exception('Unknown field type');
        }
    }

    function convertDocument(Collection $collection, array $document): Document
    {
        $data = [];
        foreach ($document['fields'] as $key => $field) {
            $data[$key] = $this->calculateValue($field);
        }

        return new Document($document['name'], $collection->getDatabase(), $collection, $data, []);
    }

    public function exportStorageGroup(int $batchSize, array $resources)
    {
        if (in_array(Resource::TYPE_BUCKET, $resources))
            $this->exportBuckets($batchSize);

        if (in_array(Resource::TYPE_FILE, $resources))
            $this->exportFiles($batchSize);
    }

    public function exportBuckets(int $batchsize)
    {
        $endpoint = 'https://storage.googleapis.com/storage/v1/b';

        $nextPageToken = null;

        while (true) {
            $result = $this->call('GET', $endpoint, [], [
                'project' => $this->projectID,
                'maxResults' => $batchsize,
                'pageToken' => $nextPageToken,
                'alt' => 'json'
            ]);

            if (empty($result)) {
                break;
            }

            foreach ($result['items'] as $bucket) {
                $this->callback([new Bucket($bucket['id'], [], false, $bucket['name'])]);
            }

            if (!isset($result['nextPageToken'])) {
                break;
            }

            $nextPageToken = $result['nextPageToken'] ?? null;
        }
    }

    public function exportFiles(int $batchsize)
    {
        $buckets = $this->resourceCache->get(Bucket::getName());

        foreach ($buckets as $bucket) {
            $endpoint = 'https://storage.googleapis.com/storage/v1/b/' . $bucket->getId() . '/o';

            $nextPageToken = null;

            while (true) {
                $result = $this->call('GET', $endpoint, [
                    'Content-Type' => 'application/json',
                ], [
                    'pageSize' => $batchsize,
                    'pageToken' => $nextPageToken
                ]);

                if (empty($result)) {
                    break;
                }

                if (!isset($result['items'])) {
                    break;
                }

                foreach ($result['items'] as $item) {
                    $this->handleDataTransfer(new File($item['name'], $bucket, $item['name']));
                }

                if (count($result['items']) < $batchsize) {
                    break;
                }

                $nextPageToken = $result['nextPageToken'] ?? null;
            }
        }
    }

    public function handleDataTransfer(File $file)
    {
        $endpoint = 'https://storage.googleapis.com/storage/v1/b/' . $file->getBucket()->getId() . '/o/' . $file->getId() . '?alt=media';
        $start = 0;
        $end = Transfer::STORAGE_MAX_CHUNK_SIZE - 1;

        while (true) {
            $result = $this->call('GET', $endpoint, [
                'Range' => 'bytes=' . $start . '-' . $end
            ]);

            if (empty($result)) {
                break;
            }

            $this->callback([new FileData(
                $result,
                $start,
                $end,
                $file
            )]);

            if (strlen($result) < Transfer::STORAGE_MAX_CHUNK_SIZE) {
                break;
            }

            $start += Transfer::STORAGE_MAX_CHUNK_SIZE;
            $end += Transfer::STORAGE_MAX_CHUNK_SIZE;
        }
    }

    public function exportFunctionsGroup(int $batchSize, array $resources)
    {
        throw new \Exception('Not implemented');
    }
}
