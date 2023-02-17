<?php

namespace Utopia\Transfer\Resources;

use Utopia\Transfer\Resource;

class Attribute extends Resource {
    const TYPE_STRING = 'stringAttribute';
    const TYPE_INTEGER = 'intAttribute';
    const TYPE_FLOAT = 'floatAttribute';
    const TYPE_BOOLEAN = 'boolAttribute';
    const TYPE_DATETIME = 'dateTimeAttribute';
    const TYPE_EMAIL = 'emailAttribute';
    const TYPE_ENUM = 'enumAttribute';
    const TYPE_IP = 'IPAttribute';
    const TYPE_URL = 'URLAttribute';

    protected string $key;
    protected bool $required;
    protected bool $array;

    /**
     * @param string $key
     * @param bool $required
     * @param bool $array
     * @param int $size
     */
    function __construct(string $key, bool $required = false, bool $array = false)
    {
        $this->key = $key;
        $this->required = $required;
        $this->array = $array;
    }
    
    function getName(): string
    {
        return 'attribute';
    }

    function getKey(): string
    {
        return $this->key;
    }
    
    function setKey(string $key): self
    {
        $this->key = $key;
        return $this;
    }

    function getRequired(): bool
    {
        return $this->required;
    }

    function setRequired(bool $required): self
    {
        $this->required = $required;
        return $this;
    }

    function getArray(): bool
    {
        return $this->array;
    }

    function setArray(bool $array): self
    {
        $this->array = $array;
        return $this;
    }

    function asArray(): array
    {
        return [
            'key' => $this->key,
            'required' => $this->required,
            'array' => $this->array,
            'type' => $this->getName(),
        ];
    }
}