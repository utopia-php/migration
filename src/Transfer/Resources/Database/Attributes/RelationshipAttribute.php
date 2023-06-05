<?php

namespace Utopia\Transfer\Resources\Database\Attributes;

use Utopia\Transfer\Resources\Database\Attribute;
use Utopia\Transfer\Resources\Database\Collection;

class RelationshipAttribute extends Attribute
{
    protected string $relatedCollection;

    protected string $relationType;

    protected bool $twoWay;

    protected string $twoWayKey;

    protected string $onDelete;

    protected string $side;

    public function __construct(string $key, Collection $collection, bool $required = false, bool $array = false, string $relatedCollection = '', string $relationType = '', bool $twoWay = false, string $twoWayKey = '', string $onDelete = '', string $side = '')
    {
        parent::__construct($key, $collection, $required, $array);
        $this->relatedCollection = $relatedCollection;
        $this->relationType = $relationType;
        $this->twoWay = $twoWay;
        $this->twoWayKey = $twoWayKey;
        $this->onDelete = $onDelete;
        $this->side = $side;
    }

    public function getTypeName(): string
    {
        return 'relationshipAttribute';
    }

    public function getRelatedCollection(): string
    {
        return $this->relatedCollection;
    }

    public function setRelatedCollection(string $relatedCollection): void
    {
        $this->relatedCollection = $relatedCollection;
    }

    public function getRelationType(): string
    {
        return $this->relationType;
    }

    public function setRelationType(string $relationType): void
    {
        $this->relationType = $relationType;
    }

    public function getTwoWay(): bool
    {
        return $this->twoWay;
    }

    public function setTwoWay(bool $twoWay): void
    {
        $this->twoWay = $twoWay;
    }

    public function getTwoWayKey(): string
    {
        return $this->twoWayKey;
    }

    public function setTwoWayKey(string $twoWayKey): void
    {
        $this->twoWayKey = $twoWayKey;
    }

    public function getOnDelete(): string
    {
        return $this->onDelete;
    }

    public function setOnDelete(string $onDelete): void
    {
        $this->onDelete = $onDelete;
    }

    public function getSide(): string
    {
        return $this->side;
    }

    public function setSide(string $side): void
    {
        $this->side = $side;
    }
}
