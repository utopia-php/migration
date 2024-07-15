<?php

namespace Utopia\Migration\Resources\Auth;

use Utopia\Migration\Resource;
use Utopia\Migration\Transfer;

class User extends Resource
{
    protected ?string $email = null;

    protected ?string $username = null;

    protected ?Hash $passwordHash = null;

    protected ?string $phone = null;

    protected array $labels = [];

    protected string $oauthProvider = '';

    protected bool $emailVerified = false;

    protected bool $phoneVerified = false;

    protected bool $disabled = false;

    protected array $preferences = [];

    public function __construct(
        string $id,
        ?string $email = null,
        ?string $username = null,
        ?Hash $passwordHash = null,
        ?string $phone = null,
        array $labels = [],
        string $oauthProvider = '',
        bool $emailVerified = false,
        bool $phoneVerified = false,
        bool $disabled = false,
        array $preferences = []
    ) {
        $this->id = $id;
        $this->email = $email;
        $this->username = $username;
        $this->passwordHash = $passwordHash;
        $this->phone = $phone;
        $this->labels = $labels;
        $this->oauthProvider = $oauthProvider;
        $this->emailVerified = $emailVerified;
        $this->phoneVerified = $phoneVerified;
        $this->disabled = $disabled;
        $this->preferences = $preferences;
    }

    /**
     * Get Name
     */
    public static function getName(): string
    {
        return Resource::TYPE_USER;
    }

    public function isRoot(): bool
    {
        return true;
    }

    /**
     * Get Email
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * Set Email
     */
    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get Username
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * Set Username
     */
    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    /**
     * Get Password Hash
     */
    public function getPasswordHash(): ?Hash
    {
        return $this->passwordHash;
    }

    /**
     * Set Password Hash
     */
    public function setPasswordHash(Hash $passwordHash): self
    {
        $this->passwordHash = $passwordHash;

        return $this;
    }

    /**
     * Get Phone
     */
    public function getPhone(): ?string
    {
        return $this->phone;
    }

    /**
     * Set Phone
     */
    public function setPhone(string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    /**
     * Get Labels
     */
    public function getLabels(): array
    {
        return $this->labels;
    }

    /**
     * Set Labels
     */
    public function setLabels(array $labels): self
    {
        $this->labels = $labels;

        return $this;
    }

    /**
     * Get OAuth Provider
     */
    public function getOAuthProvider(): string
    {
        return $this->oauthProvider;
    }

    /**
     * Set OAuth Provider
     */
    public function setOAuthProvider(string $oauthProvider): self
    {
        $this->oauthProvider = $oauthProvider;

        return $this;
    }

    /**
     * Get Email Verified
     */
    public function getEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    /**
     * Set Email Verified
     */
    public function setEmailVerified(bool $verified): self
    {
        $this->emailVerified = $verified;

        return $this;
    }

    /**
     * Get Email Verified
     */
    public function getPhoneVerified(): bool
    {
        return $this->phoneVerified;
    }

    /**
     * Set Phone Verified
     */
    public function setPhoneVerified(bool $verified): self
    {
        $this->phoneVerified = $verified;

        return $this;
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
     * Set Disabled
     */
    public function setDisabled(bool $disabled): self
    {
        $this->disabled = $disabled;

        return $this;
    }

    /**
     * Get Preferences
     */
    public function getPreferences(): array
    {
        return $this->preferences;
    }

    /**
     * Set Preferences
     */
    public function setPreferences(array $preferences): self
    {
        $this->preferences = $preferences;

        return $this;
    }

    /**
     * As Array
     */
    public function asArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'username' => $this->username,
            'passwordHash' => $this->passwordHash ? $this->passwordHash->asArray() : null,
            'phone' => $this->phone,
            'oauthProvider' => $this->oauthProvider,
            'emailVerified' => $this->emailVerified,
            'phoneVerified' => $this->phoneVerified,
        ];
    }
}
