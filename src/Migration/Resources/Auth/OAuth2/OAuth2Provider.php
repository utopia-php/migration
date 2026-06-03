<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;
use Utopia\Migration\Transfer;

/**
 * OAuth2 provider secrets are write-only and are not migrated.
 */
final class OAuth2Provider extends Resource
{
    private const TARGET_APP_ID = 'appId';
    private const TARGET_SECRET = 'secret';

    /**
     * Allow-list of readable provider fields that are safe to migrate.
     *
     * @var array<string, array<string, array{target: string, key?: string}>>
     */
    public const PROVIDERS = [
        'amazon' => ['clientId' => ['target' => self::TARGET_APP_ID]],
        'apple' => [
            'serviceId' => ['target' => self::TARGET_APP_ID],
            'keyId' => ['target' => self::TARGET_SECRET, 'key' => 'keyID'],
            'teamId' => ['target' => self::TARGET_SECRET, 'key' => 'teamID'],
        ],
        'auth0' => ['clientId' => ['target' => self::TARGET_APP_ID], 'endpoint' => ['target' => self::TARGET_SECRET]],
        'authentik' => ['clientId' => ['target' => self::TARGET_APP_ID], 'endpoint' => ['target' => self::TARGET_SECRET]],
        'autodesk' => ['clientId' => ['target' => self::TARGET_APP_ID]],
        'bitbucket' => ['clientId' => ['target' => self::TARGET_APP_ID]],
        'bitly' => ['clientId' => ['target' => self::TARGET_APP_ID]],
        'box' => ['clientId' => ['target' => self::TARGET_APP_ID]],
        'dailymotion' => ['clientId' => ['target' => self::TARGET_APP_ID]],
        'discord' => ['clientId' => ['target' => self::TARGET_APP_ID]],
        'disqus' => ['clientId' => ['target' => self::TARGET_APP_ID]],
        'dropbox' => ['clientId' => ['target' => self::TARGET_APP_ID]],
        'etsy' => ['clientId' => ['target' => self::TARGET_APP_ID]],
        'facebook' => ['clientId' => ['target' => self::TARGET_APP_ID]],
        'figma' => ['clientId' => ['target' => self::TARGET_APP_ID]],
        'fusionauth' => ['clientId' => ['target' => self::TARGET_APP_ID], 'endpoint' => ['target' => self::TARGET_SECRET]],
        'github' => ['clientId' => ['target' => self::TARGET_APP_ID]],
        'gitlab' => ['clientId' => ['target' => self::TARGET_APP_ID], 'endpoint' => ['target' => self::TARGET_SECRET]],
        'google' => ['clientId' => ['target' => self::TARGET_APP_ID], 'prompt' => ['target' => self::TARGET_SECRET]],
        'keycloak' => ['clientId' => ['target' => self::TARGET_APP_ID], 'endpoint' => ['target' => self::TARGET_SECRET]],
        'kick' => ['clientId' => ['target' => self::TARGET_APP_ID]],
        'linkedin' => ['clientId' => ['target' => self::TARGET_APP_ID]],
        'microsoft' => ['clientId' => ['target' => self::TARGET_APP_ID], 'tenant' => ['target' => self::TARGET_SECRET]],
        'notion' => ['clientId' => ['target' => self::TARGET_APP_ID]],
        'oidc' => ['clientId' => ['target' => self::TARGET_APP_ID], 'endpoint' => ['target' => self::TARGET_SECRET]],
        'okta' => ['clientId' => ['target' => self::TARGET_APP_ID], 'endpoint' => ['target' => self::TARGET_SECRET]],
        'paypal' => ['clientId' => ['target' => self::TARGET_APP_ID]],
        'paypalSandbox' => ['clientId' => ['target' => self::TARGET_APP_ID]],
        'podio' => ['clientId' => ['target' => self::TARGET_APP_ID]],
        'salesforce' => ['clientId' => ['target' => self::TARGET_APP_ID]],
        'slack' => ['clientId' => ['target' => self::TARGET_APP_ID]],
        'spotify' => ['clientId' => ['target' => self::TARGET_APP_ID]],
        'stripe' => ['clientId' => ['target' => self::TARGET_APP_ID]],
        'tradeshift' => ['clientId' => ['target' => self::TARGET_APP_ID]],
        'tradeshiftBox' => ['clientId' => ['target' => self::TARGET_APP_ID]],
        'twitch' => ['clientId' => ['target' => self::TARGET_APP_ID]],
        'wordpress' => ['clientId' => ['target' => self::TARGET_APP_ID]],
        'x' => ['clientId' => ['target' => self::TARGET_APP_ID]],
        'yahoo' => ['clientId' => ['target' => self::TARGET_APP_ID]],
        'yandex' => ['clientId' => ['target' => self::TARGET_APP_ID]],
        'zoho' => ['clientId' => ['target' => self::TARGET_APP_ID]],
        'zoom' => ['clientId' => ['target' => self::TARGET_APP_ID]],
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
        foreach (\array_keys($allowed) as $field) {
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

    public function getDestinationAppId(): mixed
    {
        foreach ($this->getDescriptor() as $field => $metadata) {
            if ($metadata['target'] === self::TARGET_APP_ID && \array_key_exists($field, $this->settings)) {
                return $this->settings[$field];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDestinationSecretFields(): array
    {
        $fields = [];
        foreach ($this->getDescriptor() as $field => $metadata) {
            if ($metadata['target'] !== self::TARGET_SECRET || !\array_key_exists($field, $this->settings)) {
                continue;
            }

            $value = $this->settings[$field];
            if (self::isEmpty($value)) {
                continue;
            }

            $fields[$metadata['key'] ?? $field] = $value;
        }

        return $fields;
    }

    public function isConfigured(): bool
    {
        return $this->enabled || !self::isEmpty($this->getDestinationAppId());
    }

    /**
     * @return array<string, array{target: string, key?: string}>
     */
    private function getDescriptor(): array
    {
        return self::PROVIDERS[$this->providerKey] ?? [];
    }

    private static function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}
