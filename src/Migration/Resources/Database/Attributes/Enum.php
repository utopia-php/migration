<?php

namespace Utopia\Migration\Resources\Database\Attributes;

use Utopia\Migration\Resources\Database\Attribute;
use Utopia\Migration\Resources\Database\Collection;

class Enum extends Attribute
{
    /**
     * @param array<string> $elements
     */
    public function __construct(
        string $key,
        Collection $collection,
        array $elements,
        bool $required = false,
        ?string $default = null,
        bool $array = false,
    ) {
        parent::__construct(
            $key,
            $collection,
            required: $required,
            default: $default,
            array: $array,
            format: 'enum',
            formatOptions: [
                'elements' => $elements,
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
     *     default: ?string,
     *     array: bool,
     *     formatOptions: array{
     *         elements: array<string>
     *     }
     * } $array
     * @return self
     */
    public static function fromArray(array $array): self
    {
        return new self(
            $array['key'],
            Collection::fromArray($array['collection']),
            elements: $array['formatOptions']['elements'],
            required: $array['required'],
            default: $array['default'],
            array: $array['array'],
        );
    }

    public function getType(): string
    {
        return Attribute::TYPE_ENUM;
    }

    /**
     * @return array<string>
     */
    public function getElements(): array
    {
        return (array)$this->formatOptions['elements'];
    }
}
