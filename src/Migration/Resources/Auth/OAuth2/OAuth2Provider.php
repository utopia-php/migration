<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;
use Utopia\Migration\Transfer;

/**
 * Base class for per-provider OAuth2 migration resources. One concrete subclass
 * per provider id (Google, Apple, GitHub, …). Each subclass:
 *
 * - Declares its own `Resource::TYPE_OAUTH2_*` type constant via getName()
 * - Carries the provider-specific non-secret fields readable from the source
 *   (clientId/serviceId/endpoint/tenant/prompt/keyId/teamId/…)
 * - Leaves the actual secret (clientSecret / p8File) unmigrated — destination
 *   admin must re-enter it post-migration
 *
 * @phpstan-consistent-constructor
 */
abstract class OAuth2Provider extends Resource
{
    /**
     * @param array<string, mixed> $array
     */
    abstract public static function fromArray(array $array): self;

    /**
     * Every OAuth2 provider Resource shares one type name. Per-provider
     * dispatch happens via `instanceof` on the concrete subclass — the type
     * constant exists only to bucket all OAuth2 resources under one status
     * counter (a per-provider TYPE explosion would blow past the 3KB cap on
     * the OSS migration document's `statusCounters` column for projects that
     * select OAuth migration).
     */
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
     * The OAuth2 provider key as stored on the project doc (e.g. 'google',
     * 'apple', 'github'). Used by the destination to compute the
     * `{providerKey}Enabled`/`{providerKey}Appid`/`{providerKey}Secret`
     * storage attribute names.
     */
    abstract public static function getProviderKey(): string;

    public function getEnabled(): bool
    {
        return $this->enabled;
    }
}
