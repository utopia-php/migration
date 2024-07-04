<?php

namespace Utopia\Migration\Resources\Functions;

use Utopia\Migration\Resource;
use Utopia\Migration\Transfer;

class Func extends Resource
{
    /**
     * @param string $name
     * @param string $id
     * @param string $runtime
     * @param array<string> $execute
     * @param bool $enabled
     * @param array<string> $events
     * @param string $schedule
     * @param int $timeout
     * @param string $activeDeployment
     */
    public function __construct(
        string $id,
        private readonly string $name,
        private readonly string $runtime,
        private readonly array $execute = [],
        private readonly bool $enabled = true,
        private readonly array $events = [],
        private readonly string $schedule = '',
        private readonly int $timeout = 0,
        private readonly string $activeDeployment = '',
        private readonly string $entrypoint = ''
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
            $array['name'],
            $array['runtime'],
            $array['execute'] ?? [],
            $array['enabled'] ?? true,
            $array['events'] ?? [],
            $array['schedule'] ?? '',
            $array['timeout'] ?? 0,
            $array['activeDeployment'] ?? '',
            $array['entrypoint'] ?? ''
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'execute' => $this->execute,
            'enabled' => $this->enabled,
            'runtime' => $this->runtime,
            'events' => $this->events,
            'schedule' => $this->schedule,
            'timeout' => $this->timeout,
            'activeDeployment' => $this->activeDeployment,
            'entrypoint' => $this->entrypoint,
        ];
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

    public function getExecute(): array
    {
        return $this->execute;
    }

    public function getEnabled(): bool
    {
        return $this->enabled;
    }

    public function getRuntime(): string
    {
        return $this->runtime;
    }

    public function getEvents(): array
    {
        return $this->events;
    }

    public function getSchedule(): string
    {
        return $this->schedule;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function getActiveDeployment(): string
    {
        return $this->activeDeployment;
    }
}
