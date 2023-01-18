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
use Utopia\Transfer\Sources\Supabase;
use Utopia\Transfer\Resources\Project;
use Utopia\Transfer\Resources\User;

class SupabaseTest extends TestCase
{
    /**
     * @var Supabase
     */
    public $supabase;

    public function setUp(): void
    {
        $this->supabase = new Supabase(
            getEnv("SUPABASE_TEST_HOST") ?? '',
            getEnv("SUPABASE_TEST_DATABASE") ?? '',
            getEnv("SUPABASE_TEST_USERNAME") ?? '',
            getEnv("SUPABASE_TEST_PASSWORD") ?? '',
        );
    }

    public function testGetUsers(): array
    {
        $result = [];

        $this->supabase->exportUsers(500, function (array $users) use (&$result) {
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
                $userFound = $this->supabase->pdo->query('SELECT * FROM auth.users WHERE id = \'' . $user->getId() . '\'')->fetch();
                $assertedUsers++;
    
                $this->assertNotEmpty($userFound);
                $this->assertEquals($user->getId(), $userFound['id']);
                $this->assertEquals($user->getEmail(), $userFound['email']);
                $this->assertEquals($user->getPhone(), $userFound['phone']);
                $this->assertEquals($user->getPasswordHash()->getHash(), $userFound['encrypted_password']);
                $this->assertEquals($user->getEmailVerified(), !empty($userFound['email_confirmed_at']));
                $this->assertEquals($user->getPhoneVerified(), !empty($userFound['phone_confirmed_at']));
            } catch (\Exception $e) {
                throw $e;
            }
        }

        $this->assertGreaterThan(1, $assertedUsers);
    }
}