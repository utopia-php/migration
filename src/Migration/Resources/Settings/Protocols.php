<?php

namespace Utopia\Migration\Resources\Settings;

use Utopia\Migration\Resource;
use Utopia\Migration\Transfer;

/**
 * Singleton resource representing the project's exposed API protocols
 * (REST, GraphQL, WebSocket), keyed by ProtocolId value. One per project;
 * destination merges each entry into the project's protocols map.
 */
class Protocols extends Resource
{
    /**
     * @param array<string, bool> $protocols Map of ProtocolId string → enabled flag.
     */
    public function __construct(
        string $id,
        private readonly array $protocols = [],
        string $createdAt = '',
        string $updatedAt = '',
    ) {
        $this->id = $id;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    /**
     * @param array<string, mixed> $array
     */
    public static function fromArray(array $array): self
    {
        return new self(
            $array['id'],
            (array) ($array['protocols'] ?? []),
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
            'protocols' => $this->protocols,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }

    public static function getName(): string
    {
        return Resource::TYPE_PROJECT_PROTOCOLS;
    }

    public function getGroup(): string
    {
        return Transfer::GROUP_PROJECTS;
    }

    /**
     * @return array<string, bool>
     */
    public function getProtocols(): array
    {
        return $this->protocols;
    }
}
