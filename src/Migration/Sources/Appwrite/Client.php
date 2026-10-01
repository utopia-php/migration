<?php

namespace Utopia\Migration\Sources\Appwrite;

use Appwrite\Client as Base;
use Override;
use Utopia\Client\Adapter\Curl\Client as Curl;
use Utopia\Client as HttpClient;
use Utopia\Migration\Sources\Appwrite\Client\RedirectRefusal;

class Client extends Base
{
    private const string PROTOCOLS = 'http,https';

    /**
     * @var (\Closure(string): array<string>)|null
     */
    private ?\Closure $resolver = null;

    /**
     * The resolver receives the endpoint URL and returns the CURLOPT_RESOLVE
     * entries ("host:port:address[,address]") every connection must use. It
     * throws to refuse the endpoint.
     *
     * @param  (\Closure(string): array<string>)|null  $resolver
     */
    public function setResolver(?\Closure $resolver): static
    {
        $this->resolver = $resolver;
        $this->resetHttpClient();

        return $this;
    }

    #[Override]
    protected function createHttpClient(bool $followRedirects): HttpClient
    {
        $options = [
            CURLOPT_TIMEOUT_MS => 0,
            CURLOPT_USERAGENT => php_uname('s').'-'.php_uname('r').':php-'.phpversion(),
            CURLOPT_PROTOCOLS_STR => self::PROTOCOLS,
            CURLOPT_REDIR_PROTOCOLS_STR => self::PROTOCOLS,
        ];

        if ($this->resolver !== null) {
            $options[CURLOPT_RESOLVE] = ($this->resolver)($this->endpoint);
        }

        $adapter = (new Curl(options: $options))->withConnectionReuse();

        $client = new HttpClient($followRedirects ? new RedirectRefusal($adapter) : $adapter);

        if ($this->selfSigned) {
            $client = $client->withSslVerification(false);
        }

        if ($this->timeout !== null) {
            $client = $client->withTimeout($this->timeout);
        }

        if ($this->connectTimeout !== null) {
            $client = $client->withConnectTimeout($this->connectTimeout);
        }

        return $client;
    }
}
