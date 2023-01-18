<?php

namespace Utopia\Transfer\Sources;

use Utopia\Transfer\Source;
use Google\Client;
use Utopia\Transfer\Resources\Project;
use Utopia\Transfer\Resources\User;
use Utopia\Transfer\Transfer;
use Utopia\Transfer\Log;
use Utopia\Transfer\Resource;
use Utopia\Transfer\Resources\Hash;

class Firebase extends Source
{
    const TYPE_OAUTH = 'oauth';
    const AUTH_SERVICEACCOUNT = 'serviceaccount';

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
    function __construct(array $authObject = [], string $authType = self::AUTH_SERVICEACCOUNT)
    {
        if (!in_array($authType, [self::TYPE_OAUTH, self::AUTH_SERVICEACCOUNT])) {
            throw new \Exception('Invalid authentication type');
        }

        $this->googleClient = new Client();

        if ($authType === self::TYPE_OAUTH) {
            $this->googleClient->setAccessToken($authObject);
        } else if ($authType === self::AUTH_SERVICEACCOUNT) {
            $this->googleClient->setAuthConfig($authObject);
        }

        $this->googleClient->addScope('https://www.googleapis.com/auth/firebase');
        $this->googleClient->addScope('https://www.googleapis.com/auth/cloud-platform');
    }

    function getName(): string
    {
        return 'Firebase';
    }

    function getSupportedResources(): array
    {
        return [
            Transfer::RESOURCE_USERS
        ];
    }

    function getProjects(): array
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
    function setProject(Project|string $project): void
    {
        if (is_string($project)) {
            $project = new Project($project, $project);
        }

        $this->project = $project;
    }

    /**
     * Get Project
     * 
     * @returns Project|null
     */
    function getProject(): Project|null
    {
        return $this->project;
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

        $count = 0;
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

                $count++;
            }

            $callback($users);

            if (count($result) < $batchSize) {
                break;
            }
        }
    }

    function calculateTypes(array $providerData): array {
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

    function check(array $resources = []): bool
    {
        if (!$this->googleClient) {
            $this->logs[Log::FATAL][] = new Log('Google Client not initialized');
            return false;
        }

        if (!$this->project || !$this->project->getId()) {
            $this->logs[Log::FATAL][] = new Log('Project not set');
            return false;
        }

        foreach ($resources as $resource) {
            switch ($resource)
            {
                case Transfer::RESOURCE_USERS:
                    $firebase = new \Google\Service\FirebaseManagement($this->googleClient);

                    $request = $firebase->projects->listProjects();

                    if (!$request['results']) {
                        $this->logs[Log::FATAL][] = new Log('Unable to fetch projects');
                        return false;
                    }

                    $found = false;

                    foreach ($request['results'] as $project) {
                        if ($project['projectId'] === $this->project->getId()) {
                            $found = true;
                            break;
                        }
                    }

                    if (!$found) {
                        $this->logs[Log::FATAL][] = new Log('Project not found');
                        return false;
                    }

                    break;
            }
        }

        return true;
    }
}
