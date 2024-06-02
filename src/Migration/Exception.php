<?php

namespace Utopia\Migration;

class Exception extends \Exception
{
    public string $resourceType;

    public string $resourceId;

    public function __construct(
        string $resourceType,
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
        string $resourceId = ''
    ) {
        $this->resourceId = $resourceId;
        $this->resourceType = $resourceType;

        parent::__construct($message, $code, $previous);
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
