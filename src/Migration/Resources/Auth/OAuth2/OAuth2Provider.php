<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;
use Utopia\Migration\Transfer;

/**
 * OAuth2 provider migration resource — one class for every provider. The
 * provider key and that provider's readable (non-secret) field names come from
 * the {@see OAuth2Provider::PROVIDERS} map, not from per-provider subclasses.
 *
 * Only non-secret fields are migrated: the handshake secret (clientSecret, or
 * Apple's p8File) is write-only on the source, so it is not carried across — the
 * destination admin must re-enter it post-migration, after which sign-in works.
 *
 * All providers share Resource::TYPE_OAUTH2_PROVIDER; the destination dispatches
 * per provider on the provider key, not on the concrete type.
 */
final class OAuth2Provider extends Resource
{
    /**
     * provider key => the readable, non-secret field names migrated for it.
     *
     * This list doubles as the secret allow-list: only these keys are copied off
     * the (heterogeneous) `listOAuth2Providers` payload, so a secret field the
     * server may add upstream later is never carried across by accident.
     *
     * Destination field routing (see Destinations\Appwrite::createOAuth2Provider):
     *  - `clientId` / `serviceId` (Apple)   -> `{key}Appid`
     *  - `endpoint` / `tenant` / `prompt`   -> merged into the `{key}Secret` JSON blob
     *  - `keyId` / `teamId` (Apple)         -> merged into the Apple secret JSON blob
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

    /**
     * @param array<string, mixed> $settings Non-secret fields, keyed per PROVIDERS[$providerKey].
     */
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
     * Build a provider from a single `listOAuth2Providers` entry, copying only
     * the non-secret fields declared for `$providerKey`. Returns null for a key
     * this lib has no mapping for yet (e.g. a provider added upstream after this
     * release), so callers can report it rather than mis-migrate it.
     *
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

    /**
     * Whether the project actually configured this provider — `listOAuth2Providers`
     * returns every supported provider, but only configured ones are migrated. The
     * app id is `serviceId` for Apple and `clientId` for everyone else.
     */
    public function isConfigured(): bool
    {
        return $this->enabled
            || ($this->settings['clientId'] ?? '') !== ''
            || ($this->settings['serviceId'] ?? '') !== '';
    }
}
