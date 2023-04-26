<?php

namespace Utopia\Transfer\Resources\Database\Attributes;

use Utopia\Transfer\Resources\Database\Attribute;
use Utopia\Transfer\Resources\Database\Collection;

class FloatAttribute extends Attribute
{
    protected ?float $default;
    protected ?float $min;
    protected ?float $max;

    /**
     * @param string $key
     * @param Collection $collection
     * @param bool $required
     * @param bool $array
     * @param ?float $default
     * @param ?float $min
     * @param ?float $max
     */
    public function __construct(string $key, Collection $collection, bool $required = false, bool $array = false, ?float $default = null, float $min = null, float $max = null)
    {
        parent::__construct($key, $collection, $required, $array);
        $this->default = $default;
        $this->min = $min;
        $this->max = $max;
    }

    public function getTypeName(): string
    {
        return 'floatAttribute';
    }

    public function getMin(): float|null
    {
        return $this->min;
    }

    public function getMax(): float|null
    {
        return $this->max;
    }

    public function setMin(float $min): self
    {
        $this->min = $min;
        return $this;
    }

    public function setMax(float $max): self
    {
        $this->max = $max;
        return $this;
    }

    public function getDefault(): ?float
    {
        return $this->default;
    }

    public function setDefault(float $default): self
    {
        $this->default = $default;
        return $this;
    }

    public function asArray(): array
    {
        return array_merge(parent::asArray(), [
            'min' => $this->getMin(),
            'max' => $this->getMax(),
            'default' => $this->getDefault(),
        ]);
    }
}
