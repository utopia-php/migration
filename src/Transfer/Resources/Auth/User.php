<?php

namespace Utopia\Transfer\Resources\Auth;

use Utopia\Transfer\Resource;
use Utopia\Transfer\Resources\Auth\Hash;
use Utopia\Transfer\Transfer;

class User extends Resource
{
    public const TYPE_EMAIL = 'email';
    public const TYPE_PHONE = 'phone';
    public const TYPE_ANONYMOUS = 'anonymous';
    public const TYPE_MAGIC = 'magic';
    public const TYPE_OAUTH = 'oauth';

    protected string $id = '';
    protected string $email = '';
    protected string $username = '';
    protected ?Hash $passwordHash = null;
    protected string $phone = '';
    protected array $types = [self::TYPE_ANONYMOUS];
    protected string $oauthProvider = '';
    protected bool $emailVerified = false;
    protected bool $phoneVerified = false;
    protected bool $disabled = false;
    protected array $preferences = [];

    public function __construct(
        string $id = '',
        string $email = '',
        string $username = '',
        ?Hash $passwordHash = null,
        string $phone = '',
        array $types = [self::TYPE_ANONYMOUS],
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
        $this->types = $types;
        $this->oauthProvider = $oauthProvider;
        $this->emailVerified = $emailVerified;
        $this->phoneVerified = $phoneVerified;
        $this->disabled = $disabled;
        $this->preferences = $preferences;
    }

    /**
     * Get Name
     *
     * @return string
     */
    public function getName(): string
    {
        return Resource::TYPE_USER;
    }

    /**
     * Get ID
     *
     * @return string
    */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Set ID
     *
     * @param string $id
     * @return self
     */
    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Get Email
     *
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Set Email
     *
     * @param string $email
     * @return self
     */
    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    /**
     * Get Username
     *
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * Set Username
     *
     * @param string $username
     * @return self
     */
    public function setUsername(string $username): self
    {
        $this->username = $username;
        return $this;
    }

    /**
     * Get Password Hash
     *
     * @return Hash
     */
    public function getPasswordHash(): Hash
    {
        return $this->passwordHash;
    }

    /**
     * Set Password Hash
     *
     * @param Hash $passwordHash
     * @return self
     */
    public function setPasswordHash(Hash $passwordHash): self
    {
        $this->passwordHash = $passwordHash;
        return $this;
    }

    /**
     * Get Phone
     *
     * @return string
     */
    public function getPhone(): string
    {
        return $this->phone;
    }

    /**
     * Set Phone
     *
     * @param string $phone
     * @return self
     */
    public function setPhone(string $phone): self
    {
        $this->phone = $phone;
        return $this;
    }

    /**
     * Get Type
     *
     * @return array
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    /**
     * Set Types
     *
     * @param string $types
     * @return self
     */
    public function setTypes(string $types): self
    {
        $this->types = $types;
        return $this;
    }

    /**
     * Get OAuth Provider
     *
     * @return string
     */
    public function getOAuthProvider(): string
    {
        return $this->oauthProvider;
    }

    /**
     * Set OAuth Provider
     *
     * @param string $oauthProvider
     * @return self
     */
    public function setOAuthProvider(string $oauthProvider): self
    {
        $this->oauthProvider = $oauthProvider;
        return $this;
    }

    /**
     * Get Email Verified
     *
     * @return bool
     */
    public function getEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    /**
     * Set Email Verified
     *
     * @param bool $verified
     * @return self
     */
    public function setEmailVerified(bool $verified): self
    {
        $this->emailVerified = $verified;
        return $this;
    }

    /**
     * Get Email Verified
     *
     * @return bool
     */
    public function getPhoneVerified(): bool
    {
        return $this->phoneVerified;
    }

    /**
     * Set Phone Verified
     *
     * @param bool $verified
     * @return self
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
     *
     * @return bool
     */
    public function getDisabled(): bool
    {
        return $this->disabled;
    }

    /**
     * Set Disabled
     *
     * @param bool $disabled
     * @return self
     */
    public function setDisabled(bool $disabled): self
    {
        $this->disabled = $disabled;
        return $this;
    }

    /**
     * Get Preferences
     *
     * @return array
     */
    public function getPreferences(): array
    {
        return $this->preferences;
    }

    /**
     * Set Preferences
     *
     * @param array $preferences
     * @return self
     */
    public function setPreferences(array $preferences): self
    {
        $this->preferences = $preferences;
        return $this;
    }

    /**
     * As Array
     *
     * @return array
     */
    public function asArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'username' => $this->username,
            'passwordHash' => $this->passwordHash ? $this->passwordHash->asArray() : null,
            'phone' => $this->phone,
            'types' => $this->types,
            'oauthProvider' => $this->oauthProvider,
            'emailVerified' => $this->emailVerified,
            'phoneVerified' => $this->phoneVerified,
        ];
    }
}
