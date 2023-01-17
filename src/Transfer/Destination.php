<?php

namespace Utopia\Transfer;

use Exception;

abstract class Destination
{
    /**
     * Global Headers
     *
     * @var array
     */
    protected $headers = [
        'Content-Type' => '',
    ];

    /**
     * Logs
     * 
     * @var array $logs
     */
    protected $logs = [];

    /**
     * Resource Cache
     * 
     * @var array $resourceCache
     */
    protected $resourceCache = [];

    /**
     * Internal Adapter State
     * 
     * @var array $state
     */
    protected $state = [];

    /**
     * Constructor, mainly handles state initialization.
     * 
     * Automatically detects if we are running within Swoole and uses a Swoole table instead of a PHP array.
     */
    public function __construct(protected string $endpoint, protected string $projectID, protected string $key)
    {
        $this->state = [];
    }

    /**
     * Gets the name of the adapter.
     * 
     * @return string
     */
    abstract public function getName(): string;

    /**
     * Get Supported Resources
     * 
     * @return array
     */
    abstract public function getSupportedResources(): array;

    /**
     * Register Logs Array
     * 
     * @param array &$logs
     */
    public function registerLogs(array &$logs): void {
        $this->logs = &$logs;
    }

    /**
     * Register Resource Cache
     * 
     * @param array &$cache
     */
    public function registerResourceCache(array &$cache): void {
        $this->resourceCache = &$cache;
    }

    /**
     * Transfer Resources to Destination from Source callback
     * 
     * @param array $resources
     * @param callable $callback
     */
    public function run(array $resources, callable $callback, Source $source): void {
        foreach ($resources as $resource) {
            if (!in_array($resource, $this->getSupportedResources())) {
                $this->logs[Log::FATAL] = new Log("Cannot Transfer unsupported resource: '".$resource."'");
                throw new \Exception("Cannot Transfer unsupported resource: '".$resource."'");
            }

            $source->run($resources, function (Log $currentLog, string $resourceType, array $resource) use ($callback) {
                switch ($resourceType) {
                    case Transfer::RESOURCE_USERS: {
                        $this->importUsers($resource);
                        break;
                    }
                }
                $callback($currentLog, $resourceType, $resource);
            });
        }
    }

    /**
     * Check Requirements
     * Performs a suite of API Checks, Resource Checks, etc... to ensure the adapter is ready to be used.
     * This is highly recommended to be called before any other method after initialization.
     * 
     * If no resources are provided, the method should check all resources.
     * 
     * @array $resources
     * 
     * @return bool
     */
    abstract public function check(array $resources = []): bool;

    /**
     * Call
     *
     * Make an API call
     *
     * @param string $method
     * @param string $path
     * @param array $params
     * @param array $headers
     * @return array|string
     * @throws \Exception
     */
    public function call(string $method, string $path = '', array $headers = array(), array $params = array()): array|string
    {
        $headers            = array_merge($this->headers, $headers);
        $ch                 = curl_init((str_contains($path, 'http') ? $path : $this->endpoint . $path . (($method == 'GET' && !empty($params)) ? '?' . http_build_query($params) : '')));
        $responseHeaders    = [];
        $responseStatus     = -1;
        $responseType       = '';
        $responseBody       = '';

        switch ($headers['Content-Type']) {
            case 'application/json':
                $query = json_encode($params);
                break;

            case 'multipart/form-data':
                $query = $this->flatten($params);
                break;

            default:
                $query = http_build_query($params);
                break;
        }

        foreach ($headers as $i => $header) {
            $headers[] = $i . ':' . $header;
            unset($headers[$i]);
        }

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, php_uname('s') . '-' . php_uname('r') . ':php-' . phpversion());
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header) use (&$responseHeaders) {
            $len = strlen($header);
            $header = explode(':', strtolower($header), 2);

            if (count($header) < 2) { // ignore invalid headers
                return $len;
            }

            $responseHeaders[strtolower(trim($header[0]))] = trim($header[1]);

            return $len;
        });

        if ($method != 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
        }

        $responseBody   = curl_exec($ch);

        $responseType   = $responseHeaders['Content-Type'] ?? '';
        $responseStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        switch (substr($responseType, 0, strpos($responseType, ';'))) {
            case 'application/json':
                $responseBody = json_decode($responseBody, true);
                break;
        }

        if (curl_errno($ch)) {
            throw new \Exception(curl_error($ch));
        }

        curl_close($ch);

        if ($responseStatus >= 400) {
            if (is_array($responseBody)) {
                throw new \Exception(json_encode($responseBody));
            } else {
                throw new \Exception($responseStatus . ': ' . $responseBody);
            }
        }

        return $responseBody;
    }

    /**
     * Flatten params array to PHP multiple format
     *
     * @param array $data
     * @param string $prefix
     * @return array
     */
    protected function flatten(array $data, string $prefix = ''): array
    {
        $output = [];

        foreach ($data as $key => $value) {
            $finalKey = $prefix ? "{$prefix}[{$key}]" : $key;

            if (is_array($value)) {
                $output += $this->flatten($value, $finalKey); // @todo: handle name collision here if needed
            } else {
                $output[$finalKey] = $value;
            }
        }

        return $output;
    }

    /**
     * Import Users
     * 
     * @param array $users
     */
    protected function importUsers(array $users): void {
        throw new \Exception("Not Implemented");
    }
}
