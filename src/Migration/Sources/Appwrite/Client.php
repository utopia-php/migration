<?php

namespace Utopia\Migration\Sources\Appwrite;

use Appwrite\Client as Base;
use Override;
use Utopia\Client\Client as HttpClient;
use Utopia\Client\Destinations;

/**
 * The Appwrite SDK client, confined to the destinations its source may connect to.
 */
class Client extends Base
{
    private ?Destinations $destinations = null;

    public function setDestinations(Destinations $destinations): static
    {
        $this->destinations = $destinations;
        $this->resetHttpClient();

        return $this;
    }

    #[Override]
    protected function createHttpClient(bool $followRedirects): HttpClient
    {
        $client = parent::createHttpClient($followRedirects);

        if ($this->destinations === null) {
            return $client;
        }

        return $client->withDestinations($this->destinations);
    }
}
