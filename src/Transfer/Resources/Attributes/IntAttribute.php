<?php

namespace Utopia\Transfer\Resources\Attributes;

use Utopia\Transfer\Resources\Attribute;

class IntAttribute extends Attribute {
    /**
     * @param string $key
     * @param bool $required
     * @param bool $array
     * @param ?int $default
     * @param int $min
     * @param int $max
     */
    function __construct(protected string $key, protected bool $required, protected bool $array, protected ?int $default, protected int $min = 0, protected int $max = 0)
    {
    }

    function getName(): string
    {
        return 'intAttribute';
    }

    function getMin(): int
    {
        return $this->min;
    }

    function getMax(): int
    {
        return $this->max;
    }

    function setMin(int $min): self
    {
        $this->min = $min;
        return $this;
    }

    function setMax(int $max): self
    {
        $this->max = $max;
        return $this;
    }

    function getDefault(): ?int
    {
        return $this->default;
    }

    function setDefault(int $default): self
    {
        $this->default = $default;
        return $this;
    }

    function asArray(): array
    {
        return array_merge(parent::asArray(), [
            'min' => $this->getMin(),
            'max' => $this->getMax(),
            'default' => $this->getDefault(),
        ]);
    }
}