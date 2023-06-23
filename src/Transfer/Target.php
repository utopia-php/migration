<?php

namespace Utopia\Transfer;

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
     * Cache
     *
     * @var Cache
     */
    public $cache;

    /**
     * Endpoint
     *
     * @var string
     */
    protected $endpoint = '';

    /**
     * Gets the name of the adapter.
     */
    abstract public static function getName(): string;

    /**
     * Get Supported Resources
     */
    abstract static function getSupportedResources(): array;

    /**
     * Register Cache
     */
    public function registerCache(Cache &$cache): void
    {
        $this->cache = &$cache;
    }

    /**
     * Run Transfer
     */
    abstract public function run(array $resources, callable $callback): void;

    /**
     * Report Resources
     *
     * This function performs a count of all resources that are available for transfer.
     * It also serves a secondary purpose of checking if the API is available for the given adapter.
     *
     * On Destinations, this function should just return nothing but still check if the API is available.
     * If any issues are found then an exception should be thrown with an error message.
     */
    abstract public function report(array $resources = []): array;

    /**
     * Call
     *
     * Make an API call
     *
     * @throws \Exception
     */
    protected function call(string $method, string $path = '', array $headers = [], array $params = []): array|string
    {
        $headers = array_merge($this->headers, $headers);
        $ch = curl_init((str_contains($path, 'http') ? $path.(($method == 'GET' && !empty($params)) ? '?'.http_build_query($params) : '') : $this->endpoint.$path.(($method == 'GET' && !empty($params)) ? '?'.http_build_query($params) : '')));
        $responseHeaders = [];
        $responseStatus = -1;
        $responseType = '';
        $responseBody = '';

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
            $headers[] = $i.':'.$header;
            unset($headers[$i]);
        }

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, php_uname('s').'-'.php_uname('r').':php-'.phpversion());
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

        $responseBody = curl_exec($ch);

        $responseType = $responseHeaders['Content-Type'] ?? $responseHeaders['content-type'] ?? '';
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
                throw new \Exception($responseStatus.': '.$responseBody);
            }
        }

        return $responseBody;
    }

    /**
     * Flatten params array to PHP multiple format
     */
    protected function flatten(array $data, string $prefix = ''): array
    {
        $output = [];

        foreach ($data as $key => $value) {
            $finalKey = $prefix ? "{$prefix}[{$key}]" : $key;

            if (is_array($value)) {
                $output += $this->flatten($value, $finalKey);
            } else {
                $output[$finalKey] = $value;
            }
        }

        return $output;
    }
}
