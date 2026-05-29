<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

/**
 * Abstract base for providers whose only non-secret per-project field is
 * `clientId`. Covers the majority of OAuth2 providers — concrete subclasses
 * only need to declare `getName()` + `getProviderKey()`.
 *
 * @phpstan-consistent-constructor
 */
abstract class StandardProvider extends OAuth2Provider
{
    public function __construct(
        string $id,
        bool $enabled,
        protected readonly string $clientId = '',
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
        return new static(
            $array['id'],
            (bool) ($array['enabled'] ?? false),
            (string) ($array['clientId'] ?? ''),
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
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }
}
