<?php

namespace Utopia\Transfer\Resources\Functions;

use Utopia\Transfer\Resource;
use Utopia\Transfer\Transfer;

class Func extends Resource
{
    protected string $name;
    protected string $id;
    protected array $execute;
    protected bool $enabled;
    protected string $runtime;
    protected array $events;
    protected string $schedule;
    protected int $timeout;

    public function __construct(string $name, string $id, string $runtime, array $execute = [], bool $enabled = true, array $events = [], string $schedule = '', int $timeout = 0)
    {
        $this->name = $name;
        $this->id = $id;
        $this->execute = $execute;
        $this->enabled = $enabled;
        $this->runtime = $runtime;
        $this->events = $events;
        $this->schedule = $schedule;
        $this->timeout = $timeout;
    }

    public static function getName(): string
    {
        return Resource::TYPE_FUNCTION;
    }

    public function getGroup(): string
    {
        return Transfer::GROUP_FUNCTIONS;
    }

    public function getFunctionName(): string
    {
        return $this->name;
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

    public function getExecute(): array
    {
        return $this->execute;
    }

    public function setExecute(array $execute): self
    {
        $this->execute = $execute;
        return $this;
    }

    public function getEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function getRuntime(): string
    {
        return $this->runtime;
    }

    public function setRuntime(string $runtime): self
    {
        $this->runtime = $runtime;
        return $this;
    }

    public function getEvents(): array
    {
        return $this->events;
    }

    public function setEvents(array $events): self
    {
        $this->events = $events;
        return $this;
    }

    public function getSchedule(): string
    {
        return $this->schedule;
    }

    public function setSchedule(string $schedule): self
    {
        $this->schedule = $schedule;
        return $this;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }

    public function asArray(): array
    {
        return [
            'name' => $this->name,
            'id' => $this->id,
            'execute' => $this->execute,
            'enabled' => $this->enabled,
            'runtime' => $this->runtime,
            'events' => $this->events,
            'schedule' => $this->schedule,
            'timeout' => $this->timeout,
        ];
    }
}
