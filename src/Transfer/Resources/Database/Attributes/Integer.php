<?php

namespace Utopia\Transfer\Resources\Database\Attributes;

use Utopia\Transfer\Resources\Database\Attribute;
use Utopia\Transfer\Resources\Database\Collection;

class Integer extends Attribute
{
    protected ?int $default;

    protected ?int $min;

    protected ?int $max;

    /**
     * @param  ?int  $default
     * @param  ?int  $min
     * @param  ?int  $max
     */
    public function __construct(string $key, Collection $collection, bool $required = false, bool $array = false, ?int $default = null, int $min = null, int $max = null)
    {
        parent::__construct($key, $collection, $required, $array);
        $this->default = $default;
        $this->min = $min;
        $this->max = $max;
    }

    public function getTypeName(): string
    {
        return Attribute::TYPE_INTEGER;
    }

    public function getMin(): int|null
    {
        return $this->min;
    }

    public function getMax(): int|null
    {
        return $this->max;
    }

    public function setMin(int|null $min): self
    {
        $this->min = $min;

        return $this;
    }

    public function setMax(int|null $max): self
    {
        $this->max = $max;

        return $this;
    }

    public function getDefault(): ?int
    {
        return $this->default;
    }

    public function setDefault(int $default): self
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
