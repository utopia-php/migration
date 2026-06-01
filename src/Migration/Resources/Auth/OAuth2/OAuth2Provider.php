<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;
use Utopia\Migration\Transfer;

/**
 * Base class for per-provider OAuth2 migration resources — one concrete subclass
 * per provider id (Google, Apple, GitHub, …), each carrying that provider's
 * readable non-secret fields (clientId/serviceId/endpoint/tenant/prompt/…). The
 * secret (clientSecret / p8File) is write-only on the source, so it is not
 * migrated — the destination admin must re-enter it post-migration.
 *
 * All subclasses share Resource::TYPE_OAUTH2_PROVIDER; the destination dispatches
 * per provider via `instanceof` on the concrete subclass.
 *
 * @phpstan-consistent-constructor
 */
abstract class OAuth2Provider extends Resource
{
    /**
     * @param array<string, mixed> $array
     */
    abstract public static function fromArray(array $array): self;

    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_PROVIDER;
    }

    public function __construct(
        string $id,
        protected readonly bool $enabled = false,
        string $createdAt = '',
        string $updatedAt = '',
    ) {
        $this->id = $id;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function getGroup(): string
    {
        return Transfer::GROUP_AUTH;
    }

    /**
     * Provider key as stored on the project doc (e.g. 'google', 'apple'). The
     * destination derives the `{providerKey}Enabled/Appid/Secret` attribute
     * names from it.
     */
    abstract public static function getProviderKey(): string;

    public function getEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Whether the project actually set this provider up — `listOAuth2Providers`
     * returns every supported provider, but only configured ones are migrated.
     * Subclasses also count a set-but-disabled appId.
     */
    public function isConfigured(): bool
    {
        return $this->enabled;
    }
}
