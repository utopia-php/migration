<?php

namespace Utopia\Migration\Resources\Database;

/**
 * Generic Attribute resource that can represent any field type.
 * This eliminates the need for per-type Attribute subclasses by storing
 * the field type as a string and copying all schema data from Column resources.
 */
class GenericAttribute extends Attribute
{
    /**
     * @param string $key
     * @param Table $table
     * @param string $fieldType The actual field type (e.g., 'string', 'integer', 'email')
     * @param int $size
     * @param bool $required
     * @param mixed|null $default
     * @param bool $array
     * @param bool $signed
     * @param string $format
     * @param array<string, mixed> $formatOptions
     * @param array<string> $filters
     * @param array<string, mixed> $options
     * @param string $createdAt
     * @param string $updatedAt
     */
    public function __construct(
        string $key,
        Table $table,
        protected readonly string $fieldType,
        int $size = 0,
        bool $required = false,
        mixed $default = null,
        bool $array = false,
        bool $signed = false,
        string $format = '',
        array $formatOptions = [],
        array $filters = [],
        array $options = [],
        string $createdAt = '',
        string $updatedAt = '',
    ) {
        parent::__construct(
            $key,
            $table,
            $size,
            $required,
            $default,
            $array,
            $signed,
            $format,
            $formatOptions,
            $filters,
            $options,
            $createdAt,
            $updatedAt
        );
    }

    /**
     * Convert a Column resource to an Attribute resource.
     * This provides a deterministic way to derive attributes from columns.
     *
     * @param Column $column
     * @return self
     */
    public static function fromColumn(Column $column): self
    {
        return new self(
            $column->getKey(),
            $column->getTable(),
            $column->getType(),
            $column->getSize(),
            $column->isRequired(),
            $column->getDefault(),
            $column->isArray(),
            $column->isSigned(),
            $column->getFormat(),
            $column->getFormatOptions(),
            $column->getFilters(),
            $column->getOptions(),
            $column->getCreatedAt(),
            $column->getUpdatedAt()
        );
    }

    /**
     * Returns the field type (e.g., 'string', 'integer', 'email').
     * This is stored separately from the resource name (which is 'attribute').
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->fieldType;
    }
}
