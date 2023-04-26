<?php

namespace Utopia\Transfer\Resources;

use Utopia\Transfer\Resource;
use Utopia\Transfer\Transfer;

class Project extends Resource
{
    protected string $name;
    protected string $id;

    public function __construct(string $name = '', string $id = '')
    {
        $this->name = $name;
        $this->id = $id;
    }

    public function getName(): string
    {
        return Resource::TYPE_PROJECT;
    }

    public function getGroup(): string
    {
        return Transfer::GROUP_GENERAL;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function asArray(): array
    {
        return [
            'name' => $this->name,
            'id' => $this->id,
        ];
    }
}
