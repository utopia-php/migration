<?php

namespace Utopia\Transfer\Resources\Storage;

use Utopia\Transfer\Resource;
use Utopia\Transfer\Transfer;

class File extends Resource
{
    protected string $id;
    protected Bucket $bucket;
    protected string $name;
    protected string $signature;
    protected string $mimeType;
    protected array $permissions;
    protected int $size;

    public function __construct(string $id = '', Bucket $bucket = null, string $name = '', string $signature = '', string $mimeType = '', array $permissions = [], int $size = 0)
    {
        $this->id = $id;
        $this->bucket = $bucket;
        $this->name = $name;
        $this->signature = $signature;
        $this->mimeType = $mimeType;
        $this->permissions = $permissions;
        $this->size = $size;
    }

    public function getName(): string
    {
        return Resource::TYPE_FILE;
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

    public function getBucket(): Bucket
    {
        return $this->bucket;
    }

    public function setBucket(Bucket $bucket): self
    {
        $this->bucket = $bucket;
        return $this;
    }

    public function getFileName(): string
    {
        return $this->name;
    }

    public function setFileName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getSignature(): string
    {
        return $this->signature;
    }

    public function setSignature(string $signature): self
    {
        $this->signature = $signature;
        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): self
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): self
    {
        $this->size = $size;
        return $this;
    }

    public function asArray(): array
    {
        return [
            'id' => $this->id,
            'bucket' => $this->bucket->asArray(),
            'name' => $this->name,
            'signature' => $this->signature,
            'mimeType' => $this->mimeType,
            'size' => $this->size,
        ];
    }
}
