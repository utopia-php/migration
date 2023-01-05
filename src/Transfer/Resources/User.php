<?php

namespace Utopia\Transfer\Resources;

use Utopia\Transfer\Resource;
use Utopia\Transfer\Hash;

class User extends Resource
{
    const AUTH_EMAIL = 'email';
    const AUTH_PHONE = 'phone';
    const AUTH_ANONYMOUS = 'anonymous';
    const AUTH_MAGIC = 'magic';
    const AUTH_OAUTH = 'oauth';

    public function __construct(
        protected string $id = '',
        protected string $email = '',
        protected Hash $passwordHash = new Hash(''),
        protected string $phone = '',
        protected string $type = self::AUTH_ANONYMOUS,
        protected string $oauthProvider = ''
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
     * @returns string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Set Type
     * 
     * @param string $type
     * @returns self
     */
    public function setType(string $type): self
    {
        $this->type = $type;
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
}