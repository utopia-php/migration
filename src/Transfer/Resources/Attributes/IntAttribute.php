<?php

namespace Utopia\Transfer\Resources\Attributes;

use Utopia\Transfer\Resources\Attribute;

class IntAttribute extends Attribute {
    protected ?int $default;
    protected ?int $min;
    protected ?int $max;

    /**
     * @param string $key
     * @param bool $required
     * @param bool $array
     * @param ?int $default
     * @param ?int $min
     * @param ?int $max
     */
    function __construct(string $key, bool $required = false, bool $array = false, ?int $default = null, int $min = null, int $max = null)
    {
        parent::__construct($key, $required, $array);
        $this->default = $default;
        $this->min = $min;
        $this->max = $max;
    }

    function getName(): string
    {
        return 'intAttribute';
    }

    function getMin(): int|null
    {
        return $this->min;
    }

    function getMax(): int|null
    {
        return $this->max;
    }

    function setMin(int|null $min): self
    {
        $this->min = $min;
        return $this;
    }

    function setMax(int|null $max): self
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