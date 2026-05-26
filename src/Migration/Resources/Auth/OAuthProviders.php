<?php

namespace Utopia\Migration\Resources\Auth;

use Utopia\Migration\Resource;
use Utopia\Migration\Transfer;

/**
 * Singleton resource representing the project's OAuth2 provider configurations.
 * One per project; destination merges entries into the project doc's `oAuthProviders`
 * map. Secrets are never migrated — the source API returns them empty, and the
 * destination user must re-enter them post-migration.
 */
class OAuthProviders extends Resource
{
    /**
     * @param array<array{key: string, enabled: bool, appId: string}> $providers
     */
    public function __construct(
        string $id,
        private readonly array $providers = [],
        string $createdAt = '',
        string $updatedAt = '',
    ) {
        $this->id = $id;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    /**
     * @param array<string, mixed> $array
     */
    public static function fromArray(array $array): self
    {
        $providers = [];
        foreach ($array['providers'] ?? [] as $provider) {
            $providers[] = [
                'key' => (string) ($provider['key'] ?? ''),
                'enabled' => (bool) ($provider['enabled'] ?? false),
                'appId' => (string) ($provider['appId'] ?? ''),
            ];
        }

        return new self(
            $array['id'],
            $providers,
            createdAt: $array['createdAt'] ?? '',
            updatedAt: $array['updatedAt'] ?? '',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'providers' => $this->providers,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }

    public static function getName(): string
    {
        return Resource::TYPE_OAUTH_PROVIDERS;
    }

    public function getGroup(): string
    {
        return Transfer::GROUP_AUTH;
    }

    /**
     * @return array<array{key: string, enabled: bool, appId: string}>
     */
    public function getProviders(): array
    {
        return $this->providers;
    }
}
