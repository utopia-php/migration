<?php

namespace Utopia\Migration\Resources\Auth;

use Utopia\Migration\Resource;
use Utopia\Migration\Transfer;

class User extends Resource
{
    public const string TYPE_PASSWORD = 'password';

    public const string TYPE_PHONE = 'phone';

    public const string TYPE_ANONYMOUS = 'anonymous';

    public const string TYPE_MAGIC = 'magic';

    public const string TYPE_OAUTH = 'oauth';

    /**
     * @param string $id
     * @param string $email
     * @param string $username
     * @param Hash|null $passwordHash
     * @param string $phone
     * @param array<string> $types
     * @param array<string> $labels
     * @param string $oauthProvider
     * @param bool $emailVerified
     * @param bool $phoneVerified
     * @param bool $disabled
     * @param array<string, mixed> $preferences
     */
    public function __construct(
        string $id,
        private readonly string $email = '',
        private readonly string $username = '',
        private readonly ?Hash $passwordHash = null,
        private readonly string $phone = '',
        private readonly array $types = [self::TYPE_ANONYMOUS],
        private readonly array $labels = [],
        private readonly string $oauthProvider = '',
        private readonly bool $emailVerified = false,
        private readonly bool $phoneVerified = false,
        private readonly bool $disabled = false,
        private readonly array $preferences = []
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
            $array['email'] ?? '',
            $array['username'] ?? '',
            $array['passwordHash'] ?? null,
            $array['phone'] ?? '',
            $array['types'] ?? [self::TYPE_ANONYMOUS],
            $array['labels'] ?? [],
            $array['oauthProvider'] ?? '',
            $array['emailVerified'] ?? false,
            $array['phoneVerified'] ?? false,
            $array['disabled'] ?? false,
            $array['preferences'] ?? []
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'username' => $this->username,
            'passwordHash' => $this->passwordHash,
            'phone' => $this->phone,
            'types' => $this->types,
            'labels' => $this->labels,
            'oauthProvider' => $this->oauthProvider,
            'emailVerified' => $this->emailVerified,
            'phoneVerified' => $this->phoneVerified,
            'disabled' => $this->disabled,
            'preferences' => $this->preferences,
        ];
    }

    /**
     * Get Name
     */
    public static function getName(): string
    {
        return Resource::TYPE_USER;
    }

    /**
     * Get Email
     */
    public function getEmail(): string
    {
        return $this->email;
    }
    /**
     * Get Username
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * Get Password Hash
     */
    public function getPasswordHash(): ?Hash
    {
        return $this->passwordHash;
    }

    /**
     * Get Phone
     */
    public function getPhone(): string
    {
        return $this->phone;
    }

    /**
     * Get Type
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    /**
     * Get Labels
     */
    public function getLabels(): array
    {
        return $this->labels;
    }

    /**
     * Get OAuth Provider
     */
    public function getOAuthProvider(): string
    {
        return $this->oauthProvider;
    }

    /**
     * Get Email Verified
     */
    public function getEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    /**
     * Get Email Verified
     */
    public function getPhoneVerified(): bool
    {
        return $this->phoneVerified;
    }

    public function getGroup(): string
    {
        return Transfer::GROUP_AUTH;
    }

    /**
     * Get Disabled
     */
    public function getDisabled(): bool
    {
        return $this->disabled;
    }

    /**
     * Get Preferences
     */
    public function getPreferences(): array
    {
        return $this->preferences;
    }
}
