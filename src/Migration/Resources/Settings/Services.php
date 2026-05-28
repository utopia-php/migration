<?php

namespace Utopia\Migration\Resources\Settings;

use Utopia\Migration\Resource;
use Utopia\Migration\Transfer;

/**
 * Singleton settings resource carrying the project's 17 per-service
 * enable/disable flags. One per project; destination flips each via
 * Project::updateService().
 */
class Services extends Resource
{
    public function __construct(
        string $id,
        private readonly bool $account = true,
        private readonly bool $avatars = true,
        private readonly bool $databases = true,
        private readonly bool $tablesdb = true,
        private readonly bool $locale = true,
        private readonly bool $health = true,
        private readonly bool $project = true,
        private readonly bool $storage = true,
        private readonly bool $teams = true,
        private readonly bool $users = true,
        private readonly bool $vcs = true,
        private readonly bool $sites = true,
        private readonly bool $functions = true,
        private readonly bool $proxy = true,
        private readonly bool $graphql = true,
        private readonly bool $migrations = true,
        private readonly bool $messaging = true,
        string $createdAt = '',
        string $updatedAt = '',
    ) {
        $this->id = $id;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    /**
     * @param array<string, mixed> $array
     */
    public static function fromArray(array $array): self
    {
        return new self(
            $array['id'],
            (bool) ($array['account'] ?? true),
            (bool) ($array['avatars'] ?? true),
            (bool) ($array['databases'] ?? true),
            (bool) ($array['tablesdb'] ?? true),
            (bool) ($array['locale'] ?? true),
            (bool) ($array['health'] ?? true),
            (bool) ($array['project'] ?? true),
            (bool) ($array['storage'] ?? true),
            (bool) ($array['teams'] ?? true),
            (bool) ($array['users'] ?? true),
            (bool) ($array['vcs'] ?? true),
            (bool) ($array['sites'] ?? true),
            (bool) ($array['functions'] ?? true),
            (bool) ($array['proxy'] ?? true),
            (bool) ($array['graphql'] ?? true),
            (bool) ($array['migrations'] ?? true),
            (bool) ($array['messaging'] ?? true),
            createdAt: $array['createdAt'] ?? '',
            updatedAt: $array['updatedAt'] ?? '',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'account' => $this->account,
            'avatars' => $this->avatars,
            'databases' => $this->databases,
            'tablesdb' => $this->tablesdb,
            'locale' => $this->locale,
            'health' => $this->health,
            'project' => $this->project,
            'storage' => $this->storage,
            'teams' => $this->teams,
            'users' => $this->users,
            'vcs' => $this->vcs,
            'sites' => $this->sites,
            'functions' => $this->functions,
            'proxy' => $this->proxy,
            'graphql' => $this->graphql,
            'migrations' => $this->migrations,
            'messaging' => $this->messaging,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }

    public static function getName(): string
    {
        return Resource::TYPE_PROJECT_SERVICES;
    }

    public function getGroup(): string
    {
        return Transfer::GROUP_PROJECTS;
    }

    public function getAccount(): bool
    {
        return $this->account;
    }

    public function getAvatars(): bool
    {
        return $this->avatars;
    }

    public function getDatabases(): bool
    {
        return $this->databases;
    }

    public function getTablesdb(): bool
    {
        return $this->tablesdb;
    }

    public function getLocale(): bool
    {
        return $this->locale;
    }

    public function getHealth(): bool
    {
        return $this->health;
    }

    public function getProject(): bool
    {
        return $this->project;
    }

    public function getStorage(): bool
    {
        return $this->storage;
    }

    public function getTeams(): bool
    {
        return $this->teams;
    }

    public function getUsers(): bool
    {
        return $this->users;
    }

    public function getVcs(): bool
    {
        return $this->vcs;
    }

    public function getSites(): bool
    {
        return $this->sites;
    }

    public function getFunctions(): bool
    {
        return $this->functions;
    }

    public function getProxy(): bool
    {
        return $this->proxy;
    }

    public function getGraphql(): bool
    {
        return $this->graphql;
    }

    public function getMigrations(): bool
    {
        return $this->migrations;
    }

    public function getMessaging(): bool
    {
        return $this->messaging;
    }
}
