<?php

namespace Utopia\Transfer\Resources\Attributes;

use Utopia\Transfer\Resources\Attribute;

class FloatAttribute extends Attribute {
    protected ?float $default;
    protected float $min;
    protected float $max;

    /**
     * @param string $key
     * @param bool $required
     * @param bool $array
     * @param ?float $default
     * @param float $min
     * @param float $max
     */
    function __construct(string $key, bool $required = false, bool $array = false, ?float $default = null, float $min = 0, float $max = 0)
    {
        parent::__construct($key, $required, $array);
        $this->default = $default;
        $this->min = $min;
        $this->max = $max;
    }

    function getName(): string
    {
        return 'floatAttribute';
    }

    function getMin(): float
    {
        return $this->min;
    }

    function getMax(): float
    {
        return $this->max;
    }

    function setMin(float $min): self
    {
        $this->min = $min;
        return $this;
    }

    function setMax(float $max): self
    {
        $this->max = $max;
        return $this;
    }

    function getDefault(): ?float
    {
        return $this->default;
    }

    function setDefault(float $default): self
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