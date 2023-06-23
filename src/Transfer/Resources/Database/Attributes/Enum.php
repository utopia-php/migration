<?php

namespace Utopia\Transfer\Resources\Database\Attributes;

use Utopia\Transfer\Resources\Database\Attribute;
use Utopia\Transfer\Resources\Database\Collection;

class Enum extends Attribute
{
    protected ?string $default;

    protected array $elements;

    /**
     * @param  string[]  $elements
     * @param  ?string  $default
     */
    public function __construct(string $key, Collection $collection, array $elements, bool $required, bool $array, ?string $default)
    {
        parent::__construct($key, $collection, $required, $array);
        $this->default = $default;
        $this->elements = $elements;
    }

    public function getTypeName(): string
    {
        return Attribute::TYPE_ENUM;
    }

    public function getElements(): array
    {
        return $this->elements;
    }

    public function setElements(array $elements): self
    {
        $this->elements = $elements;

        return $this;
    }

    public function getDefault(): ?string
    {
        return $this->default;
    }

    public function setDefault(string $default): void
    {
        $this->default = $default;
    }

    public function asArray(): array
    {
        return array_merge(parent::asArray(), [
            'elements' => $this->elements,
        ]);
    }
}
