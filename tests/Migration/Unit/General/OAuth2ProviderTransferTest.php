<?php

namespace Utopia\Tests\Unit\General;

use PHPUnit\Framework\TestCase;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Auth\OAuth2\Apple;
use Utopia\Migration\Resources\Auth\OAuth2\Github;
use Utopia\Migration\Resources\Auth\OAuth2\Google;
use Utopia\Migration\Resources\Auth\OAuth2\Microsoft;
use Utopia\Migration\Transfer;
use Utopia\Tests\Unit\Adapters\MockDestination;
use Utopia\Tests\Unit\Adapters\MockSource;

/**
 * Transfer-level coverage for OAuth2 providers. Every provider shares the one
 * TYPE_OAUTH2_PROVIDER type under GROUP_AUTH, so they must all route through a
 * single transfer call, and each concrete subclass must survive with its
 * provider-specific fields intact (Google's prompt, Apple's serviceId/keyId/
 * teamId, Microsoft's tenant, and a plain clientId provider).
 */
class OAuth2ProviderTransferTest extends TestCase
{
    protected MockSource $source;

    protected MockDestination $destination;

    protected Transfer $transfer;

    public function setUp(): void
    {
        $this->source = new MockSource();
        $this->destination = new MockDestination();
        $this->transfer = new Transfer($this->source, $this->destination);
    }

    public function testProvidersTransferUnderAuthGroupWithFieldsIntact(): void
    {
        $this->source->pushMockResource(Google::fromArray([
            'id' => 'project-google',
            'enabled' => true,
            'clientId' => 'g-client',
            'prompt' => ['consent', 'select_account'],
        ]));
        $this->source->pushMockResource(Apple::fromArray([
            'id' => 'project-apple',
            'enabled' => false,
            'serviceId' => 'svc',
            'keyId' => 'KEY',
            'teamId' => 'TEAM',
        ]));
        $this->source->pushMockResource(Microsoft::fromArray([
            'id' => 'project-microsoft',
            'enabled' => true,
            'clientId' => 'm-client',
            'tenant' => 'contoso',
        ]));
        $this->source->pushMockResource(Github::fromArray([
            'id' => 'project-github',
            'enabled' => true,
            'clientId' => 'gh-client',
        ]));

        $this->transfer->run([Resource::TYPE_OAUTH2_PROVIDER], function () {
        });

        // All four land under the single shared type in the auth group.
        $ids = $this->destination->getResourceTypeData(Transfer::GROUP_AUTH, Resource::TYPE_OAUTH2_PROVIDER);
        $this->assertCount(4, $ids);

        /** @var Google $google */
        $google = $this->destination->getResourceById(Transfer::GROUP_AUTH, Resource::TYPE_OAUTH2_PROVIDER, 'project-google');
        $this->assertInstanceOf(Google::class, $google);
        $this->assertSame('g-client', $google->getClientId());
        $this->assertSame(['consent', 'select_account'], $google->getPrompt());
        $this->assertTrue($google->getEnabled());

        /** @var Apple $apple */
        $apple = $this->destination->getResourceById(Transfer::GROUP_AUTH, Resource::TYPE_OAUTH2_PROVIDER, 'project-apple');
        $this->assertInstanceOf(Apple::class, $apple);
        $this->assertSame('svc', $apple->getServiceId());
        $this->assertSame('KEY', $apple->getKeyId());
        $this->assertSame('TEAM', $apple->getTeamId());
        $this->assertFalse($apple->getEnabled());

        /** @var Microsoft $microsoft */
        $microsoft = $this->destination->getResourceById(Transfer::GROUP_AUTH, Resource::TYPE_OAUTH2_PROVIDER, 'project-microsoft');
        $this->assertInstanceOf(Microsoft::class, $microsoft);
        $this->assertSame('m-client', $microsoft->getClientId());
        $this->assertSame('contoso', $microsoft->getTenant());

        /** @var Github $github */
        $github = $this->destination->getResourceById(Transfer::GROUP_AUTH, Resource::TYPE_OAUTH2_PROVIDER, 'project-github');
        $this->assertInstanceOf(Github::class, $github);
        $this->assertSame('gh-client', $github->getClientId());
        $this->assertSame(Transfer::GROUP_AUTH, $github->getGroup());
    }
}
