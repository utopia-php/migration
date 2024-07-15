<?php

namespace Utopia\Migration\Resources\Auth;

use Utopia\Migration\Resource;
use Utopia\Migration\Resources\User;
use Utopia\Migration\Transfer;

class Team extends Resource
{
    protected string $name;

    protected array $preferences = [];

    protected array $members = [];

    public function __construct(string $id, string $name, array $preferences = [], array $members = [])
    {
        $this->id = $id;
        $this->name = $name;
        $this->preferences = $preferences;
        $this->members = $members;
    }

    public static function getName(): string
    {
        return Resource::TYPE_TEAM;
    }

    public function getGroup(): string
    {
        return Transfer::GROUP_AUTH;
    }

    public function isRoot(): bool
    {
        return true;
    }

    public function getTeamName(): string
    {
        return $this->name;
    }

    public function setTeamName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getPreferences(): array
    {
        return $this->preferences;
    }

    public function setPreferences(array $preferences): self
    {
        $this->preferences = $preferences;

        return $this;
    }

    public function getMembers(): array
    {
        return $this->members;
    }

    /**
     * @param  User[]  $members
     */
    public function setMembers(array $members): self
    {
        $this->members = $members;

        return $this;
    }

    public function asArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'preferences' => $this->preferences,
        ];
    }
}
