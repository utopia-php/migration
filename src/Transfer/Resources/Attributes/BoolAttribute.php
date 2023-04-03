<?php

namespace Utopia\Transfer\Resources\Attributes;

use Utopia\Transfer\Resources\Attribute;

class BoolAttribute extends Attribute
{
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
    public function __construct(string $key, bool $required = false, bool $array = false, ?bool $default = null)
    {
        parent::__construct($key, $required, $array);
        $this->default = $default;
    }

    public function getName(): string
    {
        return 'boolAttribute';
    }

    public function getDefault(): ?bool
    {
        return $this->default;
    }

    public function setDefault(bool $default): void
    {
        $this->default = $default;
    }

    public function asArray(): array
    {
        return array_merge(parent::asArray(), [
            'default' => $this->default,
        ]);
    }
}
