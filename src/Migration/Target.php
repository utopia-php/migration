<?php

namespace Utopia\Migration;

abstract class Target
{
    /**
     * Global Headers
     *
     * @var array<string, string>
     */
    protected array $headers = [
        'Content-Type' => '',
    ];

    public Cache $cache;

    /**
     * @var array<\Exception>
     */
    public array $errors = [];

    protected $endpoint = '';

    abstract public static function getName(): string;

    abstract public static function getSupportedResources(): array;

    public function registerCache(Cache &$cache): void
    {
        $this->cache = &$cache;
    }

    /**
     * Run Transfer
     *
     * @param  array<string>  $resources  Resources to transfer
     * @param  callable  $callback  Callback to run after transfer
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
     *
     * @param  array<string>  $resources  Resources to report
     * @return array<string, int>
     */
    abstract public function report(array $resources = []): array;

    /**
     * Make an API call
     *
     * @param string $method
     * @param string $path
     * @param array<string, string> $headers
     * @param array<string, mixed> $params
     * @param array<string, string> $responseHeaders
     * @return array<mixed>|string
     * @throws \Exception
     */
    protected function call(
        string $method,
        string $path = '',
        array $headers = [],
        array $params = [],
        array &$responseHeaders = []
    ): array|string {
        $headers = \array_merge($this->headers, $headers);
        $ch = \curl_init((
            \str_contains($path, 'http')
            ? $path.(($method == 'GET' && ! empty($params)) ? '?' . \http_build_query($params) : '')
            : $this->endpoint.$path.(
                ($method == 'GET' && ! empty($params))
                ? '?' . \http_build_query($params)
                : ''
            )
        ));

        $query = match ($headers['Content-Type']) {
            'application/json' => \json_encode($params),
            'multipart/form-data' => $this->flatten($params),
            default => \http_build_query($params),
        };

        foreach ($headers as $i => $header) {
            $headers[] = $i.':'.$header;
            unset($headers[$i]);
        }

        if ($method === 'HEAD') {
            \curl_setopt($ch, CURLOPT_NOBODY, true);
        } else {
            \curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }
        \curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        \curl_setopt($ch, CURLOPT_USERAGENT, php_uname('s').'-'.php_uname('r').':php-'.phpversion());
        \curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        \curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        \curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header) use (&$responseHeaders) {
            $len = strlen($header);
            $header = explode(':', strtolower($header), 2);

            if (\count($header) < 2) { // ignore invalid headers
                return $len;
            }

            $responseHeaders[\strtolower(\trim($header[0]))] = \trim($header[1]);

            return $len;
        });

        if ($method != 'GET') {
            \curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
        }

        $responseBody = curl_exec($ch);

        $responseType = $responseHeaders['Content-Type'] ?? $responseHeaders['content-type'] ?? '';
        $responseStatus = \curl_getinfo($ch, CURLINFO_HTTP_CODE);

        switch (\substr($responseType, 0, \strpos($responseType, ';'))) {
            case 'application/json':
                $responseBody = \json_decode($responseBody, true);
                break;
        }

        if (\curl_errno($ch)) {
            throw new \Exception(\curl_error($ch));
        }

        \curl_close($ch);

        if ($responseStatus >= 400) {
            if (\is_array($responseBody)) {
                throw new \Exception(\json_encode($responseBody));
            } else {
                throw new \Exception($responseStatus.': '.$responseBody);
            }
        }

        return $responseBody;
    }

    /**
     * Flatten params array to PHP multiple format
     *
     * @param array<string, mixed> $data
     * @param string $prefix
     * @return array<string, mixed>
     */
    protected function flatten(array $data, string $prefix = ''): array
    {
        $output = [];

        foreach ($data as $key => $value) {
            $finalKey = $prefix ? "{$prefix}[{$key}]" : $key;

            if (\is_array($value)) {
                $output += $this->flatten($value, $finalKey);
            } else {
                $output[$finalKey] = $value;
            }
        }

        return $output;
    }

    /**
     * Get Errors
     *
     * @returns array<Exception>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Set Errors
     *
     * @param  array<Exception>  $errors
     */
    public function setErrors(array $errors): void
    {
        $this->errors = $errors;
    }

    public function addError(Exception $error): void
    {
        $this->errors[] = $error;
    }
}
