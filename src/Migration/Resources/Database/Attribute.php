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


    public function __construct(
        protected readonly string $key,
        protected readonly Collection $collection,
        protected readonly bool $required = false,
        protected readonly bool $array = false
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->getTypeName(),
            'collection' => $this->collection,
            //'collectionId' => $this->collection->getId(),
            'required' => $this->required,
            'array' => $this->array,
        ];
    }

    public static function getName(): string
    {
        return Resource::TYPE_ATTRIBUTE;
    }

    abstract public function getTypeName(): string;

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

    public function getRequired(): bool
    {
        return $this->required;
    }

    public function getArray(): bool
    {
        return $this->array;
    }
}
