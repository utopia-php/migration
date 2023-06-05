<?php

namespace Utopia\Transfer\Resources\Auth;

use Utopia\Transfer\Resource;
use Utopia\Transfer\Transfer;

class Membership extends Resource
{
    protected Team $team;

    protected string $userId;

    protected array $roles;

    protected bool $active = true;

    public function __construct(Team $team, string $userId, array $roles = [], bool $active = true)
    {
        $this->team = $team;
        $this->userId = $userId;
        $this->roles = $roles;
        $this->active = $active;
    }

    public static function getName(): string
    {
        return Resource::TYPE_MEMBERSHIP;
    }

    public function getGroup(): string
    {
        return Transfer::GROUP_AUTH;
    }

    public function getTeam(): Team
    {
        return $this->team;
    }

    public function setTeam(Team $team): self
    {
        $this->team = $team;

        return $this;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function asArray(): array
    {
        return [
            'userId' => $this->userId,
            'roles' => $this->roles,
            'active' => $this->active,
        ];
    }
}
