<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;
use Utopia\Migration\Transfer;

/**
 * OAuth2 provider secrets are write-only and are not migrated.
 */
final class OAuth2Provider extends Resource
{
    /**
     * Allow-list of readable provider fields that are safe to migrate.
     *
     * @var array<string, array<string>>
     */
    public const PROVIDERS = [
        'amazon' => ['clientId'],
        'apple' => ['serviceId', 'keyId', 'teamId'],
        'auth0' => ['clientId', 'endpoint'],
        'authentik' => ['clientId', 'endpoint'],
        'autodesk' => ['clientId'],
        'bitbucket' => ['clientId'],
        'bitly' => ['clientId'],
        'box' => ['clientId'],
        'dailymotion' => ['clientId'],
        'discord' => ['clientId'],
        'disqus' => ['clientId'],
        'dropbox' => ['clientId'],
        'etsy' => ['clientId'],
        'facebook' => ['clientId'],
        'figma' => ['clientId'],
        'fusionauth' => ['clientId', 'endpoint'],
        'github' => ['clientId'],
        'gitlab' => ['clientId', 'endpoint'],
        'google' => ['clientId', 'prompt'],
        'keycloak' => ['clientId', 'endpoint'],
        'kick' => ['clientId'],
        'linkedin' => ['clientId'],
        'microsoft' => ['clientId', 'tenant'],
        'notion' => ['clientId'],
        'oidc' => ['clientId', 'endpoint'],
        'okta' => ['clientId', 'endpoint'],
        'paypal' => ['clientId'],
        'paypalSandbox' => ['clientId'],
        'podio' => ['clientId'],
        'salesforce' => ['clientId'],
        'slack' => ['clientId'],
        'spotify' => ['clientId'],
        'stripe' => ['clientId'],
        'tradeshift' => ['clientId'],
        'tradeshiftBox' => ['clientId'],
        'twitch' => ['clientId'],
        'wordpress' => ['clientId'],
        'x' => ['clientId'],
        'yahoo' => ['clientId'],
        'yandex' => ['clientId'],
        'zoho' => ['clientId'],
        'zoom' => ['clientId'],
    ];

    public function __construct(
        string $id,
        protected readonly string $providerKey,
        protected readonly bool $enabled = false,
        protected readonly array $settings = [],
        string $createdAt = '',
        string $updatedAt = '',
    ) {
        $this->id = $id;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_PROVIDER;
    }

    public function getGroup(): string
    {
        return Transfer::GROUP_AUTH;
    }

    /**
     * @param array<string, mixed> $array
     */
    public static function fromArray(string $providerKey, array $array): ?self
    {
        $allowed = self::PROVIDERS[$providerKey] ?? null;
        if ($allowed === null) {
            return null;
        }

        $settings = [];
        foreach ($allowed as $field) {
            if (\array_key_exists($field, $array)) {
                $settings[$field] = $array[$field];
            }
        }

        return new self(
            $array['id'],
            $providerKey,
            (bool) ($array['enabled'] ?? false),
            $settings,
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
            'providerKey' => $this->providerKey,
            'enabled' => $this->enabled,
            'settings' => $this->settings,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }

    public function getProviderKey(): string
    {
        return $this->providerKey;
    }

    public function getEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        return $this->settings;
    }

    public function getSetting(string $field): mixed
    {
        return $this->settings[$field] ?? null;
    }

    public function isConfigured(): bool
    {
        return $this->enabled
            || ($this->settings['clientId'] ?? '') !== ''
            || ($this->settings['serviceId'] ?? '') !== '';
    }
}
