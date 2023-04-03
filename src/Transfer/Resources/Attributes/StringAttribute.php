<?php

namespace Utopia\Transfer\Resources\Attributes;

use Utopia\Transfer\Resources\Attribute;

class StringAttribute extends Attribute
{
    protected ?string $default;
    protected int $size = 256;

    /**
     * @param string $key
     * @param bool $required
     * @param bool $array
     * @param ?string $default
     * @param int $size
     */
    public function __construct(string $key, bool $required = false, bool $array = false, ?string $default = null, int $size = 256)
    {
        parent::__construct($key, $required, $array);
        $this->default = $default;
        $this->size = $size;
    }

    public function getName(): string
    {
        return 'stringAttribute';
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): self
    {
        $this->size = $size;
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
            'size' => $this->size,
        ]);
    }
}
