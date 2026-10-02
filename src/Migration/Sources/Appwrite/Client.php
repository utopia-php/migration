<?php

namespace Utopia\Migration\Sources\Appwrite;

use Ahc\Jwt\JWT;
use Appwrite\AppwriteException;
use Appwrite\Client as Base;
use Override;

class Client extends Base
{
    private const string PROTOCOLS = 'http,https';

    private const string RESPONSE_LOCATION = 'location';

    /**
     * @var (\Closure(string): array<string>)|null
     */
    private ?\Closure $resolver = null;

    /**
     * The resolver receives each request URL and returns the CURLOPT_RESOLVE
     * entries ("host:port:address[,address]") the connection must use. It
     * throws to refuse the URL. While a resolver is set, requests never go
     * through a proxy, which would resolve the host itself.
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
    ): mixed {
        if ($this->key !== null) {
            $this->headers['authorization'] = $this->authorization();
        }

        $headers = \array_merge($this->headers, $headers);

        $url = $this->endpoint.$path;
        if ($method === self::METHOD_GET && ! empty($params)) {
            $url .= (\str_contains($path, '?') ? '&' : '?').\http_build_query($params);
        }

        $resolve = $this->resolver === null ? null : ($this->resolver)($url);

        $query = match ($headers['content-type']) {
            'application/json' => \json_encode($this->prepareParams($params)),
            'multipart/form-data' => $this->flatten($params),
            default => \http_build_query($params),
        };

        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name.':'.$value;
        }

        $responseHeaders = [];

        $curl = \curl_init($url);
        \curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        \curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        \curl_setopt($curl, CURLOPT_USERAGENT, php_uname('s').'-'.php_uname('r').':php-'.phpversion());
        \curl_setopt($curl, CURLOPT_HTTPHEADER, $lines);
        \curl_setopt($curl, CURLOPT_PROTOCOLS_STR, self::PROTOCOLS);
        \curl_setopt($curl, CURLOPT_REDIR_PROTOCOLS_STR, self::PROTOCOLS);
        \curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
        if ($resolve !== null) {
            \curl_setopt($curl, CURLOPT_RESOLVE, $resolve);
            \curl_setopt($curl, CURLOPT_PROXY, '');
        }
        \curl_setopt($curl, CURLOPT_HEADERFUNCTION, function ($curl, string $header) use (&$responseHeaders): int {
            $length = \strlen($header);
            $parts = \explode(':', \strtolower($header), 2);

            if (\count($parts) < 2) {
                return $length;
            }

            $responseHeaders[\trim($parts[0])] = \trim($parts[1]);

            return $length;
        });

        if ($method !== self::METHOD_GET) {
            \curl_setopt($curl, CURLOPT_POSTFIELDS, $query);
        }

        if ($this->selfSigned) {
            \curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            \curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        }

        if ($this->timeout !== null) {
            \curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
        }

        if ($this->connectTimeout !== null) {
            \curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        }

        $responseBody = \curl_exec($curl);
        $contentType = $responseHeaders['content-type'] ?? '';
        $responseStatus = \curl_getinfo($curl, CURLINFO_HTTP_CODE);

        $warnings = $responseHeaders['x-appwrite-warning'] ?? '';
        if ($warnings) {
            foreach (\explode(';', $warnings) as $warning) {
                \trigger_error($warning, E_USER_WARNING);
            }
        }

        if (\is_string($responseBody) && \str_starts_with($contentType, 'application/json')) {
            $responseBody = \json_decode($responseBody, true);
        }

        if (\curl_errno($curl)) {
            throw new AppwriteException(\curl_error($curl), $responseStatus, $responseBody['type'] ?? '', \is_string($responseBody) ? $responseBody : null);
        }

        if ($responseType !== self::RESPONSE_LOCATION && $responseStatus >= 300 && $responseStatus < 400) {
            throw new AppwriteException($responseStatus.': Redirects are not followed', $responseStatus);
        }

        if ($responseStatus >= 400) {
            if (\is_array($responseBody)) {
                throw new AppwriteException($responseBody['message'], $responseStatus, $responseBody['type'] ?? '', \json_encode($responseBody) ?: null);
            }

            throw new AppwriteException((string) $responseBody, $responseStatus, '', (string) $responseBody);
        }

        if ($responseType === self::RESPONSE_LOCATION) {
            return $responseHeaders['location'] ?? '';
        }

        return $responseBody;
    }

    private function authorization(): string
    {
        if (\is_string($this->authorization) && $this->authorizationExpiresAt > new \DateTime()) {
            return $this->authorization;
        }

        $jwt = new JWT((string) $this->key, maxAge: self::JWT_MAX_AGE_SECONDS);
        $this->authorization = 'Bearer '.$jwt->encode([]);
        $this->authorizationExpiresAt = (new \DateTime())->modify('+'.(self::JWT_MAX_AGE_SECONDS - 5).' seconds');

        return $this->authorization;
    }
}
