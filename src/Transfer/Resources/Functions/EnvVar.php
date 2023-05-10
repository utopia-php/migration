<?php

namespace Utopia\Transfer\Resources\Functions;

use Utopia\Transfer\Resource;
use Utopia\Transfer\Transfer;

class EnvVar extends Resource
{
    protected Func $func;
    protected string $key;
    protected string $value;

    public function __construct(Func $func, string $key, string $value)
    {
        $this->func = $func;
        $this->key = $key;
        $this->value = $value;
    }

    static function getName(): string
    {
        return Resource::TYPE_ENVVAR;
    }

    public function getGroup(): string
    {
        return Transfer::GROUP_FUNCTIONS;
    }

    public function getFunc(): Func
    {
        return $this->func;
    }

    public function setFunc(Func $func): self
    {
        $this->func = $func;
        return $this;
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

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): self
    {
        $this->value = $value;
        return $this;
    }

    public function asArray(): array
    {
        return [
            'func' => $this->func->getId(),
            'key' => $this->key,
            'value' => $this->value,
        ];
    }
}
