<?php

namespace Utopia\Transfer;

abstract class Resource
{
    protected string $id = '';

    /**
     * Gets the name of the adapter.
     * 
     * @return string
     */
    abstract public function getName(): string;

    /**
     * Get ID
     * 
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Set ID
     * 
     * @param string $id
     * @return self
     */
    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * As Array
     * 
     * @returns array
     */
    abstract public function asArray(): array;
}