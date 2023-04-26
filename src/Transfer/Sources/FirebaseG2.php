<?php

namespace Utopia\Transfer\Sources;

use Utopia\Transfer\Source;
use Utopia\Transfer\Transfer;
use Utopia\Transfer\Resources\Auth\Hash;
use Utopia\Transfer\Resources\Auth\User;

class FirebaseG2 extends Source
{
    private array $serviceAccount;
    private string $projectID;
    private string $currentToken = '';
    private int $tokenExpires = 0;

    public function __construct(array $serviceAccount, string $projectID)
    {
        $this->serviceAccount = $serviceAccount;
        $this->projectID = $projectID;
    }

    public function getName(): string
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
            'scope' => 'https://www.googleapis.com/auth/firebase https://www.googleapis.com/auth/cloud-platform',
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

        $response = $this->call('POST', 'https://oauth2.googleapis.com/token', [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ], [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $this->calculateJWT()
        ]);

        $this->currentToken = $response['access_token'];
        $this->tokenExpires = time() + $response['expires_in'];
        $this->headers['Authorization'] = 'Bearer ' . $this->currentToken;
    }

    public function getSupportedResources(): array
    {
        return [
            Transfer::GROUP_AUTH
        ];
    }

    public function check(array $resources = []): array
    {
        throw new \Exception('Not implemented');
    }

    public function exportAuth(int $batchSize, callable $callback): void
    {
        $this->authenticate();

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

            $callback($users);

            if (count($result) < $batchSize) {
                break;
            }
        }
    }

    public function calculateUserType(array $providerData): array
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

    public function exportFunctions(int $batchSize, callable $callback): void
    {
        throw new \Exception('Not implemented');
    }

    public function exportFiles(int $batchSize, callable $callback): void
    {
        throw new \Exception('Not implemented');
    }

    public function exportDatabases(int $batchSize, callable $callback): void
    {
        $this->authenticate();

        // Transfer Databases
        $databases = [];
        
    }

    public function exportDocuments(int $batchSize, callable $callback): void
    {
        throw new \Exception('Not implemented');
    }
}
