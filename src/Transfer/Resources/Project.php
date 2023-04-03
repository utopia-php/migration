<?php

namespace Utopia\Transfer\Resources;

use Utopia\Transfer\Resource;

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
        return 'project';
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
