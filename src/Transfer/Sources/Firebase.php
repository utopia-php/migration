<?php

namespace Utopia\Transfer\Sources;

use Utopia\Transfer\Source;
use Google\Client;
use Utopia\Transfer\Resources\Project;
use Utopia\Transfer\Resources\User;
use Utopia\Transfer\Transfer;
use Kreait\Firebase\Factory;
use Utopia\Transfer\Hash;

class Firebase extends Source
{
    const AUTH_OAUTH = 'oauth';
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
     * @var Factory|null
     */
    protected $firebaseAdminClient = null;

    /**
     * Constructor
     * 
     * @param array $authObject Service Account Credentials for AUTH_SERVICEACCOUNT 
     * @param string $authType Can be either Firebase::AUTH_OAUTH or Firebase::AUTH_APIKEY
     */
    function __construct(array $authObject = [], string $authType = self::AUTH_SERVICEACCOUNT)
    {
        if (!in_array($authType, [self::AUTH_OAUTH, self::AUTH_SERVICEACCOUNT])) {
            throw new \Exception('Invalid authentication type');
        }

        $this->googleClient = new Client();

        if ($authType === self::AUTH_OAUTH) {
            $this->googleClient->setAccessToken($authObject);
        } else if ($authType === self::AUTH_SERVICEACCOUNT) {
            $this->googleClient->setAuthConfig($authObject);
        }

        $this->googleClient->addScope('https://www.googleapis.com/auth/firebase');
        $this->googleClient->addScope('https://www.googleapis.com/auth/cloud-platform');

        $this->firebaseAdminClient = (new Factory)->withServiceAccount($authObject);
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
     * Export Users
     * 
     * @param int $chunk
     * @returns list<Utopia\Transfer\Resources\User>
     */
    public function exportUsers(int $chunk = 1000)
    {
        $users = [];

        $auth = $this->firebaseAdminClient->createAuth();

        $result = $auth->listUsers($chunk);

        // Fetch our hash config

        $httpClient = $this->googleClient->authorize();

        $hashConfig = json_decode($httpClient->request('GET', 'https://identitytoolkit.googleapis.com/admin/v2/projects/amadeus-a5bcc/config')->getBody()->getContents(), true)["signIn"]["hashConfig"];

        foreach($result as $user) {
            /** @var \Kreait\Firebase\Auth\UserRecord $user */
    
            // Figure out what type of user it is.
            $type = $user->providerData[0]->providerId ?? 'Anonymous';

            switch ($type) {
                case 'password': {
                    $users[] = new User(
                        $user->uid,
                        $user->email,
                        new Hash($user->passwordHash, $user->passwordSalt, Hash::SCRYPT_MODIFIED, $hashConfig["saltSeparator"], $hashConfig["signerKey"]),
                        '',
                        User::AUTH_EMAIL
                    );
                    break;
                }
                case 'phone': {
                    $users[] = new User(
                        $user->uid,
                        '',
                        new Hash(''),
                        $user->phoneNumber,
                        User::AUTH_PHONE
                    );
                    break;
                }
                case 'Anonymous': {
                    $users[] = new User(
                        $user->uid,
                        '',
                        new Hash(''),
                        '',
                        User::AUTH_ANONYMOUS
                    );
                    break;
                }
                default: {
                    $users[] = new User(
                        $user->uid,
                        '',
                        new Hash(''),
                        '',
                        User::AUTH_OAUTH,
                        $type
                    );

                    break;
                }
            }
        }

        return $users;

    }

    function check(array $resources = []): bool
    {
        return true;
    }
}