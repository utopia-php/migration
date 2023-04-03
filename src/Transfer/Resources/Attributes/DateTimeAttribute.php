<?php

namespace Utopia\Transfer\Resources\Attributes;

use Utopia\Transfer\Resources\Attribute;

class DateTimeAttribute extends Attribute
{
    protected ?string $default;

    /**
     * @param string $key
     * @param bool $required
     * @param bool $array
     * @param ?string $default
     */
    public function __construct(string $key, bool $required = false, bool $array = false, ?string $default = null)
    {
        parent::__construct($key, $required, $array);
        $this->default = $default;
    }

    public function getDefault(): ?string
    {
        return $this->default;
    }

    public function setDefault(string $default): void
    {
        $this->default = $default;
    }

    public function getName(): string
    {
        return 'dateTimeAttribute';
    }
}
