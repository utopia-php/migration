<?php

namespace Utopia\Migration\Resources\Database;

use Utopia\Migration\Resource;
use Utopia\Migration\Transfer;

abstract class Attribute extends Resource
{
    public const string TYPE_STRING = 'string';
    public const string TYPE_INTEGER = 'int';
    public const string TYPE_FLOAT = 'float';
    public const string TYPE_BOOLEAN = 'bool';
    public const string TYPE_DATETIME = 'dateTime';
    public const string TYPE_EMAIL = 'email';
    public const string TYPE_ENUM = 'enum';
    public const string TYPE_IP = 'IP';
    public const string TYPE_URL = 'URL';
    public const string TYPE_RELATIONSHIP = 'relationship';

    /**
     * @param string $key
     * @param Collection $collection
     * @param int $size
     * @param bool $required
     * @param mixed|null $default
     * @param bool $array
     * @param bool $signed
     * @param string $format
     * @param array<string, mixed> $formatOptions
     * @param array<string> $filters
     * @param array<string, mixed> $options
     */
    public function __construct(
        protected readonly string $key,
        protected readonly Collection $collection,
        protected readonly int $size = 0,
        protected readonly bool $required = false,
        protected readonly mixed $default = null,
        protected readonly bool $array = false,
        protected readonly bool $signed = false,
        protected readonly string $format = '',
        protected readonly array $formatOptions = [],
        protected readonly array $filters = [],
        protected array $options = [],
        protected string $createdAt = '',
        protected string $updatedAt = '',
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'key' => $this->key,
            'collection' => $this->collection,
            'type' => $this->getType(),
            'size' => $this->size,
            'required' => $this->required,
            'default' => $this->default,
            'array' => $this->array,
            'signed' => $this->signed,
            'format' => $this->format,
            'formatOptions' => $this->formatOptions,
            'filters' => $this->filters,
            'options' => $this->options,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }

    public static function getName(): string
    {
        return Resource::TYPE_ATTRIBUTE;
    }

    abstract public function getType(): string;

    public function getGroup(): string
    {
        return Transfer::GROUP_DATABASES;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getCollection(): Collection
    {
        return $this->collection;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function isArray(): bool
    {
        return $this->array;
    }

    public function isSigned(): bool
    {
        return $this->signed;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFormatOptions(): array
    {
        return $this->formatOptions;
    }

    /**
     * @return array<string>
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * @return array<string, mixed>
     */
    public function &getOptions(): array
    {
        return $this->options;
    }
}
