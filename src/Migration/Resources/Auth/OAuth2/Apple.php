<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

/**
 * Apple OAuth2 provider. Bespoke shape — the credential is split across four
 * fields. `serviceId`/`keyId`/`teamId` are readable on the source and
 * migrated; `p8File` is write-only and must be re-entered on the destination.
 */
class Apple extends OAuth2Provider
{
    public function __construct(
        string $id,
        bool $enabled,
        private readonly string $serviceId = '',
        private readonly string $keyId = '',
        private readonly string $teamId = '',
        string $createdAt = '',
        string $updatedAt = '',
    ) {
        parent::__construct($id, $enabled, $createdAt, $updatedAt);
    }

    /**
     * @param array<string, mixed> $array
     */
    public static function fromArray(array $array): self
    {
        return new self(
            $array['id'],
            (bool) ($array['enabled'] ?? false),
            (string) ($array['serviceId'] ?? ''),
            (string) ($array['keyId'] ?? ''),
            (string) ($array['teamId'] ?? ''),
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
            'enabled' => $this->enabled,
            'serviceId' => $this->serviceId,
            'keyId' => $this->keyId,
            'teamId' => $this->teamId,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }

    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_APPLE;
    }

    public static function getProviderKey(): string
    {
        return 'apple';
    }

    public function getServiceId(): string
    {
        return $this->serviceId;
    }

    public function getKeyId(): string
    {
        return $this->keyId;
    }

    public function getTeamId(): string
    {
        return $this->teamId;
    }
}
