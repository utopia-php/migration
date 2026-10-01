<?php

namespace Utopia\Migration\Sources\Appwrite;

use Appwrite\AppwriteException;
use Appwrite\Client as Base;
use Override;

class Client extends Base
{
    private const PROTOCOLS = CURLPROTO_HTTP | CURLPROTO_HTTPS;

    private const RESPONSE_LOCATION = 'location';

    /**
     * @var (\Closure(string): array<string>)|null
     */
    private ?\Closure $resolver = null;

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
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $params
     * @return array<mixed>|string
     *
     * @throws AppwriteException
     */
    #[Override]
    public function call(
        string $method,
        string $path = '',
        array $headers = [],
        array $params = [],
        ?string $responseType = null
    ) {
        $headers = \array_merge($this->headers, $headers);
        $querySeparator = \str_contains($path, '?') ? '&' : '?';
        $url = $this->endpoint.$path.(($method === self::METHOD_GET && ! empty($params)) ? $querySeparator.\http_build_query($params) : '');
        $resolve = $this->resolver !== null ? ($this->resolver)($url) : null;
        $ch = \curl_init($url);
        $responseHeaders = [];

        $query = match ($headers['content-type']) {
            'application/json' => \json_encode($this->prepareParams($params)),
            'multipart/form-data' => $this->flatten($params),
            default => \http_build_query($params),
        };

        foreach ($headers as $i => $header) {
            $headers[] = $i.':'.$header;
            unset($headers[$i]);
        }

        \curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        \curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        \curl_setopt($ch, CURLOPT_USERAGENT, php_uname('s').'-'.php_uname('r').':php-'.phpversion());
        \curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        \curl_setopt($ch, CURLOPT_PROTOCOLS, self::PROTOCOLS);
        \curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, self::PROTOCOLS);
        \curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        if ($resolve !== null) {
            \curl_setopt($ch, CURLOPT_RESOLVE, $resolve);
        }
        \curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header) use (&$responseHeaders) {
            $length = \strlen($header);
            $header = \explode(':', \strtolower($header), 2);

            if (\count($header) < 2) {
                return $length;
            }

            $responseHeaders[\strtolower(\trim($header[0]))] = \trim($header[1]);

            return $length;
        });

        if ($method !== self::METHOD_GET) {
            \curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
        }

        if ($this->selfSigned) {
            \curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            \curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }

        if ($this->timeout !== null) {
            \curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        }

        if ($this->connectTimeout !== null) {
            \curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        }

        $responseBody = \curl_exec($ch);
        $contentType = $responseHeaders['content-type'] ?? '';
        $responseStatus = \curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $warnings = $responseHeaders['x-appwrite-warning'] ?? '';
        if ($warnings) {
            foreach (\explode(';', $warnings) as $warning) {
                \trigger_error($warning, E_USER_WARNING);
            }
        }

        if (\str_starts_with($contentType, 'application/json')) {
            $responseBody = \json_decode($responseBody, true);
        }

        if (\curl_errno($ch)) {
            throw new AppwriteException(\curl_error($ch), $responseStatus, $responseBody['type'] ?? '', $responseBody);
        }

        if ($responseType !== self::RESPONSE_LOCATION && $responseStatus >= 300 && $responseStatus < 400) {
            throw new AppwriteException($responseStatus.': Redirects are not followed', $responseStatus);
        }

        if ($responseStatus >= 400) {
            if (\is_array($responseBody)) {
                throw new AppwriteException($responseBody['message'], $responseStatus, $responseBody['type'] ?? '', \json_encode($responseBody));
            }

            throw new AppwriteException($responseBody, $responseStatus, '', $responseBody);
        }

        if ($responseType === self::RESPONSE_LOCATION) {
            return $responseHeaders['location'];
        }

        return $responseBody;
    }
}
