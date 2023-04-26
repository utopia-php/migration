<?php

namespace Utopia\Transfer\Sources;

use Utopia\Transfer\Source;
use Google\Client;
use Google\Service\Firestore\ListCollectionIdsRequest;
use Utopia\Transfer\Resources\Project;
use Utopia\Transfer\Resources\User;
use Utopia\Transfer\Transfer;
use Utopia\Transfer\Log;
use Utopia\Transfer\Resources\Database\Attribute as ResourcesAttribute;
use Utopia\Transfer\Resources\Database\Attributes\BoolAttribute;
use Utopia\Transfer\Resources\Database\Attributes\DateTimeAttribute;
use Utopia\Transfer\Resources\Database\Attributes\FloatAttribute;
use Utopia\Transfer\Resources\Database\Attributes\IntAttribute;
use Utopia\Transfer\Resources\Database\Attributes\StringAttribute;
use Utopia\Transfer\Resources\Database\Collection;
use Utopia\Transfer\Resources\Database;
use Utopia\Transfer\Resources\Auth\Hash;

class Firebase extends Source
{
    public const TYPE_OAUTH = 'oauth';
    public const AUTH_SERVICEACCOUNT = 'serviceaccount';

    /**
     * @var array{object: array, type: string}
     */
    protected $authentication = [
        'type' => self::AUTH_SERVICEACCOUNT,
        'object' => []
    ];

    /**
     * @var Client|null
     */
    protected $googleClient = null;

    /**
     * @var Project|null
     */
    protected $project;

    /**
     * Constructor
     *
     * @param array $authObject Service Account Credentials for AUTH_SERVICEACCOUNT
     * @param string $authType Can be either Firebase::TYPE_OAUTH or Firebase::AUTH_APIKEY
     */
    public function __construct(array $authObject = [], string $authType = self::AUTH_SERVICEACCOUNT)
    {
        if (!in_array($authType, [self::TYPE_OAUTH, self::AUTH_SERVICEACCOUNT])) {
            throw new \Exception('Invalid authentication type');
        }

        $this->googleClient = new Client();

        if ($authType === self::TYPE_OAUTH) {
            $this->googleClient->setAccessToken($authObject);
        } elseif ($authType === self::AUTH_SERVICEACCOUNT) {
            $this->googleClient->setAuthConfig($authObject);
        }

        $this->googleClient->addScope('https://www.googleapis.com/auth/firebase');
        $this->googleClient->addScope('https://www.googleapis.com/auth/cloud-platform');
    }

    public function getName(): string
    {
        return 'Firebase';
    }

    public function getSupportedResources(): array
    {
        return [
            Transfer::GROUP_AUTH,
            Transfer::GROUP_DATABASES
        ];
    }

    public function getProjects(): array
    {
        $projects = [];

        if (!$this->googleClient) {
            throw new \Exception('Google Client not initialized');
        }

        $firebase = new \Google\Service\FirebaseManagement($this->googleClient);

        $request = $firebase->projects->listProjects();

        if ($request['results']) {
            foreach ($request['results'] as $project) {
                $projects[] = new Project(
                    $project['displayName'],
                    $project['projectId']
                );
            }
        }

        return $projects;
    }

    /**
     * Set Project
     *
     * @param Project|string $project
     */
    public function setProject(Project|string $project): void
    {
        if (is_string($project)) {
            $project = new Project($project, $project);
        }

        $this->project = $project;
    }

    /**
     * Get Project
     *
     * @return Project|null
     */
    public function getProject(): Project|null
    {
        return $this->project;
    }

    /**
     * Export Users
     *
     * @param int $batchSize Max 500
     * @param callable $callback Callback function to be called after each batch, $callback(user[] $batch);
     *
     * @return void
     */
    public function exportAuth(int $batchSize, callable $callback): void
    {
        if (!$this->project || !$this->project->getId()) {
            $this->logs[Log::FATAL][] = new Log('Project not set');
            throw new \Exception('Project not set');
        }

        if ($batchSize > 500) {
            $this->logs[Log::FATAL][] = new Log('Batch size cannot be greater than 500');
            throw new \Exception('Batch size cannot be greater than 500');
        }

        // Fetch our hash config
        $httpClient = $this->googleClient->authorize();

        $hashConfig = json_decode($httpClient->request('GET', 'https://identitytoolkit.googleapis.com/admin/v2/projects/' . $this->project->getId() . '/config')->getBody()->getContents(), true)["signIn"]["hashConfig"];

        if (!$hashConfig) {
            $this->logs[Log::FATAL][] = new Log('Unable to fetch hash config');
            throw new \Exception('Unable to fetch hash config');
        }

        $nextPageToken = null;

        while (true) {
            $users = [];

            $request = [
                "targetProjectId" => $this->project->getId(),
                "maxResults" => $batchSize,
            ];

            if ($nextPageToken) {
                $request["nextPageToken"] = $nextPageToken;
            }

            $response = json_decode($httpClient->request('POST', 'https://identitytoolkit.googleapis.com/identitytoolkit/v3/relyingparty/downloadAccount', [
                'json' => $request
            ])->getBody()->getContents(), true);

            if (!$response) {
                $this->logs[Log::FATAL][] = new Log('Unable to fetch users');
                throw new \Exception('Unable to fetch users');
            }

            $result = $response["users"];
            $nextPageToken = $response["nextPageToken"] ?? null;

            foreach ($result as $user) {
                /** @var array $user */

                $users[] = new User(
                    $user["localId"] ?? '',
                    $user["email"] ?? '',
                    $user["displayName"] ?? $user["email"] ?? '',
                    new Hash($user["passwordHash"] ?? '', $user["salt"] ?? '', Hash::SCRYPT_MODIFIED, $hashConfig["saltSeparator"], $hashConfig["signerKey"]),
                    $user["phoneNumber"] ?? '',
                    $this->calculateTypes($user['providerUserInfo'] ?? []),
                    '',
                    $user["emailVerified"],
                    false, // Can't get phone number status on firebase :/
                    $user["disabled"]
                );
            }

            $callback($users);

            if (count($result) < $batchSize) {
                break;
            }
        }
    }

    /**
     * Calculate Array Type
     *
     * @param string $key
     * @param \Google\Service\Firestore\ArrayValue $data
     *
     * @return ResourcesAttribute
     */
    public function calculateArrayType(string $key, \Google\Service\Firestore\ArrayValue $data): ResourcesAttribute
    {
        $isSameType = true;
        $previousType = null;

        foreach ($data["values"] as $field) {
            if (!$previousType) {
                $previousType = $this->calculateType($key, $field);
            } elseif ($previousType->getName() != ($this->calculateType($key, $field))->getName()) {
                $isSameType = false;
                break;
            }
        }

        if ($isSameType) {
            $previousType->setArray(true);
            return $previousType;
        } else {
            return new StringAttribute($key, false, true, null, 1000000);
        }
    }

    /**
     * Calculate Type
     *
     * @param string $key
     * @param \Google\Service\Firestore\Value $field
     *
     * @return ResourcesAttribute
     */
    public function calculateType(string $key, \Google\Service\Firestore\Value $field): ResourcesAttribute
    {
        if (isset($field["booleanValue"])) {
            return new BoolAttribute($key, false, false, null);
        } elseif (isset($field["bytesValue"])) {
            return new StringAttribute($key, false, false, null, 1000000);
        } elseif (isset($field["doubleValue"])) {
            return new FloatAttribute($key, false, false, null);
        } elseif (isset($field["integerValue"])) {
            return new IntAttribute($key, false, false, null);
        } elseif (isset($field["mapValue"])) {
            return new StringAttribute($key, false, false, null, 1000000);
        } elseif (isset($field["nullValue"])) {
            return new StringAttribute($key, false, false, null, 1000000);
        } elseif (isset($field["referenceValue"])) {
            return new StringAttribute($key, false, false, null, 1000000);
        } elseif (isset($field["stringValue"])) {
            return new StringAttribute($key, false, false, null, 1000000);
        } elseif (isset($field["timestampValue"])) {
            return new DateTimeAttribute($key, false, false, null);
        } elseif (isset($field["geoPointValue"])) {
            return new StringAttribute($key, false, false, null, 1000000);
        } elseif (isset($field["arrayValue"])) {
            return $this->calculateArrayType($key, $field["arrayValue"]);
        } else {
            $this->logs[Log::WARNING][] = new Log('Failed to determine data type for: ' . $key . ' Falling back to string.', \time());
            return new StringAttribute($key, false, false, null, 1000000);
        }
    }

    /**
     * Calculate Schema
     *
     * @param int $batchSize Max 500
     * @param $collection Collection
     * @param &Collection[] $newCollections
     *
     * @return list<ResourcesAttribute>
     **/
    public function calculateSchema(int $batchSize, Collection $collection, array &$newCollections)
    {
        $attributes = [];

        $firestore = new \Google\Service\Firestore($this->googleClient);

        $documents = $firestore->projects_databases_documents->listDocuments('projects/' . $this->project->getId() . '/databases/(default)/documents/' . $collection->getId(), '', [
            'pageSize' => $batchSize
        ]);

        foreach ($documents as $document) {
            foreach ($document["fields"] as $key => $value) {
                $attributes[] = $this->calculateType($key, $value);
            }

            $requestOptions = new ListCollectionIdsRequest();
            $requestOptions->setPageSize(500);

            // Handle subcollections
            $subcollections = $firestore->projects_databases_documents->listCollectionIds($document["name"], $requestOptions, [])["collectionIds"];

            if ($subcollections == null) {
                continue;
            }

            $subcollections = array_map(function ($subcollection) use ($document, $collection) {
                $name = str_replace("projects/" . $this->getProject()->getId() . "/databases/(default)/documents/", "", $document["name"]);
                return $name . '/' . $subcollection;
            }, $subcollections);

            $newCollections = array_merge($newCollections, $this->handleCollections($subcollections, $collection->getDatabase()));
        }

        return $attributes;
    }

    /**
     * Handle Collections
     *
     * @param string[] $collectionIDs
     * @param Database $database
     *
     * @return Collection[]
     */
    public function handleCollections(array $collectionIDs, Database $database): array
    {
        $collections = [];

        foreach ($collectionIDs as $collectionID) {
            $collection = new Collection($database, $collectionID, $collectionID);

            $collection->setAttributes($this->calculateSchema(500, $collection, $collections));

            $collections[] = $collection;
        }

        return $collections;
    }

    /**
     * Export Databases
     *
     * @param int $batchSize Max 500
     * @param callable $callback Callback function to be called after each batch, $callback(database[] $batch);
     *
     * @return void
     */
    public function exportDatabases(int $batchSize, callable $callback): void
    {
        if (!$this->project || !$this->project->getId()) {
            $this->logs[Log::FATAL][] = new Log('Project not set');
            throw new \Exception('Project not set');
        }

        if ($batchSize > 500) {
            $this->logs[Log::FATAL][] = new Log('Batch size cannot be greater than 500');
            throw new \Exception('Batch size cannot be greater than 500');
        }

        $firestore = new \Google\Service\Firestore($this->googleClient);

        // Let's grab the root collections. (google's params technically doesn't allow this, however they do it in their own console)
        $request = $firestore->projects_databases_documents->listCollectionIds('projects/' . $this->project->getId() . '/databases/(default)/documents', new ListCollectionIdsRequest());

        $database = new Database('Default', 'Default', Database::DB_NON_RELATIONAL);

        $database->setCollections($this->handleCollections($request['collectionIds'], $database));

        $callback([$database]);
    }

    public function calculateTypes(array $providerData): array
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

        if (!$this->googleClient) {
            $this->logs[Log::FATAL][] = new Log('Google Client not initialized');
            $report['Users'][] = 'Google Client not initialized';
            return $report;
        }

        if (!$this->project || !$this->project->getId()) {
            $this->logs[Log::FATAL][] = new Log('Project not set');
            $report['Users'][] = 'Project not set';
            return $report;
        }

        foreach ($resources as $resource) {
            switch ($resource) {
                case Transfer::GROUP_AUTH:
                    $firebase = new \Google\Service\FirebaseManagement($this->googleClient);

                    $request = $firebase->projects->listProjects();

                    if (!$request['results']) {
                        $report['Users'][] = 'Unable to fetch projects';
                        return $report;
                    }

                    $found = false;

                    foreach ($request['results'] as $project) {
                        if ($project['projectId'] === $this->project->getId()) {
                            $found = true;
                            break;
                        }
                    }

                    if (!$found) {
                        $report['Users'][] = 'Project not found';
                        return $report;
                    }

                    $completedResources[] = Transfer::GROUP_AUTH;
                    break;
                case Transfer::GROUP_DATABASES:
                    $firestore = new \Google\Service\Firestore($this->googleClient);

                    $request = $firestore->projects_databases_documents->listDocuments('projects/' . $this->project->getId() . '/databases/(default)/documents', '', [
                        'pageSize' => 1
                    ]);

                    if (!$request['documents']) {
                        $report['Databases'][] = 'Unable to fetch documents';
                        return $report;
                    }
                    break;
            }
        }

        return $report;
    }

    public function exportDocuments(int $batchSize, callable $callback): void
    {
        throw new \Exception('Not Implemented');
    }

    public function exportFiles(int $batchSize, callable $callback): void
    {
        throw new \Exception('Not Implemented');
    }

    public function exportFunctions(int $batchSize, callable $callback): void
    {
        throw new \Exception('Not Implemented');
    }
}
