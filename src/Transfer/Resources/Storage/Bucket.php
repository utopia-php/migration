<?php

namespace Utopia\Transfer\Resources\Storage;

use Utopia\Transfer\Resource;
use Utopia\Transfer\Transfer;

class Bucket extends Resource
{
    protected string $id;
    protected array $permissions;
    protected bool $fileSecurity;
    protected string $name;
    protected bool $enabled;
    protected int $maxFileSize;
    protected array $allowedFileExtensions;
    protected string $compression;
    protected bool $encryption;
    protected bool $antiVirus;

    public function __construct(string $id = '', array $permissions = [], bool $fileSecurity = false, string $name = '', bool $enabled = false, int $maxFileSize = 0, array $allowedFileExtensions = [], string $compression = '', bool $encryption = false, bool $antiVirus = false)
    {
        $this->id = $id;
        $this->permissions = $permissions;
        $this->fileSecurity = $fileSecurity;
        $this->name = $name;
        $this->enabled = $enabled;
        $this->maxFileSize = $maxFileSize;
        $this->allowedFileExtensions = $allowedFileExtensions;
        $this->compression = $compression;
        $this->encryption = $encryption;
        $this->antiVirus = $antiVirus;
    }

    public function getName(): string
    {
        return Resource::TYPE_BUCKET;
    }

    public function getGroup(): string
    {
        return Transfer::GROUP_STORAGE;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function setPermissions(array $permissions): self
    {
        $this->permissions = $permissions;
        return $this;
    }

    public function getFileSecurity(): bool
    {
        return $this->fileSecurity;
    }

    public function setFileSecurity(bool $fileSecurity): self
    {
        $this->fileSecurity = $fileSecurity;
        return $this;
    }

    public function getBucketName(): string
    {
        return $this->name;
    }

    public function setBucketName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function getMaxFileSize(): int
    {
        return $this->maxFileSize;
    }

    public function setMaxFileSize(int $maxFileSize): self
    {
        $this->maxFileSize = $maxFileSize;
        return $this;
    }

    public function getAllowedFileExtensions(): array
    {
        return $this->allowedFileExtensions;
    }

    public function setAllowedFileExtensions(array $allowedFileExtensions): self
    {
        $this->allowedFileExtensions = $allowedFileExtensions;
        return $this;
    }

    public function getCompression(): string
    {
        return $this->compression;
    }

    public function setCompression(string $compression): self
    {
        $this->compression = $compression;
        return $this;
    }

    public function getEncryption(): bool
    {
        return $this->encryption;
    }

    public function setEncryption(bool $encryption): self
    {
        $this->encryption = $encryption;
        return $this;
    }

    public function getAntiVirus(): bool
    {
        return $this->antiVirus;
    }

    public function setAntiVirus(bool $antiVirus): self
    {
        $this->antiVirus = $antiVirus;
        return $this;
    }

    public function asArray(): array
    {
        return [
            'id' => $this->id,
            'permissions' => $this->permissions,
            'fileSecurity' => $this->fileSecurity,
            'name' => $this->name,
            'enabled' => $this->enabled,
            'maxFileSize' => $this->maxFileSize,
            'allowedFileExtensions' => $this->allowedFileExtensions,
            'compression' => $this->compression,
            'encryption' => $this->encryption,
            'antiVirus' => $this->antiVirus,
        ];
    }
}
