<?php

namespace Utopia\Transfer\Resources\Storage;

use Utopia\Transfer\Resource;
use Utopia\Transfer\Transfer;

class FileData extends Resource
{
    protected string $data;
    protected int $start;
    protected int $end;
    protected File $file;

    public function __construct(string $data, int $start, int $end, File $file)
    {
        $this->data = $data;
        $this->start = $start;
        $this->end = $end;
        $this->file = $file;
    }

    public static function getName(): string
    {
        return Resource::TYPE_FILEDATA;
    }

    public function getGroup(): string
    {
        return Transfer::GROUP_STORAGE;
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function getStart(): int
    {
        return $this->start;
    }

    public function getEnd(): int
    {
        return $this->end;
    }

    public function getFile(): File
    {
        return $this->file;
    }

    public function asArray(): array
    {
        return [
            'start' => $this->start,
            'end' => $this->end,
            'file' => $this->file->asArray(),
        ];
    }
}
