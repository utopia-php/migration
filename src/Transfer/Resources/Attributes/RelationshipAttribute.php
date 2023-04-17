<?php

namespace Utopia\Transfer\Resources\Attributes;

use Utopia\Transfer\Resources\Attribute;

class RelationshipAttribute extends Attribute
{
    protected string $relatedCollection;
    protected string $relationType;
    protected bool $twoWay;
    protected string $twoWayKey;
    protected string $onDelete;
    protected string $side;

    /**
     * @param string $key
     * @param bool $required
     * @param bool $array
     * @param string $relatedCollection
     * @param string $relationType
     * @param bool $twoWay
     * @param string $twoWayKey
     * @param string $onDelete
     * @param string $side
     */
    public function __construct(string $key, bool $required = false, bool $array = false, string $relatedCollection = '', string $relationType = '', bool $twoWay = false, string $twoWayKey = '', string $onDelete = '', string $side = '')
    {
        parent::__construct($key, $required, $array);
        $this->relatedCollection = $relatedCollection;
        $this->relationType = $relationType;
        $this->twoWay = $twoWay;
        $this->twoWayKey = $twoWayKey;
        $this->onDelete = $onDelete;
        $this->side = $side;
    }

    public function getName(): string
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
