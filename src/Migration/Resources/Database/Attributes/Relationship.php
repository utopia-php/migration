<?php

namespace Utopia\Migration\Resources\Database\Attributes;

use Utopia\Migration\Resources\Database\Attribute;
use Utopia\Migration\Resources\Database\Collection;

class Relationship extends Attribute
{
    protected string $relatedCollection;

    protected string $relationType;

    protected bool $twoWay;

    protected string $twoWayKey;

    protected string $onDelete;

    protected string $side;

    public function __construct(
        string $key,
        Collection $collection,
        bool $required = false,
        bool $array = false,
        string $relatedCollection = '',
        string $relationType = '',
        bool $twoWay = false,
        string $twoWayKey = '',
        string $onDelete = '',
        string $side = ''
    ) {
        parent::__construct($key, $collection, $required, $array);
        $this->relatedCollection = $relatedCollection;
        $this->relationType = $relationType;
        $this->twoWay = $twoWay;
        $this->twoWayKey = $twoWayKey;
        $this->onDelete = $onDelete;
        $this->side = $side;
    }

    /**
     * @param array<string, mixed> $array
     * @return self
     */
    public static function fromArray(array $array): self
    {
        return new self(
            $array['key'],
            Collection::fromArray($array['collection']),
            $array['required'] ?? false,
            $array['array'] ?? false,
            $array['relatedCollection'] ?? '',
            $array['relationType'] ?? '',
            $array['twoWay'] ?? false,
            $array['twoWayKey'] ?? '',
            $array['onDelete'] ?? '',
            $array['side'] ?? ''
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'relatedCollection' => $this->relatedCollection,
            'relationType' => $this->relationType,
            'twoWay' => $this->twoWay,
            'twoWayKey' => $this->twoWayKey,
            'onDelete' => $this->onDelete,
            'side' => $this->side,
        ]);
    }

    public function getTypeName(): string
    {
        return Attribute::TYPE_RELATIONSHIP;
    }

    public function getRelatedCollection(): string
    {
        return $this->relatedCollection;
    }

    public function getRelationType(): string
    {
        return $this->relationType;
    }

    public function getTwoWay(): bool
    {
        return $this->twoWay;
    }

    public function getTwoWayKey(): string
    {
        return $this->twoWayKey;
    }

    public function getOnDelete(): string
    {
        return $this->onDelete;
    }

    public function getSide(): string
    {
        return $this->side;
    }
}
