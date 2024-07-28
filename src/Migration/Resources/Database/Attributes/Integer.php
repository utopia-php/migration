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
        ?int $default = null,
        bool $array = false,
        ?int $min = null,
        ?int $max = null
    ) {
        parent::__construct(
            $key,
            $collection,
            required: $required,
            default: $default,
            array: $array,
            formatOptions: [
                'min' => $min,
                'max' => $max,
            ]
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
     *     array: bool,
     *     default: ?int,
     *     formatOptions: array{
     *         min: ?int,
     *         max: ?int
     *     }
     * } $array
     * @return self
     */
    public static function fromArray(array $array): self
    {
        return new self(
            $array['key'],
            Collection::fromArray($array['collection']),
            required: $array['required'],
            default: $array['default'],
            array: $array['array'],
            min: $array['formatOptions']['min'] ?? null,
            max: $array['formatOptions']['max'] ?? null
        );
    }

    public function getType(): string
    {
        return Attribute::TYPE_INTEGER;
    }

    public function getMin(): ?int
    {
        return (int)$this->formatOptions['min'];
    }

    public function getMax(): ?int
    {
        return (int)$this->formatOptions['max'];
    }
}
