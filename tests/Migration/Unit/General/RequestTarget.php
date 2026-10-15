<?php

namespace Utopia\Tests\Unit\General;

use Override;
use Utopia\Migration\Target;

final class RequestTarget extends Target
{
    #[Override]
    public static function getName(): string
    {
        return 'Request';
    }

    #[Override]
    public static function getSupportedResources(): array
    {
        return [];
    }

    #[Override]
    public function run(array $resources, callable $callback, string $rootResourceId = ''): void
    {
    }

    #[Override]
    public function report(array $resources = [], array $resourceIds = []): array
    {
        return [];
    }

    /**
     * @return array<mixed>|string
     *
     * @throws \Exception
     */
    public function fetch(string $url): array|string
    {
        return $this->call('GET', $url);
    }
}
