<?php

namespace Utopia\Transfer\Resources;

use Utopia\Transfer\Resource;

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

    public function getName(): string
    {
        return 'FileData';
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
            'data' => $this->data,
            'start' => $this->start,
            'end' => $this->end,
            'file' => $this->file->asArray(),
        ];
    }
}
