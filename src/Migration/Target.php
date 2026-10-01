<?php

namespace Utopia\Migration;

abstract class Target
{
    private const string PROTOCOLS = 'http,https';

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
     * Errors
     *
     * @var array<Exception>
     */
    public array $errors = [];

    /**
     * Warnings
     *
     * @var array<Warning>
     */
    public array $warnings = [];

    protected string $endpoint = '';

    protected bool $followRedirects = false;

    /**
     * @var (\Closure(string): array<string>)|null
     */
    protected ?\Closure $resolver = null;

    protected string $rootResourceId = '';

    protected string $rootResourceType = '';

    abstract public static function getName(): string;

    abstract public static function getSupportedResources(): array;

    public function registerCache(Cache &$cache): void
    {
        $this->cache = &$cache;
    }

    public function setFollowRedirects(bool $followRedirects): static
    {
        $this->followRedirects = $followRedirects;

        return $this;
    }

    /**
     * The resolver receives each request URL and returns the CURLOPT_RESOLVE
     * entries ("host:port:address[,address]") the connection must use. It
     * throws to refuse the URL.
     *
     * @param  (\Closure(string): array<string>)|null  $resolver
     */
    public function setResolver(?\Closure $resolver): static
    {
        $this->resolver = $resolver;

        return $this;
    }

    /**
     * Run Transfer
     *
     * @param  array<string>  $resources  Resources to transfer
     * @param  callable  $callback  Callback to run after transfer
     * @param  string  $rootResourceId  Root resource ID, If enabled you can only transfer a single root resource
     */
    abstract public function run(array $resources, callable $callback, string $rootResourceId = ''): void;

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
     * @param  array<string, array<string>>  $resourceIds  Map of resource type to IDs. Only top-level resources supported.
     * @return array<string, int>
     *
     * @throws \Exception if resourceIds contains non-top-level resource types
     */
    abstract public function report(array $resources = [], array $resourceIds = []): array;

    /**
     * Make an API call
     *
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $params
     * @param  array<string, string>  $responseHeaders
     * @return array<mixed>|string
     *
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

        $url = \str_contains($path, 'http') ? $path : $this->endpoint.$path;
        if ($method === 'GET' && ! empty($params)) {
            $url .= '?'.\http_build_query($params);
        }

        $ch = \curl_init($url);

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
        \curl_setopt($ch, CURLOPT_PROTOCOLS_STR, self::PROTOCOLS);
        \curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS_STR, self::PROTOCOLS);
        \curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $this->followRedirects);
        if ($this->resolver !== null) {
            \curl_setopt($ch, CURLOPT_RESOLVE, ($this->resolver)($url));
        }
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
            throw new \Exception(\curl_error($ch), Exception::CODE_INTERNAL);
        }

        if (! $this->followRedirects && $responseStatus >= 300 && $responseStatus < 400) {
            throw new \Exception($responseStatus.': Redirects are not followed', $responseStatus);
        }

        if ($responseStatus >= 400) {
            if (\is_array($responseBody)) {
                throw new \Exception(\json_encode($responseBody), $responseStatus);
            } else {
                throw new \Exception($responseStatus.': '.$responseBody, $responseStatus);
            }
        }

        return $responseBody;
    }

    /**
     * Flatten params array to PHP multiple format
     *
     * @param  array<string, mixed>  $data
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
     * Validate that resourceIds only contains top-level resources
     */
    protected function validateResourceIds(array $resourceIds): void
    {
        foreach (array_keys($resourceIds) as $resourceType) {
            if (!in_array($resourceType, Transfer::ROOT_RESOURCES)) {
                throw new \Exception(
                    'Invalid resource type in resourceIds: ' . $resourceType . '. ' .
                    'Only top-level resources are supported: ' . implode(', ', Transfer::ROOT_RESOURCES)
                );
            }
        }
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
     * Add Error
     */
    public function addError(Exception $error): void
    {
        $this->errors[] = $error;
    }

    /**
     * Get Warnings
     *
     * @returns array<Warning>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Add Warning
     */
    public function addWarning(Warning $warning): void
    {
        $this->warnings[] = $warning;
    }

    /**
     * Completion callback
     */
    public function shutdown(): void
    {
    }

    /**
     * Success callback
     */
    public function success(): void
    {
    }

    /**
     * Error callback
     */
    public function error(): void
    {
    }

    /**
     * Clean up callback
     */
    public function cleanUp(): void
    {
    }
}
