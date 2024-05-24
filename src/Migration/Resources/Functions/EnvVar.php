<?php

namespace Utopia\Migration\Resources\Functions;

use Utopia\Migration\Resource;
use Utopia\Migration\Transfer;

class EnvVar extends Resource
{
    public function __construct(
        string $id,
        private readonly Func $func,
        private readonly string $key,
        private readonly string $value
    ) {
        $this->id = $id;
    }

    /**
     * @param array<string, mixed> $array
     * @return self
     */
    public static function fromArray(array $array): self
    {
        return new self(
            $array['id'],
            Func::fromArray($array['func']),
            $array['key'],
            $array['value']
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'func' => $this->func,
            'key' => $this->key,
            'value' => $this->value,
        ];
    }

    public static function getName(): string
    {
        return Resource::TYPE_ENVIRONMENT_VARIABLE;
    }

    public function getGroup(): string
    {
        return Transfer::GROUP_FUNCTIONS;
    }

    public function getFunc(): Func
    {
        return $this->func;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
