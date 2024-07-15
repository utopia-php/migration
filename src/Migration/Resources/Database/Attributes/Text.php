<?php

namespace Utopia\Migration\Resources\Database\Attributes;

use Utopia\Migration\Resources\Database\Attribute;
use Utopia\Migration\Resources\Database\Collection;

class Text extends Attribute
{
    protected ?string $default;

    protected int $size = 256;

    /**
     * @param  ?string  $default
     */
    public function __construct(string $key, Collection $collection, bool $required = false, bool $array = false, ?string $default = null, int $size = 256)
    {
        parent::__construct($key, $collection, $required, $array);
        $this->default = $default;
        $this->size = $size;
    }

    public function getTypeName(): string
    {
        return Attribute::TYPE_STRING;
    }

    public function isRoot(): bool
    {
        return false;
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
