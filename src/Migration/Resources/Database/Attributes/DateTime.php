<?php

namespace Utopia\Migration\Resources\Database\Attributes;

use Utopia\Migration\Resources\Database\Attribute;
use Utopia\Migration\Resources\Database\Collection;

class DateTime extends Attribute
{
    public function __construct(
        string $key,
        Collection $collection,
        bool $required = false,
        bool $array = false,
        private readonly ?string $default = null,
    ) {
        parent::__construct($key, $collection, $required, $array);
    }

    /**
     * @param  array<string, mixed>  $array
     */
    public static function fromArray(array $array): self
    {
        return new self(
            $array['key'],
            Collection::fromArray($array['collection']),
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
            'default' => $this->default,
        ]);
    }

    public function getTypeName(): string
    {
        return Attribute::TYPE_DATETIME;
    }

    public function getDefault(): ?string
    {
        return $this->default;
    }
}
