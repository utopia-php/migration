<?php

namespace Utopia\Transfer;

use Utopia\Transfer\ResourceCache;

abstract class Target
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
     * Resource Cache
     *
     * @var ResourceCache $resourceCache
     */
    protected $resourceCache;

    /**
     * Endpoint
     *
     * @var string $endpoint
     */
    protected $endpoint = '';

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
     * Register Transfer Cache
     *
     * @param ResourceCache &$cache
     *
     * @return void
     */
    //TODO: Pretty sure there is a better way to do this instead of passing by reference
    public function registerTransferCache(ResourceCache &$cache): void
    {
        $this->resourceCache = &$cache;
    }

    /**
     * Run Transfer
     *
     * @param array $resources
     * @param callable $callback
     */
    abstract public function run(array $resources, callable $callback): void;

    /**
     * Check Requirements
     * Performs a suite of API Checks, Resource Checks, etc... to ensure the adapter is ready to be used.
     * This is highly recommended to be called before any other method after initialization.
     *
     * If no resources are provided, the method should check all resources.
     * Returns a object with all the keys of the resources provided and a true|string value if the resource is available or not.
     * If the resource is not available, the value should be a string with the error message.
     *
     * @string[] $resources
     *
     * @return string[]
     */
    abstract public function check(array $resources = []): array;

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

        $responseType   = $responseHeaders['Content-Type'] ?? $responseHeaders['content-type'] ?? '';
        $responseStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        switch (substr($responseType, 0, strpos($responseType, ';'))) {
            case 'application/json':
                $responseBody = json_decode($responseBody, true);
                break;
        }

        if (curl_errno($ch)) {
            var_dump(curl_errno($ch));
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
}
