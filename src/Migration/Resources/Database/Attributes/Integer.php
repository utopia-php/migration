<?php

namespace Utopia\Migration\Resources\Database\Attributes;

use Utopia\Migration\Resources\Database\Attribute;
use Utopia\Migration\Resources\Database\Collection;

class Integer extends Attribute
{
    public function __construct(
        string $key,
        Collection $collection,
        bool $required = false,
        bool $array = false,
        private readonly ?int $default = null,
        private readonly ?int $min = null,
        private readonly ?int $max = null
    ) {
        parent::__construct($key, $collection, $required, $array);
    }

    /**
     * @param array<string, mixed> $array
     * @return self
     */
    public static function fromArray(array $array): self
    {
        return new self(
            $array['key'],
            Collection::fromArray($array['collection']),
            $array['required'] ?? false,
            $array['array'] ?? false,
            $array['default'] ?? null,
            $array['min'] ?? null,
            $array['max'] ?? null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'default' => $this->default,
            'min' => $this->min,
            'max' => $this->max,
        ]);
    }

    public function getTypeName(): string
    {
        return Attribute::TYPE_FLOAT;
    }

    public function getMin(): ?int
    {
        return $this->min;
    }

    public function getMax(): ?int
    {
        return $this->max;
    }

    public function getDefault(): ?int
    {
        return $this->default;
    }
}
