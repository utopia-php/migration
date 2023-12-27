<?php

namespace Utopia\Migration\Extends\Logger;

use Exception;
use Utopia\Logger\Adapter;
use Utopia\Logger\Log;
use Utopia\Logger\Logger;

class Redis extends Adapter
{
    protected \Redis $redis;

    /**
     * Return unique adapter name
     *
     * @return string
     */
    public static function getName(): string
    {
        return 'redis';
    }

    public function __construct(string $redisConnection)
    {
        $this->redis = new \Redis();
        $this->redis->connect($redisConnection);
    }

    public function getSupportedTypes(): array
    {
        return [
            Log::TYPE_INFO,
            Log::TYPE_DEBUG,
            Log::TYPE_VERBOSE,
            Log::TYPE_WARNING,
            Log::TYPE_ERROR,
        ];
    }

    public function getSupportedEnvironments(): array
    {
        return [
            Log::ENVIRONMENT_STAGING,
            Log::ENVIRONMENT_PRODUCTION,
        ];
    }

    public function getSupportedBreadcrumbTypes(): array
    {
        return [
            Log::TYPE_INFO,
            Log::TYPE_DEBUG,
            Log::TYPE_VERBOSE,
            Log::TYPE_WARNING,
            Log::TYPE_ERROR,
        ];
    }

    /**
     * Push log to external provider
     *
     * @param  Log  $log
     * @return int
     *
     * @throws Exception
     */
    public function push(Log $log): int
    {
        $params = [];

        foreach ($log->getExtra() as $paramKey => $paramValue) {
            $params[$paramKey] = var_export($paramValue, true);
        }

        $breadcrumbsObject = $log->getBreadcrumbs();
        $breadcrumbsArray = [];

        foreach ($breadcrumbsObject as $breadcrumb) {
            \array_push($breadcrumbsArray, [
                'timestamp' => \intval($breadcrumb->getTimestamp()),
                'category' => $breadcrumb->getCategory(),
                'action' => $breadcrumb->getMessage(),
                'metadata' => [
                    'type' => $breadcrumb->getType(),
                ],
            ]);
        }

        $tags = [];

        foreach ($log->getTags() as $tagKey => $tagValue) {
            $tags[$tagKey] = $tagValue;
        }

        if (! empty($log->getType())) {
            $tags['type'] = $log->getType();
        }
        if (! empty($log->getUser()) && ! empty($log->getUser()->getId())) {
            $tags['userId'] = $log->getUser()->getId();
        }
        if (! empty($log->getUser()) && ! empty($log->getUser()->getUsername())) {
            $tags['userName'] = $log->getUser()->getUsername();
        }
        if (! empty($log->getUser()) && ! empty($log->getUser()->getEmail())) {
            $tags['userEmail'] = $log->getUser()->getEmail();
        }

        $tags['sdk'] = 'utopia-logger/'.Logger::LIBRARY_VERSION;

        $requestBody = [
            'timestamp' => \intval($log->getTimestamp()),
            'namespace' => $log->getNamespace(),
            'error' => [
                'name' => $log->getMessage(),
                'message' => $log->getMessage(),
                'backtrace' => [],
            ],
            'environment' => [
                'environment' => $log->getEnvironment(),
                'server' => $log->getServer(),
                'version' => $log->getVersion(),
            ],
            'revision' => $log->getVersion(),
            'action' => $log->getAction(),
            'params' => $params,
            'tags' => $tags,
            'breadcrumbs' => $breadcrumbsArray,
        ];

        if ($redis->lLen('utopia-logger') > 1000) {
            $redis->lTrim('utopia-logger', 0, 1000);
        }

        $redis->close();

        return $redis->lPush('utopia-logger', \json_encode($requestBody));
    }
}