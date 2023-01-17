<?php

namespace Utopia\Transfer\Resources;

use Utopia\Transfer\Resource;
use Utopia\Transfer\Resources\Hash;

class User extends Resource
{
    const TYPE_EMAIL = 'email';
    const TYPE_PHONE = 'phone';
    const TYPE_ANONYMOUS = 'anonymous';
    const TYPE_MAGIC = 'magic';
    const TYPE_OAUTH = 'oauth';

    public function __construct(
        protected string $id = '',
        protected string $email = '',
        protected string $username = '',
        protected Hash $passwordHash = new Hash(''),
        protected string $phone = '',
        protected array $types = [Self::TYPE_ANONYMOUS],
        protected string $oauthProvider = '',
        protected bool $emailVerified = false,
        protected bool $phoneVerified = false,
        protected bool $disabled = false,
        protected array $preferences = []
    ){}

    /**
     * Get Name
     * 
     * @returns string
     */
    public function getName(): string
    {
        return 'user';
    }

    /** 
     * Get ID
     * 
     * @returns string
    */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Set ID
     * 
     * @param string $id
     * @returns self
     */
    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Get Email
     * 
     * @returns string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Set Email
     * 
     * @param string $email
     * @returns self
     */
    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    /**
     * Get Username
     * 
     * @returns string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * Set Username
     * 
     * @param string $username
     * @returns self
     */
    public function setUsername(string $username): self
    {
        $this->username = $username;
        return $this;
    }

    /**
     * Get Password Hash
     * 
     * @returns Hash
     */
    public function getPasswordHash(): Hash
    {
        return $this->passwordHash;
    }

    /**
     * Set Password Hash
     * 
     * @param Hash $passwordHash
     * @returns self
     */
    public function setPasswordHash(Hash $passwordHash): self
    {
        $this->passwordHash = $passwordHash;
        return $this;
    }

    /**
     * Get Phone
     * 
     * @returns string
     */
    public function getPhone(): string
    {
        return $this->phone;
    }

    /**
     * Set Phone
     * 
     * @param string $phone
     * @returns self
     */
    public function setPhone(string $phone): self
    {
        $this->phone = $phone;
        return $this;
    }

    /**
     * Get Type
     * 
     * @returns array
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    /**
     * Set Types
     * 
     * @param string $types
     * @returns self
     */
    public function setTypes(string $types): self
    {
        $this->types = $types;
        return $this;
    }

    /**
     * Get OAuth Provider
     * 
     * @returns string
     */
    public function getOAuthProvider(): string
    {
        return $this->oauthProvider;
    }

    /**
     * Set OAuth Provider
     * 
     * @param string $oauthProvider
     * @returns self
     */
    public function setOAuthProvider(string $oauthProvider): self
    {
        $this->oauthProvider = $oauthProvider;
        return $this;
    }

    /**
     * Get Email Verified
     * 
     * @returns bool
     */
    public function getEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    /**
     * Set Email Verified
     * 
     * @param bool $verified
     * @returns self
     */
    public function setEmailVerified(bool $verified): self
    {
        $this->emailVerified = $verified;
        return $this;
    }

    /**
     * Get Email Verified
     * 
     * @returns bool
     */
    public function getPhoneVerified(): bool
    {
        return $this->phoneVerified;
    }

    /**
     * Set Phone Verified
     * 
     * @param bool $verified
     * @returns self
     */
    public function setPhoneVerified(bool $verified): self
    {
        $this->phoneVerified = $verified;
        return $this;
    }

    /**
     * Get Disabled
     * 
     * @returns bool
     */
    public function getDisabled(): bool
    {
        return $this->disabled;
    }

    /**
     * Set Disabled
     * 
     * @param bool $disabled
     * @returns self
     */
    public function setDisabled(bool $disabled): self
    {
        $this->disabled = $disabled;
        return $this;
    }

    /**
     * Get Preferences
     * 
     * @returns array
     */
    public function getPreferences(): array
    {
        return $this->preferences;
    }

    /**
     * Set Preferences
     * 
     * @param array $preferences
     * @returns self
     */
    public function setPreferences(array $preferences): self
    {
        $this->preferences = $preferences;
        return $this;
    }

    /**
     * As Array
     * 
     * @returns array
     */
    public function asArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'username' => $this->username,
            'passwordHash' => $this->passwordHash->asArray(),
            'phone' => $this->phone,
            'types' => $this->types,
            'oauthProvider' => $this->oauthProvider,
            'emailVerified' => $this->emailVerified,
            'phoneVerified' => $this->phoneVerified,
        ];
    }
}