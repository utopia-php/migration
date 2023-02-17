<?php

namespace Utopia\Transfer\Resources\Attributes;

use Utopia\Transfer\Resources\Attribute;

class DateTimeAttribute extends Attribute {
    protected ?string $default;

    /**
     * @param string $key
     * @param bool $required
     * @param bool $array
     * @param ?string $default
     */
    function __construct(string $key, bool $required = false, bool $array = false, ?string $default = null)
    {
        parent::__construct($key, $required, $array);
        $this->default = $default;
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
        return 'dateTimeAttribute';
    }
}