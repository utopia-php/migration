<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

/**
 * Abstract base for providers that add an `endpoint` URL on top of the
 * standard `clientId` field. Covers Auth0, Authentik, FusionAuth, Gitlab,
 * Keycloak, Oidc, Okta — concrete subclasses only need to declare
 * `getName()` + `getProviderKey()`.
 *
 * @phpstan-consistent-constructor
 */
abstract class WithEndpointProvider extends StandardProvider
{
    public function __construct(
        string $id,
        bool $enabled,
        string $clientId = '',
        protected readonly string $endpoint = '',
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
        return new static(
            $array['id'],
            (bool) ($array['enabled'] ?? false),
            (string) ($array['clientId'] ?? ''),
            (string) ($array['endpoint'] ?? ''),
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
            'endpoint' => $this->endpoint,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }
}
