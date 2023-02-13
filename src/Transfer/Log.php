<?php

namespace Utopia\Transfer;

class Log {
    const INFO = 'info';
    const WARNING = 'warning';
    const ERROR = 'error';
    const FATAL = 'fatal';
    const SUCCESS = 'success';
    const DEBUG = 'debug';

    public function __construct(private string $message = '', private int $timestamp = 0, protected Resource|null $resource = null)
    {
        $timestamp = \time();
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