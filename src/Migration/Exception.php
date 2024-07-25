<?php

namespace Utopia\Migration;

class Exception extends \Exception implements \JsonSerializable
{
    public string $resourceName;

    public string $resourceGroup;

    public ?string $resourceId;

    public function __construct(
        string $resourceName,
        string $resourceGroup,
        string $resourceId = null,
        string $message = '',
        int $code = 0,
        \Throwable $previous = null,
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
        return $this->resourceId ?? '';
    }

    public function jsonSerialize(): array
    {
        return [
            'code' => $this->getCode(),
            'message' => $this->getMessage(),
            'resourceName' => $this->resourceName,
            'resourceGroup' => $this->resourceGroup,
            'resourceId' => $this->resourceId,
            'trace' => $this->getTrace(),
        ];
    }
}
