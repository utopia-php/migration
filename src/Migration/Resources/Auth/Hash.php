<?php

namespace Utopia\Migration\Resources\Auth;

use Utopia\Migration\Resource;
use Utopia\Migration\Transfer;

/**
 * Helper class for hashing.
 */
class Hash extends Resource
{
    public const ALGORITHM_SCRYPT_MODIFIED = 'scryptModified';

    public const ALGORITHM_BCRYPT = 'bcrypt';

    public const ALGORITHM_MD5 = 'md5';

    public const ALGORITHM_ARGON2 = 'argon2';

    public const ALGORITHM_SHA256 = 'sha256';

    public const ALGORITHM_PHPASS = 'phpass';

    public const ALGORITHM_SCRYPT = 'scrypt';

    public const ALGORITHM_PLAINTEXT = 'plainText';

    private string $hash;

    private string $salt = '';

    private string $algorithm = self::ALGORITHM_SHA256;

    private string $separator = '';

    private string $signingKey = '';

    private int $passwordCpu = 0;

    private int $passwordMemory = 0;

    private int $passwordParallel = 0;

    private int $passwordLength = 0;

    public function __construct(string $hash, string $salt = '', string $algorithm = self::ALGORITHM_SHA256, string $separator = '', string $signingKey = '', int $passwordCpu = 0, int $passwordMemory = 0, int $passwordParallel = 0, int $passwordLength = 0)
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

    public static function getName(): string
    {
        return Resource::TYPE_HASH;
    }

    public function getGroup(): string
    {
        return Transfer::GROUP_AUTH;
    }

    /**
     * Get Hash
     */
    public function getHash(): string
    {
        return $this->hash;
    }

    /**
     * Set Hash
     */
    public function setHash(string $hash): self
    {
        $this->hash = $hash;

        return $this;
    }

    /**
     * Get Salt
     */
    public function getSalt(): string
    {
        return $this->salt;
    }

    /**
     * Set Salt
     */
    public function setSalt(string $salt): self
    {
        $this->salt = $salt;

        return $this;
    }

    /**
     * Get Algorithm
     */
    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    /**
     * Set Algorithm
     */
    public function setAlgorithm(string $algorithm): self
    {
        $this->algorithm = $algorithm;

        return $this;
    }

    /**
     * Get Separator
     */
    public function getSeparator(): string
    {
        return $this->separator;
    }

    /**
     * Set Separator
     */
    public function setSeparator(string $separator): self
    {
        $this->separator = $separator;

        return $this;
    }

    /**
     * Get Signing Key
     */
    public function getSigningKey(): string
    {
        return $this->signingKey;
    }

    /**
     * Set Signing Key
     */
    public function setSigningKey(string $signingKey): self
    {
        $this->signingKey = $signingKey;

        return $this;
    }

    /**
     * Get Password CPU
     */
    public function getPasswordCpu(): int
    {
        return $this->passwordCpu;
    }

    /**
     * Set Password CPU
     */
    public function setPasswordCpu(int $passwordCpu): self
    {
        $this->passwordCpu = $passwordCpu;

        return $this;
    }

    /**
     * Get Password Memory
     */
    public function getPasswordMemory(): int
    {
        return $this->passwordMemory;
    }

    /**
     * Set Password Memory
     */
    public function setPasswordMemory(int $passwordMemory): self
    {
        $this->passwordMemory = $passwordMemory;

        return $this;
    }

    /**
     * Get Password Parallel
     */
    public function getPasswordParallel(): int
    {
        return $this->passwordParallel;
    }

    /**
     * Set Password Parallel
     */
    public function setPasswordParallel(int $passwordParallel): self
    {
        $this->passwordParallel = $passwordParallel;

        return $this;
    }

    /**
     * Get Password Length
     */
    public function getPasswordLength(): int
    {
        return $this->passwordLength;
    }

    /**
     * Set Password Length
     */
    public function setPasswordLength(int $passwordLength): self
    {
        $this->passwordLength = $passwordLength;

        return $this;
    }

    /**
     * As Array
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
