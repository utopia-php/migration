<?php

namespace Utopia\Transfer\Resources\Attributes;

use Utopia\Transfer\Resources\Attribute;

class BoolAttribute extends Attribute {
    protected string $key;
    protected bool $required;
    protected bool $array;
    protected ?bool $default;

    /**
     * @param string $key
     * @param bool $required
     * @param bool $array
     * @param ?bool $default
     */
    function __construct(string $key, bool $required = false, bool $array = false, ?bool $default = null)
    {
        parent::__construct($key, $required, $array);
        $this->default = $default;
    }

    function getName(): string
    {
        return 'boolAttribute';
    }

    function getDefault(): ?bool
    {
        return $this->default;
    }

    function setDefault(bool $default): void
    {
        $this->default = $default;
    }

    function asArray(): array
    {
        return array_merge(parent::asArray(), [
            'default' => $this->default,
        ]);
    }
}