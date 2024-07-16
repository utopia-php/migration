<?php

namespace Utopia\Migration;

class Exception extends \Exception
{
    public string $resourceName;

    public string $resourceGroup;

    public string $resourceId;

    public function __construct(
        string $resourceName,
        string $resourceGroup,
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
        string $resourceId = ''
    ) {
        $this->resourceName = $resourceName;
        $this->resourceId = $resourceId;
        $this->resourceGroup = $resourceGroup;

        parent::__construct($message, $code, $previous);
    }

    public function getResourceName(): string
    {
        return $this->resourceName;
    }

    public function getResourceGroup(): string
    {
        return $this->resourceGroup;
    }

    public function getResourceId(): string
    {
        return $this->resourceId;
    }
}
