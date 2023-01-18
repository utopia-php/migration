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
     * @returns string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Set Message
     * 
     * @param string $message
     * @returns self
     */
    public function setMessage(string $message): self
    {
        $this->message = $message;
        return $this;
    }

    /**
     * Get Timestamp
     * 
     * @returns int
     */
    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * Set Timestamp
     * 
     * @param int $timestamp
     * @returns self
     */
    public function setTimestamp(int $timestamp): self
    {
        $this->timestamp = $timestamp;
        return $this;
    }

    /**
     * Get Resource
     * 
     * @returns Resource|null
     */
    public function getResource(): ?Resource
    {
        return $this->resource;
    }

    /**
     * As Array
     * 
     * @returns array
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