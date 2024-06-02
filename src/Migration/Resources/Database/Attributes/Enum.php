<?php

namespace Utopia\Migration\Resources\Database\Attributes;

use Utopia\Migration\Resources\Database\Attribute;
use Utopia\Migration\Resources\Database\Collection;

class Enum extends Attribute
{
    /**
     * @param  array<string>  $elements
     */
    public function __construct(
        string $key,
        Collection $collection,
        private readonly array $elements,
        bool $required = false,
        bool $array = false,
        private readonly ?string $default = null
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
            $array['elements'],
            $array['required'] ?? false,
            $array['array'] ?? false,
            $array['default'] ?? null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'elements' => $this->elements,
            'default' => $this->default,
        ]);
    }

    public function getTypeName(): string
    {
        return Attribute::TYPE_ENUM;
    }

    /**
     * @return array<string>
     */
    public function getElements(): array
    {
        return $this->elements;
    }

    public function getDefault(): ?string
    {
        return $this->default;
    }
}
