<?php

namespace Utopia\Migration\Resources\Database\Attribute;

use Utopia\Migration\Resources\Database\Attribute;
use Utopia\Migration\Resources\Database\Collection;

class Decimal extends Attribute
{
    public function __construct(
        string $key,
        Collection $collection,
        bool   $required = false,
        ?float $default = null,
        bool   $array = false,
        ?float $min = null,
        ?float $max = null,
        bool   $signed = true,
        string $createdAt = '',
        string $updatedAt = ''
    ) {
        $min ??= PHP_FLOAT_MIN;
        $max ??= PHP_FLOAT_MAX;

        parent::__construct(
            $key,
            $collection,
            required: $required,
            default: $default,
            array: $array,
            signed: $signed,
            formatOptions: [
                'min' => $min,
                'max' => $max,
            ],
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );
    }

    /**
     * @param array{
     *     key: string,
     *     collection?: array{
     *         database: array{
     *             id: string,
     *             name: string,
     *         },
     *         name: string,
     *         id: string,
     *         documentSecurity: bool,
     *         permissions: ?array<string>
     *     },
     *     table?: array{
     *         database: array{
     *             id: string,
     *             name: string,
     *         },
     *         name: string,
     *         id: string,
     *         rowSecurity: bool,
     *         permissions: ?array<string>
     *     },
     *     required: bool,
     *     array: bool,
     *     default: ?float,
     *     formatOptions: array{
     *         min: ?float,
     *         max: ?float
     *     },
     *     createdAt: string,
     *     updatedAt: string,
     * } $array
     * @return self
     */
    public static function fromArray(array $array): self
    {
        return new self(
            $array['key'],
            Collection::fromArray($array['table'] ?? $array['collection']),
            required: $array['required'],
            default: $array['default'],
            array: $array['array'],
            min: $array['formatOptions']['min'],
            max: $array['formatOptions']['max'],
            createdAt: $array['createdAt'] ?? '',
            updatedAt: $array['updatedAt'] ?? '',
        );
    }

    public function getType(): string
    {
        return Attribute::TYPE_FLOAT;
    }

    public function getMin(): ?float
    {
        return (float)$this->formatOptions['min'];
    }

    public function getMax(): ?float
    {
        return (float)$this->formatOptions['max'];
    }
}
