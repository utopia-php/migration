<?php

namespace Utopia\Migration\Resources\Settings;

use Utopia\Migration\Resource;
use Utopia\Migration\Transfer;

class Key extends Resource
{
    /**
     * @param string $id
     * @param string $name
     * @param array<string> $scopes
     * @param string $secret
     * @param string|null $expire
     * @param string|null $accessedAt
     * @param array<string> $sdks
     */
    public function __construct(
        string $id,
        private readonly string $name,
        private readonly array $scopes,
        private readonly string $secret,
        private readonly ?string $expire = null,
        private readonly ?string $accessedAt = null,
        private readonly array $sdks = [],
    ) {
        $this->id = $id;
    }

    /**
     * @param array<string, mixed> $array
     * @return self
     */
    public static function fromArray(array $array): self
    {
        return new self(
            $array['id'],
            $array['name'],
            $array['scopes'],
            $array['secret'],
            $array['expire'] ?? null,
            $array['accessedAt'] ?? null,
            $array['sdks'] ?? [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'scopes' => $this->scopes,
            'secret' => $this->secret,
            'expire' => $this->expire,
            'accessedAt' => $this->accessedAt,
            'sdks' => $this->sdks,
        ];
    }

    public static function getName(): string
    {
        return Resource::TYPE_KEY;
    }

    public function getGroup(): string
    {
        return Transfer::GROUP_SETTINGS;
    }

    public function getKeyName(): string
    {
        return $this->name;
    }

    /**
     * @return array<string>
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function getExpire(): ?string
    {
        return $this->expire;
    }

    public function getAccessedAt(): ?string
    {
        return $this->accessedAt;
    }

    /**
     * @return array<string>
     */
    public function getSdks(): array
    {
        return $this->sdks;
    }
}
