<?php

/**
 * Utopia PHP Framework
 *
 * @package Transfer
 * @subpackage Tests
 *
 * @link https://github.com/utopia-php/transfer
 * @author Bradley Schofield <bradley@appwrite.io>
 * @version 1.0 RC1
 * @license The MIT License (MIT) <http://www.opensource.org/licenses/mit-license.php>
 */

namespace Utopia\Tests;

use PHPUnit\Framework\TestCase;
use Utopia\App;
use Utopia\Transfer\Sources\NHost;
use Utopia\Transfer\Resources\Project;
use Utopia\Transfer\Resources\User;

class NHostTest extends TestCase
{
    /**
     * @var NHost
     */
    public $nhost;

    public function setUp(): void
    {
        $this->nhost = new NHost(
            getEnv("NHOST_TEST_HOST") ?? '',
            getEnv("NHOST_TEST_DATABASE") ?? '',
            getEnv("NHOST_TEST_USERNAME") ?? '',
            getEnv("NHOST_TEST_PASSWORD") ?? '',
        );
    }

    public function testGetUsers(): array
    {
        $result = [];

        $this->nhost->exportUsers(500, function (array $users) use (&$result) {
            $result = array_merge($result, $users);
        });

        foreach ($result as $user) {
            /** @var User $user */
            $this->assertIsObject($user);
            $this->assertNotEmpty($user->getPasswordHash());
            $this->assertNotEmpty($user->getPasswordHash()->getHash());
        }

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        return $result;
    }

    /**
     * @depends testGetUsers
     */
    public function testVerifyUsers(array $users): void
    {
        $assertedUsers = 0;

        foreach ($users as $user) {
            /** @var User $user */
            if (in_array(User::TYPE_ANONYMOUS, $user->getTypes())) {
                continue;
            }

            try {
                $userFound = $this->nhost->pdo->query('SELECT * FROM auth.users WHERE id = \'' . $user->getId() . '\'')->fetch();
                $assertedUsers++;
    
                $this->assertNotEmpty($userFound);
                $this->assertEquals($user->getId(), $userFound['id']);
                $this->assertEquals($user->getEmail(), $userFound['email']);
                $this->assertEquals($user->getPhone(), $userFound['phone_number']);
                $this->assertEquals($user->getPasswordHash()->getHash(), $userFound['password_hash']);
                $this->assertEquals($user->getEmailVerified(), $userFound['email_verified']);
                $this->assertEquals($user->getPhoneVerified(), $userFound['phone_number_verified']);
            } catch (\Exception $e) {
                throw $e;
            }
        }

        $this->assertGreaterThan(1, $assertedUsers);
    }
}