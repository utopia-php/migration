<?php

namespace Utopia\Transfer\Resources\Auth;

use Utopia\Transfer\Resource;
use Utopia\Transfer\Transfer;

class TeamMembership extends Resource
{
    protected Team $team;
    protected string $userId;
    protected array $roles;
    protected bool $active = true;

    function __construct(Team $team, string $userId, array $roles = [], bool $active = true)
    {
        $this->team = $team;
        $this->userId = $userId;
        $this->roles = $roles;
        $this->active = $active;
    }

    function getName(): string
    {
        return Resource::TYPE_TEAM_MEMBERSHIP;
    }

    function getGroup(): string
    {
        return Transfer::GROUP_AUTH;
    }

    function getTeam(): Team
    {
        return $this->team;
    }

    function setTeam(Team $team): self
    {
        $this->team = $team;
        return $this;
    }

    function getUserId(): string
    {
        return $this->userId;
    }

    function setUserId(string $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    function getRoles(): array
    {
        return $this->roles;
    }

    function setRoles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
    }

    function getActive(): bool
    {
        return $this->active;
    }

    function setActive(bool $active): self
    {
        $this->active = $active;
        return $this;
    }

    function asArray(): array
    {
        return [
            'userId' => $this->userId,
            'roles' => $this->roles,
            'active' => $this->active,
        ];
    }
}
