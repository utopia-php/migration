<?php

namespace Utopia\Migration\Resources\Database\Attributes;

use Utopia\Migration\Resources\Database\Attribute;
use Utopia\Migration\Resources\Database\Collection;

class Text extends Attribute
{
    public function __construct(
        string $key,
        Collection $collection,
        bool $required = false,
        bool $array = false,
        private readonly ?string $default = null,
        private readonly int $size = 256
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
            $array['size'] ?? 256
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'default' => $this->default,
            'size' => $this->size,
        ]);
    }

    public function getTypeName(): string
    {
        return Attribute::TYPE_STRING;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getDefault(): ?string
    {
        return $this->default;
    }
}
