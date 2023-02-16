<?php

namespace Utopia\Transfer;

class Progress
{
    function __construct(
        private string $resourceType,
        private int $timestamp, 
        private int $total = 0, 
        private int $current = 0, 
        private int $failed = 0, 
        private int $skipped = 0
        ){}
    
    /**
     * Get Resource Type
     * 
     * @return string
     */
    public function getResourceType(): string
    {
        return $this->resourceType;
    }

    /**
     * Set Resource Type
     * 
     * @param string $resourceType
     * @return self
     */

    public function setResourceType(string $resourceType): self
    {
        $this->resourceType = $resourceType;
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
     * Get Total
     * 
     * @return int
     */
    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * Set Total
     * 
     * @param int $total
     * @return self
     */
    public function setTotal(int $total): self
    {
        $this->total = $total;
        return $this;
    }

    /**
     * Get Current
     * 
     * @return int
     */
    public function getCurrent(): int
    {
        return $this->current;
    }

    /**
     * Set Current
     * 
     * @param int $current
     * @return self
     */
    public function setCurrent(int $current): self
    {
        $this->current = $current;
        return $this;
    }

    /**
     * Get Failed
     * 
     * @return int
     */
    public function getFailed(): int
    {
        return $this->failed;
    }

    /**
     * Set Failed
     * 
     * @param int $failed
     * @return self
     */
    public function setFailed(int $failed): self
    {
        $this->failed = $failed;
        return $this;
    }

    /**
     * Get Skipped
     * 
     * @return int
     */
    public function getSkipped(): int
    {
        return $this->skipped;
    }

    /**
     * Set Skipped
     * 
     * @param int $skipped
     * @return self
     */
    public function setSkipped(int $skipped): self
    {
        $this->skipped = $skipped;
        return $this;
    }

    /**
     * Get Progress
     * 
     * @return float
     */
    public function getProgress(): float
    {
        return ($this->current / $this->total) * 100;
    }

    /**
     * Get Remaining
     * 
     * @return int
     */
    public function getRemaining(): int
    {
        return $this->total - $this->current;
    }

    /**
     * Get ETA
     * 
     * @return int
     */
    public function getETA(): int
    {
        return 0;
    }

    /**
     * As Array
     * 
     * @return array
     */
    public function asArray(): array
    {
        return [
            'resourceType' => $this->resourceType,
            'timestamp' => $this->timestamp,
            'total' => $this->total,
            'current' => $this->current,
            'failed' => $this->failed,
            'skipped' => $this->skipped,
            'progress' => $this->getProgress(),
            'remaining' => $this->getRemaining(),
            'eta' => $this->getETA(),
        ];
    }

}