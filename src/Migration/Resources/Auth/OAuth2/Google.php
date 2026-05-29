<?php

namespace Utopia\Migration\Resources\Auth\OAuth2;

use Utopia\Migration\Resource;

/**
 * Google OAuth2 provider. Standard `clientId` plus an array of OAuth `prompt`
 * modes (consent / none / select_account).
 */
class Google extends StandardProvider
{
    /**
     * @param array<string> $prompt
     */
    public function __construct(
        string $id,
        bool $enabled,
        string $clientId = '',
        private readonly array $prompt = [],
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
            (array) ($array['prompt'] ?? []),
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
            'prompt' => $this->prompt,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }

    public static function getName(): string
    {
        return Resource::TYPE_OAUTH2_GOOGLE;
    }

    public static function getProviderKey(): string
    {
        return 'google';
    }

    /**
     * @return array<string>
     */
    public function getPrompt(): array
    {
        return $this->prompt;
    }
}
