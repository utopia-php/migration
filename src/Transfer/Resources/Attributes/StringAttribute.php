<?php

namespace Utopia\Transfer\Resources\Attributes;

use Utopia\Transfer\Resources\Attribute;

class StringAttribute extends Attribute {
    /**
     * @param string $key
     * @param bool $required
     * @param bool $array
     * @param ?string $default
     * @param int $size
     */
    function __construct(protected string $key, protected bool $required, protected bool $array, protected ?string $default, protected int $size)
    {
    }

    function getName(): string
    {
        return 'stringAttribute';
    }

    function getSize(): int
    {
        return $this->size;
    }

    function setSize(int $size): self
    {
        $this->size = $size;
        return $this;
    }

    function getDefault(): ?string
    {
        return $this->default;
    }

    function setDefault(string $default): void
    {
        $this->default = $default;
    }

    function asArray(): array
    {
        return array_merge(parent::asArray(), [
            'size' => $this->size,
        ]);
    }
}