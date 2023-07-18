<?php

namespace Utopia\Transfer\Resources\Auth;

use Utopia\Transfer\Resource;
use Utopia\Transfer\Transfer;

/**
 * Represents a membership of a user in a team
 */
class Membership extends Resource
{
    protected Team $team;

    protected User $user;

    protected array $roles;

    protected bool $active = true;

    public function __construct(Team $team, User $user, array $roles = [], bool $active = true)
    {
        $this->team = $team;
        $this->user = $user;
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

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

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
            'userId' => $this->user->getId(),
            'roles' => $this->roles,
            'active' => $this->active,
        ];
    }
}
