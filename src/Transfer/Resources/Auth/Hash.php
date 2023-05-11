<?php

namespace Utopia\Transfer\Resources\Auth;

use Utopia\Transfer\Resource;
use Utopia\Transfer\Transfer;

/**
 * Helper class for hashing.
 */

class Hash extends Resource
{
    public const SCRYPT_MODIFIED = 'ScryptModified';
    public const BCRYPT = 'Bcrypt';
    public const MD5 = 'MD5';
    public const ARGON2 = 'Argon2';
    public const SHA256 = 'SHA256';
    public const PHPASS = 'PHPass';
    public const SCRYPT = 'Scrypt';
    public const PLAINTEXT = 'PlainText';

    private string $hash;
    private string $salt = '';
    private string $algorithm = self::SHA256;
    private string $separator = '';
    private string $signingKey = '';
    private int $passwordCpu = 0;
    private int $passwordMemory = 0;
    private int $passwordParallel = 0;
    private int $passwordLength = 0;

    public function __construct(string $hash, string $salt = '', string $algorithm = self::SHA256, string $separator = '', string $signingKey = '', int $passwordCpu = 0, int $passwordMemory = 0, int $passwordParallel = 0, int $passwordLength = 0)
    {
        $this->hash = $hash;
        $this->salt = $salt;
        $this->algorithm = $algorithm;
        $this->separator = $separator;
        $this->signingKey = $signingKey;
        $this->passwordCpu = $passwordCpu;
        $this->passwordMemory = $passwordMemory;
        $this->passwordParallel = $passwordParallel;
        $this->passwordLength = $passwordLength;
    }

    static function getName(): string
    {
        return Resource::TYPE_HASH;
    }

    public function getGroup(): string
    {
        return Transfer::GROUP_AUTH;
    }

    /**
     * Get Hash
     *
     * @return string
     */
    public function getHash(): string
    {
        return $this->hash;
    }

    /**
     * Set Hash
     *
     * @param string $hash
     * @return self
     */
    public function setHash(string $hash): self
    {
        $this->hash = $hash;
        return $this;
    }

    /**
     * Get Salt
     *
     * @return string
     */
    public function getSalt(): string
    {
        return $this->salt;
    }

    /**
     * Set Salt
     *
     * @param string $salt
     * @return self
     */
    public function setSalt(string $salt): self
    {
        $this->salt = $salt;
        return $this;
    }

    /**
     * Get Algorithm
     *
     * @return string
     */
    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    /**
     * Set Algorithm
     *
     * @param string $algorithm
     * @return self
     */
    public function setAlgorithm(string $algorithm): self
    {
        $this->algorithm = $algorithm;
        return $this;
    }

    /**
     * Get Separator
     *
     * @return string
     */
    public function getSeparator(): string
    {
        return $this->separator;
    }

    /**
     * Set Separator
     *
     * @param string $separator
     * @return self
     */
    public function setSeparator(string $separator): self
    {
        $this->separator = $separator;
        return $this;
    }

    /**
     * Get Signing Key
     *
     * @return string
     */
    public function getSigningKey(): string
    {
        return $this->signingKey;
    }

    /**
     * Set Signing Key
     *
     * @param string $signingKey
     * @return self
     */
    public function setSigningKey(string $signingKey): self
    {
        $this->signingKey = $signingKey;
        return $this;
    }

    /**
     * Get Password CPU
     *
     * @return int
     */
    public function getPasswordCpu(): int
    {
        return $this->passwordCpu;
    }

    /**
     * Set Password CPU
     *
     * @param int $passwordCpu
     * @return self
     */
    public function setPasswordCpu(int $passwordCpu): self
    {
        $this->passwordCpu = $passwordCpu;
        return $this;
    }

    /**
     * Get Password Memory
     *
     * @return int
     */
    public function getPasswordMemory(): int
    {
        return $this->passwordMemory;
    }

    /**
     * Set Password Memory
     *
     * @param int $passwordMemory
     * @return self
     */
    public function setPasswordMemory(int $passwordMemory): self
    {
        $this->passwordMemory = $passwordMemory;
        return $this;
    }

    /**
     * Get Password Parallel
     *
     * @return int
     */
    public function getPasswordParallel(): int
    {
        return $this->passwordParallel;
    }

    /**
     * Set Password Parallel
     *
     * @param int $passwordParallel
     * @return self
     */
    public function setPasswordParallel(int $passwordParallel): self
    {
        $this->passwordParallel = $passwordParallel;
        return $this;
    }

    /**
     * Get Password Length
     *
     * @return int
     */
    public function getPasswordLength(): int
    {
        return $this->passwordLength;
    }

    /**
     * Set Password Length
     *
     * @param int $passwordLength
     * @return self
     */
    public function setPasswordLength(int $passwordLength): self
    {
        $this->passwordLength = $passwordLength;
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
