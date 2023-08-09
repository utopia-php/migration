<?php

namespace Utopia\Migration\Resources\Storage;

use Utopia\Migration\Resource;
use Utopia\Migration\Transfer;

class File extends Resource
{
    protected Bucket $bucket;

    protected string $name;

    protected string $signature;

    protected string $mimeType;

    protected int $size;

    protected string $data;

    protected int $start;

    protected int $end;

    public function __construct(string $id = '', Bucket $bucket = null, string $name = '', string $signature = '', string $mimeType = '', array $permissions = [], int $size = 0, string $data = '', int $start = 0, int $end = 0)
    {
        $this->id = $id;
        $this->bucket = $bucket;
        $this->name = $name;
        $this->signature = $signature;
        $this->mimeType = $mimeType;
        $this->permissions = $permissions;
        $this->size = $size;
        $this->data = $data;
        $this->start = $start;
        $this->end = $end;
    }

    public static function getName(): string
    {
        return Resource::TYPE_FILE;
    }

    public function getGroup(): string
    {
        return Transfer::GROUP_STORAGE;
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

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSize(): int
    {
        return $this->size;
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

    public function getData(): string
    {
        return $this->data;
    }

    public function setData(string $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function getStart(): int
    {
        return $this->start;
    }

    public function setStart(int $start): self
    {
        $this->start = $start;

        return $this;
    }

    public function getEnd(): int
    {
        return $this->end;
    }

    public function setEnd(int $end): self
    {
        $this->end = $end;

        return $this;
    }

    public function getSizeInBytes(): int
    {
        return strlen($this->data);
    }

    public function getChunkSize(): int
    {
        return $this->end - $this->start;
    }

    public function asArray(): array
    {
        return [
            'id' => $this->id,
            'bucket' => $this->bucket->getId(),
            'name' => $this->name,
            'signature' => $this->signature,
            'mimeType' => $this->mimeType,
            'permissions' => $this->permissions,
            'size' => $this->size,
            'start' => $this->start,
            'end' => $this->end,
        ];
    }
}
