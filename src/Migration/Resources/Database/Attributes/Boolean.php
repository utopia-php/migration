<?php

namespace Utopia\Migration\Resources\Database\Attributes;

use Utopia\Migration\Resources\Database\Attribute;
use Utopia\Migration\Resources\Database\Collection;

class Boolean extends Attribute
{
    protected string $key;

    protected bool $required;

    protected bool $array;

    protected ?bool $default;

    /**
     * @param  ?bool  $default
     */
    public function __construct(string $key, Collection $collection, bool $required = false, bool $array = false, bool $default = null)
    {
        parent::__construct($key, $collection, $required, $array);
        $this->default = $default;
    }

    public function getTypeName(): string
    {
        return Attribute::TYPE_BOOLEAN;
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
