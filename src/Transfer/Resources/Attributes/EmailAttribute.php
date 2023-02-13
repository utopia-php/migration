<?php

namespace Utopia\Transfer\Resources\Attributes;

use Utopia\Transfer\Resources\Attribute;

class EmailAttribute extends Attribute {
    /**
     * @param string $key
     * @param bool $required
     * @param bool $array
     * @param ?string $default
     */
    function __construct(protected string $key, protected bool $required, protected bool $array, protected ?string $default)
    {
    }

    function getDefault(): ?string
    {
        return $this->default;
    }

    function setDefault(string $default): void
    {
        $this->default = $default;
    }

    function getName(): string
    {
        return 'emailAttribute';
    }
}