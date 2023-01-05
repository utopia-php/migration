<?php

namespace Utopia\Transfer;

class Log {
    const INFO = 'info';
    const WARNING = 'warning';
    const ERROR = 'error';
    const DEBUG = 'debug';

    public function __construct(private string $level = Log::INFO, private string $message = '', private int $timestamp = 0, protected Resource $resource = null)
    {
        $timestamp = \time();
    }

    /** 
     * Get Level
     * 
     * @returns string
    */
    public function getLevel(): string
    {
        return $this->level;
    }

    /**
     * Set Level
     * 
     * @param string $level
     * @returns self
     */
    public function setLevel(string $level)
    {
        $this->level = $level;
        return $this;
    }

    /**
     * Get Message
     * 
     * @returns string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Set Message
     * 
     * @param string $message
     * @returns self
     */
    public function setMessage(string $message)
    {
        $this->message = $message;
        return $this;
    }

    /**
     * Get Timestamp
     * 
     * @returns string
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * Set Timestamp
     * 
     * @param string $timestamp
     * @returns self
     */
    public function setTimestamp(string $timestamp)
    {
        $this->timestamp = $timestamp;
        return $this;
    }
}