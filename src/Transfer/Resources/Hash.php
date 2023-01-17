<?php

namespace Utopia\Transfer\Resources;

use Utopia\Transfer\Resource;

/**
 * Helper class for hashing.
 */

class Hash extends Resource {
    public const SCRYPT_MODIFIED = 'ScryptModified';
    public const BCRYPT = 'Bcrypt';
    public const MD5 = 'MD5';
    public const ARGON2 = 'Argon2';
    public const SHA256 = 'SHA256';
    public const PHPASS = 'PHPass';
    public const SCRYPT = 'Scrypt';


    public function __construct(private string $hash, private string $salt = '', private string $algorithm = self::SHA256, private string $separator = '', private string $signingKey = '', private int $passwordCpu = 0, private int $passwordMemory = 0, private int $passwordParallel = 0, private int $passwordLength = 0)
    {
    }

    public function getName(): string
    {
        return 'hash';
    }

    /**
     * Get Hash
     * 
     * @returns string
     */
    public function getHash(): string
    {
        return $this->hash;
    }

    /**
     * Set Hash
     * 
     * @param string $hash
     * @returns self
     */
    public function setHash(string $hash): self
    {
        $this->hash = $hash;
        return $this;
    }

    /**
     * Get Salt
     * 
     * @returns string
     */
    public function getSalt(): string
    {
        return $this->salt;
    }

    /**
     * Set Salt
     * 
     * @param string $salt
     * @returns self
     */
    public function setSalt(string $salt): self
    {
        $this->salt = $salt;
        return $this;
    }

    /**
     * Get Algorithm
     * 
     * @returns string
     */
    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    /**
     * Set Algorithm
     * 
     * @param string $algorithm
     * @returns self
     */
    public function setAlgorithm(string $algorithm): self
    {
        $this->algorithm = $algorithm;
        return $this;
    }

    /**
     * Get Separator
     * 
     * @returns string
     */
    public function getSeparator(): string
    {
        return $this->separator;
    }

    /**
     * Set Separator
     * 
     * @param string $separator
     * @returns self
     */
    public function setSeparator(string $separator): self
    {
        $this->separator = $separator;
        return $this;
    }

    /**
     * Get Signing Key
     * 
     * @returns string
     */
    public function getSigningKey(): string
    {
        return $this->signingKey;
    }

    /**
     * Set Signing Key
     * 
     * @param string $signingKey
     * @returns self
     */
    public function setSigningKey(string $signingKey): self
    {
        $this->signingKey = $signingKey;
        return $this;
    }

    /**
     * Get Password CPU
     * 
     * @returns int
     */
    public function getPasswordCpu(): int
    {
        return $this->passwordCpu;
    }

    /**
     * Set Password CPU
     * 
     * @param int $passwordCpu
     * @returns self
     */
    public function setPasswordCpu(int $passwordCpu): self
    {
        $this->passwordCpu = $passwordCpu;
        return $this;
    }

    /**
     * Get Password Memory
     * 
     * @returns int
     */
    public function getPasswordMemory(): int
    {
        return $this->passwordMemory;
    }

    /**
     * Set Password Memory
     * 
     * @param int $passwordMemory
     * @returns self
     */
    public function setPasswordMemory(int $passwordMemory): self
    {
        $this->passwordMemory = $passwordMemory;
        return $this;
    }

    /**
     * Get Password Parallel
     * 
     * @returns int
     */
    public function getPasswordParallel(): int
    {
        return $this->passwordParallel;
    }

    /**
     * Set Password Parallel
     * 
     * @param int $passwordParallel
     * @returns self
     */
    public function setPasswordParallel(int $passwordParallel): self
    {
        $this->passwordParallel = $passwordParallel;
        return $this;
    }

    /**
     * Get Password Length
     * 
     * @returns int
     */
    public function getPasswordLength(): int
    {
        return $this->passwordLength;
    }

    /**
     * Set Password Length
     * 
     * @param int $passwordLength
     * @returns self
     */
    public function setPasswordLength(int $passwordLength): self
    {
        $this->passwordLength = $passwordLength;
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
            'hash' => $this->hash,
            'salt' => $this->salt,
            'algorithm' => $this->algorithm,
            'separator' => $this->separator,
            'signingKey' => $this->signingKey,
            'passwordCpu' => $this->passwordCpu,
            'passwordMemory' => $this->passwordMemory,
            'passwordParallel' => $this->passwordParallel,
            'passwordLength' => $this->passwordLength,
        ];
    }
}