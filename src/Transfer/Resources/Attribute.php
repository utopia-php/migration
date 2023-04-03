<?php

namespace Utopia\Transfer\Resources;

use Utopia\Transfer\Resource;

class Attribute extends Resource
{
    public const TYPE_STRING = 'stringAttribute';
    public const TYPE_INTEGER = 'intAttribute';
    public const TYPE_FLOAT = 'floatAttribute';
    public const TYPE_BOOLEAN = 'boolAttribute';
    public const TYPE_DATETIME = 'dateTimeAttribute';
    public const TYPE_EMAIL = 'emailAttribute';
    public const TYPE_ENUM = 'enumAttribute';
    public const TYPE_IP = 'IPAttribute';
    public const TYPE_URL = 'URLAttribute';

    protected string $key;
    protected bool $required;
    protected bool $array;

    /**
     * @param string $key
     * @param bool $required
     * @param bool $array
     * @param int $size
     */
    public function __construct(string $key, bool $required = false, bool $array = false)
    {
        $this->key = $key;
        $this->required = $required;
        $this->array = $array;
    }

    public function getName(): string
    {
        return 'attribute';
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): self
    {
        $this->key = $key;
        return $this;
    }

    public function getRequired(): bool
    {
        return $this->required;
    }

    public function setRequired(bool $required): self
    {
        $this->required = $required;
        return $this;
    }

    public function getArray(): bool
    {
        return $this->array;
    }

    public function setArray(bool $array): self
    {
        $this->array = $array;
        return $this;
    }

    public function asArray(): array
    {
        return [
            'key' => $this->key,
            'required' => $this->required,
            'array' => $this->array,
            'type' => $this->getName(),
        ];
    }
}
