<?php

namespace Utopia\Transfer;

class Log
{
    public const INFO = 'info';
    public const WARNING = 'warning';
    public const ERROR = 'error';
    public const FATAL = 'fatal';
    public const SUCCESS = 'success';
    public const DEBUG = 'debug';

    private string $message = '';
    private int $timestamp = 0;
    protected ?Resource $resource = null;

    public function __construct(string $message = '', int $timestamp = 0, ?Resource $resource = null)
    {
        $this->message = $message;
        $this->timestamp = $timestamp ?? \time();
        $this->resource = $resource;
    }
    /**
     * Get Message
     *
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Set Message
     *
     * @param string $message
     * @return self
     */
    public function setMessage(string $message): self
    {
        $this->message = $message;
        return $this;
    }

    /**
     * Get Timestamp
     *
     * @return int
     */
    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * Set Timestamp
     *
     * @param int $timestamp
     * @return self
     */
    public function setTimestamp(int $timestamp): self
    {
        $this->timestamp = $timestamp;
        return $this;
    }

    /**
     * Get Resource
     *
     * @return Resource|null
     */
    public function getResource(): ?Resource
    {
        return $this->resource;
    }

    /**
     * As Array
     *
     * @return array
    */
    public function asArray(): array
    {
        return [
            'message' => $this->message,
            'timestamp' => $this->timestamp,
            'resource' => $this->resource ? $this->resource->asArray() : null,
        ];
    }
}
