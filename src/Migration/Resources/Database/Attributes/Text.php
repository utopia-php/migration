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
        ?string $default = null,
        bool $array = false,
        int $size = 256,
        string $format = '',
    ) {
        parent::__construct(
            $key,
            $collection,
            size: $size,
            required: $required,
            default: $default,
            array: $array,
            format: $format,
        );
    }

    /**
     * @param array{
     *     key: string,
     *     collection: array{
     *         database: array{
     *             id: string,
     *             name: string,
     *         },
     *         name: string,
     *         id: string,
     *         documentSecurity: bool,
     *         permissions: ?array<string>
     *     },
     *     required: bool,
     *     default: ?string,
     *     array: bool,
     *     size: int,
     *     format: string,
     * } $array
     * @return self
     */
    public static function fromArray(array $array): self
    {
        return new self(
            $array['key'],
            Collection::fromArray($array['collection']),
            required: $array['required'],
            default: $array['default'] ?? null,
            array: $array['array'],
            size: $array['size'],
            format: $array['format'],
        );
    }

    public function getType(): string
    {
        return Attribute::TYPE_STRING;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getFormat(): string
    {
        return $this->format;
    }
}
