<?php

namespace Utopia\Transfer\Resources\Functions;

use Utopia\Transfer\Resource;
use Utopia\Transfer\Resources\Functions\Func;
use Utopia\Transfer\Transfer;

class Deployment extends Resource
{
    protected string $id;
    protected Func $func;
    protected string $entrypoint;
    protected int $size;
    protected int $start;
    protected int $end;
    protected string $data;
    protected bool $activated;

    public function __construct(string $id, Func $func, int $size, string $entrypoint, int $start = 0, int $end = 0, string $data = '', bool $activated = false)
    {
        $this->id = $id;
        $this->func = $func;
        $this->size = $size;
        $this->entrypoint = $entrypoint;
        $this->start = $start;
        $this->end = $end;
        $this->data = $data;
        $this->activated = $activated;
    }

    static function getName(): string
    {
        return Resource::TYPE_DEPLOYMENT;
    }

    public function getGroup(): string
    {
        return Transfer::GROUP_FUNCTIONS;
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

    public function getFunction(): Func
    {
        return $this->func;
    }

    public function setFunction(Func $func): self
    {
        $this->func = $func;
        return $this;
    }

    public function setSize(int $size): self
    {
        $this->size = $size;
        return $this;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setEntrypoint(string $entrypoint): self
    {
        $this->entrypoint = $entrypoint;
        return $this;
    }

    public function getEntrypoint(): string
    {
        return $this->entrypoint;
    }

    public function setStart(int $start): self
    {
        $this->start = $start;
        return $this;
    }

    public function getStart(): int
    {
        return $this->start;
    }
    
    public function setEnd(int $end): self
    {
        $this->end = $end;
        return $this;
    }

    public function getEnd(): int
    {
        return $this->end;
    }

    public function setData(string $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function setActivated(bool $activated): self
    {
        $this->activated = $activated;
        return $this;
    }

    public function getActivated(): bool
    {
        return $this->activated;
    }

    public function asArray(): array
    {
        return [
            'id' => $this->id,
            'func' => $this->func->asArray(),
            'size' => $this->size,
            'entrypoint' => $this->entrypoint,
            'start' => $this->start,
            'end' => $this->end,
            'activated' => $this->activated,
        ];
    }
}
