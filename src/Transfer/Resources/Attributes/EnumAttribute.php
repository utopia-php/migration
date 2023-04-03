<?php

namespace Utopia\Transfer\Resources\Attributes;

use Utopia\Transfer\Resources\Attribute;

class EnumAttribute extends Attribute
{
    protected ?string $default;
    protected array $elements;

    /**
     * @param string $key
     * @param string[] $elements
     * @param bool $required
     * @param bool $array
     * @param ?string $default
     */
    public function __construct(string $key, array $elements, bool $required, bool $array, ?string $default)
    {
        parent::__construct($key, $required, $array);
        $this->default = $default;
        $this->elements = $elements;
    }

    public function getName(): string
    {
        return 'enumAttribute';
    }

    public function getElements(): array
    {
        return $this->elements;
    }

    public function setElements(array $elements): self
    {
        $this->elements = $elements;
        return $this;
    }

    public function getDefault(): ?string
    {
        return $this->default;
    }

    public function setDefault(string $default): void
    {
        $this->default = $default;
    }

    public function asArray(): array
    {
        return array_merge(parent::asArray(), [
            'elements' => $this->elements,
        ]);
    }
}
