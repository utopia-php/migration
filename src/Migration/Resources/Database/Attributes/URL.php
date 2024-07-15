<?php

namespace Utopia\Migration\Resources\Database\Attributes;

use Utopia\Migration\Resources\Database\Attribute;
use Utopia\Migration\Resources\Database\Collection;

class URL extends Attribute
{
    protected ?string $default;

    /**
     * @param  ?string  $default
     */
    public function __construct(string $key, Collection $collection, bool $required = false, bool $array = false, ?string $default = null)
    {
        parent::__construct($key, $collection, $required, $array);
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

    public function getTypeName(): string
    {
        return Attribute::TYPE_URL;
    }

    public function isRoot(): bool
    {
        return false;
    }
}
