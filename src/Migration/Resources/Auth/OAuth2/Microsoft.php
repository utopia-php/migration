<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

/**
 * Microsoft OAuth2 provider. Standard `clientId` plus a `tenant` field
 * identifying the Azure AD tenant.
 */
class Microsoft extends StandardProvider
{
    public function __construct(
        string $id,
        bool $enabled,
        string $clientId = '',
        private readonly string $tenant = '',
        string $createdAt = '',
        string $updatedAt = '',
    ) {
        parent::__construct($id, $enabled, $clientId, $createdAt, $updatedAt);
    }

    /**
     * @param array<string, mixed> $array
     */
    public static function fromArray(array $array): self
    {
        return new self(
            $array['id'],
            (bool) ($array['enabled'] ?? false),
            (string) ($array['clientId'] ?? ''),
            (string) ($array['tenant'] ?? ''),
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
            'clientId' => $this->clientId,
            'tenant' => $this->tenant,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }

    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_MICROSOFT;
    }

    public static function getProviderKey(): string
    {
        return 'microsoft';
    }

    public function getTenant(): string
    {
        return $this->tenant;
    }
}
