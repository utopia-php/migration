<?php

namespace Utopia\Tests\Unit\General;

use PHPUnit\Framework\TestCase;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Auth\Membership;
use Utopia\Migration\Resources\Auth\Team;
use Utopia\Migration\Resources\Auth\User;
use Utopia\Migration\Transfer;
use Utopia\Tests\Unit\Adapters\MockDestination;
use Utopia\Tests\Unit\Adapters\MockSource;

/**
 * A resource requested without its prerequisites cannot be transferred: its
 * exporter walks a cache the missing prerequisite never filled, or is only
 * reached by the prerequisite's own exporter. Nothing throws, so without this
 * check the transfer finishes reporting success having moved nothing.
 */
class ResourceDependenciesTest extends TestCase
{
    protected Transfer $transfer;

    protected MockSource $source;

    protected MockDestination $destination;

    public function setup(): void
    {
        $this->source = new MockSource();
        $this->destination = new MockDestination();

        $this->transfer = new Transfer(
            $this->source,
            $this->destination
        );

        $this->source->setResourceDependencies([
            Resource::TYPE_MEMBERSHIP => [Resource::TYPE_USER, Resource::TYPE_TEAM],
        ]);

        $team = new Team('team', 'Team');
        $user = new User('user', 'user@example.com');

        $this->source->pushMockResource($team);
        $this->source->pushMockResource($user);
        $this->source->pushMockResource(new Membership('membership', $team, $user));
    }

    public function testMissingPrerequisitesAreReported(): void
    {
        $this->transfer->run([Resource::TYPE_MEMBERSHIP], function () {
        });

        $errors = $this->source->getErrors();

        $this->assertCount(1, $errors);
        $this->assertSame(Resource::TYPE_MEMBERSHIP, $errors[0]->getResourceName());
        $this->assertSame(
            'Cannot transfer membership without user and team.',
            $errors[0]->getMessage()
        );
    }

    public function testOnlyTheAbsentPrerequisitesAreNamed(): void
    {
        $this->transfer->run(
            [Resource::TYPE_USER, Resource::TYPE_MEMBERSHIP],
            function () {
            }
        );

        $errors = $this->source->getErrors();

        $this->assertCount(1, $errors);
        $this->assertSame(
            'Cannot transfer membership without team.',
            $errors[0]->getMessage()
        );
    }

    public function testASatisfiedRequestIsNotReported(): void
    {
        $this->transfer->run(
            [Resource::TYPE_USER, Resource::TYPE_TEAM, Resource::TYPE_MEMBERSHIP],
            function () {
            }
        );

        $this->assertEmpty($this->source->getErrors());
    }

    public function testAResourceWithNoPrerequisitesIsNotReported(): void
    {
        $this->transfer->run([Resource::TYPE_USER], function () {
        });

        $this->assertEmpty($this->source->getErrors());
    }
}
