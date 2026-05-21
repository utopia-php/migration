<?php

namespace Utopia\Migration\Resources\Auth;

use Utopia\Migration\Resource;
use Utopia\Migration\Transfer;

/**
 * Singleton auth resource carrying the project's security policies:
 * password rules, session behavior, user limits, membership privacy.
 *
 * 9 policies, 13 fields total — MembershipPrivacy alone has 5 sub-flags.
 */
class Policies extends Resource
{
    public function __construct(
        string $id,
        private readonly int $passwordHistory = 0,
        private readonly int $sessionDuration = 31536000,
        private readonly int $sessionsLimit = 100,
        private readonly int $userLimit = 0,
        private readonly bool $passwordDictionary = false,
        private readonly bool $personalDataCheck = false,
        private readonly bool $sessionAlerts = false,
        private readonly bool $sessionInvalidation = false,
        private readonly bool $membershipsUserId = true,
        private readonly bool $membershipsUserEmail = true,
        private readonly bool $membershipsUserName = true,
        private readonly bool $membershipsUserMfa = true,
        private readonly bool $membershipsUserPhone = true,
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
            (int) ($array['passwordHistory'] ?? 0),
            (int) ($array['sessionDuration'] ?? 31536000),
            (int) ($array['sessionsLimit'] ?? 100),
            (int) ($array['userLimit'] ?? 0),
            (bool) ($array['passwordDictionary'] ?? false),
            (bool) ($array['personalDataCheck'] ?? false),
            (bool) ($array['sessionAlerts'] ?? false),
            (bool) ($array['sessionInvalidation'] ?? false),
            (bool) ($array['membershipsUserId'] ?? true),
            (bool) ($array['membershipsUserEmail'] ?? true),
            (bool) ($array['membershipsUserName'] ?? true),
            (bool) ($array['membershipsUserMfa'] ?? true),
            (bool) ($array['membershipsUserPhone'] ?? true),
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
            'passwordHistory' => $this->passwordHistory,
            'sessionDuration' => $this->sessionDuration,
            'sessionsLimit' => $this->sessionsLimit,
            'userLimit' => $this->userLimit,
            'passwordDictionary' => $this->passwordDictionary,
            'personalDataCheck' => $this->personalDataCheck,
            'sessionAlerts' => $this->sessionAlerts,
            'sessionInvalidation' => $this->sessionInvalidation,
            'membershipsUserId' => $this->membershipsUserId,
            'membershipsUserEmail' => $this->membershipsUserEmail,
            'membershipsUserName' => $this->membershipsUserName,
            'membershipsUserMfa' => $this->membershipsUserMfa,
            'membershipsUserPhone' => $this->membershipsUserPhone,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }

    public static function getName(): string
    {
        return Resource::TYPE_POLICIES;
    }

    public function getGroup(): string
    {
        return Transfer::GROUP_AUTH;
    }

    public function getPasswordHistory(): int
    {
        return $this->passwordHistory;
    }

    public function getSessionDuration(): int
    {
        return $this->sessionDuration;
    }

    public function getSessionsLimit(): int
    {
        return $this->sessionsLimit;
    }

    public function getUserLimit(): int
    {
        return $this->userLimit;
    }

    public function getPasswordDictionary(): bool
    {
        return $this->passwordDictionary;
    }

    public function getPersonalDataCheck(): bool
    {
        return $this->personalDataCheck;
    }

    public function getSessionAlerts(): bool
    {
        return $this->sessionAlerts;
    }

    public function getSessionInvalidation(): bool
    {
        return $this->sessionInvalidation;
    }

    public function getMembershipsUserId(): bool
    {
        return $this->membershipsUserId;
    }

    public function getMembershipsUserEmail(): bool
    {
        return $this->membershipsUserEmail;
    }

    public function getMembershipsUserName(): bool
    {
        return $this->membershipsUserName;
    }

    public function getMembershipsUserMfa(): bool
    {
        return $this->membershipsUserMfa;
    }

    public function getMembershipsUserPhone(): bool
    {
        return $this->membershipsUserPhone;
    }
}
