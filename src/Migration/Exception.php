<?php

namespace Utopia\Migration;

class Exception extends \Exception
{
    public string $resourceName;

    public string $resourceType;

    public string $resourceId;

    public function __construct(string $resourceName, string $resourceType, string $message, int $code = 0, ?\Throwable $previous = null, string $resourceId = '')
    {
        $this->resourceName = $resourceName;
        $this->resourceId = $resourceId;
        $this->resourceType = $resourceType;

        parent::__construct($message, $code, $previous);
    }

    public function getResourceName(): string
    {
        return $this->resourceName;
    }

    public function getResourceType(): string
    {
        return $this->resourceType;
    }

    public function getResourceId(): string
    {
        return $this->resourceId;
    }
}
