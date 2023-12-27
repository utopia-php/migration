<?php

namespace Utopia\Tests\E2E\Sources\Live\Appwrite;

use Utopia\App;
use Utopia\Migration\Resource;
use Utopia\Migration\Sources\Appwrite;
use Utopia\Tests\E2E\Sources\Base;

class AuthTest extends Base
{
    public function testExportUsers()
    {
        $users = [];

        $callback = function ($data) use (&$users) {
            $users[] = $data;
            var_dump($data);
        };

        $this->source->run([
            Resource::TYPE_USER,
        ], $callback);

        $this->assertEmpty($this->source->getErrors());
    }

    public function testExportTeams()
    {
        $teams = [];

        $callback = function ($data) use (&$teams) {
            $teams[] = $data;
            var_dump($data);
        };

        $this->source->run([
            Resource::TYPE_TEAM,
        ], $callback);
    }

    public function testExportMemberships()
    {
        $memberships = [];

        $callback = function ($data) use (&$memberships) {
            $memberships[] = $data;
            var_dump($data);
        };

        $this->source->run([
            Resource::TYPE_MEMBERSHIP,
        ], $callback);
    }

    public function testFullTransfer()
    {
        $callback = function ($data) {};

        $this->source->run([
            Resource::TYPE_USER,
            Resource::TYPE_TEAM,
            Resource::TYPE_MEMBERSHIP,
        ], $callback);
    }

}
