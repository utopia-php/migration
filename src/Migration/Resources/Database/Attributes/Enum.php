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
        int $size = 256,
        string $createdAt = '',
        string $updatedAt = ''
    ) {
        parent::__construct(
            $key,
            $collection,
            size: $size,
            required: $required,
            default: $default,
            array: $array,
            format: 'enum',
            formatOptions: [
                'elements' => $elements,
            ],
            createdAt: $createdAt,
            updatedAt: $updatedAt
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
     *     size: int,
     *     required: bool,
     *     default: ?string,
     *     array: bool,
     *     formatOptions: array{
     *         elements: array<string>
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
            Collection::fromArray($array['collection']),
            elements: $array['formatOptions']['elements'],
            required: $array['required'],
            default: $array['default'],
            array: $array['array'],
            size: $array['size'],
            createdAt: $array['createdAt'] ?? '',
            updatedAt: $array['updatedAt'] ?? '',
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
