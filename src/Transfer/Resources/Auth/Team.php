<?php

namespace Utopia\Transfer\Resources\Auth;

use Utopia\Transfer\Resource;
use Utopia\Transfer\Transfer;
use Utopia\Transfer\Resources\User;

class Team extends Resource
{
    protected string $id;
    protected string $name;
    protected array $preferences = [];
    protected array $members = [];

    function __construct(string $id, string $name, array $preferences = [], array $members = [])
    {
        $this->id = $id;
        $this->name = $name;
        $this->preferences = $preferences;
        $this->members = $members;
    }

    static function getName(): string
    {
        return Resource::TYPE_TEAM;
    }

    function getGroup(): string
    {
        return Transfer::GROUP_AUTH;
    }

    function getTeamName(): string
    {
        return $this->name;
    }

    function setTeamName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    function getId(): string
    {
        return $this->id;
    }

    function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    function getPreferences(): array
    {
        return $this->preferences;
    }

    function setPreferences(array $preferences): self
    {
        $this->preferences = $preferences;
        return $this;
    }

    function getMembers(): array
    {
        return $this->members;
    }

    /**
     * @param User[] $members
     */
    function setMembers(array $members): self
    {
        $this->members = $members;
        return $this;
    }

    function asArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'preferences' => $this->preferences,
        ];
    }
}
