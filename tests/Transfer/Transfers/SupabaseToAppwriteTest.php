<?php

/**
 * Utopia PHP Framework
 *
 * @package    Transfer
 * @subpackage Tests
 *
 * @link    https://github.com/utopia-php/transfer
 * @author  Bradley Schofield <bradley@appwrite.io>
 * @version 1.0 RC1
 * @license The MIT License (MIT) <http://www.opensource.org/licenses/mit-license.php>
 */

namespace Utopia\Tests;

use PHPUnit\Framework\TestCase;
use Utopia\Transfer\Destinations\Appwrite;
use Utopia\Transfer\Log;
use Utopia\Transfer\Sources\Supabase;
use Utopia\Transfer\Transfer;
use Utopia\Transfer\Resources\User;
use Appwrite\Client as AppwriteClient;
use Appwrite\Services\Users;

class SupabaseToAppwriteTest extends TestCase
{
    /**
     * @var Supabase
     */
    public $supabase;

    /**
     * @var Appwrite
     */
    public $appwrite;

    /**
     * @var AppwriteClient
     */
    public $appwriteClient;

    /**
     * @var Transfer
     */
    public $transfer;

    public function setUp(): void
    {
        $this->supabase = new Supabase(
            getEnv("SUPABASE_TEST_HOST"),
            getEnv("SUPABASE_TEST_DATABASE"),
            getEnv("SUPABASE_TEST_USERNAME"),
            getEnv("SUPABASE_TEST_PASSWORD"),
        );

        $this->appwrite = new Appwrite(
            getenv("DESTINATION_APPWRITE_TEST_PROJECT"),
            getenv("DESTINATION_APPWRITE_TEST_ENDPOINT"),
            getenv("DESTINATION_APPWRITE_TEST_KEY")
        );

        $this->appwriteClient = new AppwriteClient();
        $this->appwriteClient
            ->setEndpoint(getenv("DESTINATION_APPWRITE_TEST_ENDPOINT"))
            ->setProject(getenv("DESTINATION_APPWRITE_TEST_PROJECT"))
            ->setKey(getenv("DESTINATION_APPWRITE_TEST_KEY"));

        $this->transfer = new Transfer($this->supabase, $this->appwrite);
    }

    public function testTransferUsers(): void
    {
        $this->transfer->run(
            [Transfer::GROUP_AUTH],
            public function () {
            }
        );

        // Check for Fatal Errors in Transfer Log
        $this->assertEmpty($this->transfer->getLogs(Log::FATAL));
    }


    /**
     * @depends testTransferUsers
     */
    public function testVerifyUsers(): void
    {
        $userClient = new Users($this->appwriteClient);

        $assertedUsers = false;

        $this->supabase->exportAuth(
            500,
            public function (array $users) use ($userClient, &$assertedUsers) {
                foreach ($users as $user) {
                    /**
            * @var User $user
            */
                    if (in_array(User::TYPE_ANONYMOUS, $user->getTypes())) {
                        continue;
                    }

                    try {
                        $userFound = $userClient->get($user->getId());
                    } catch (\Exception $e) {
                        throw $e;
                    }
                    $this->assertNotEmpty($userFound);

                    $this->assertEquals($user->getId(), $userFound['$id']);
                    $this->assertEquals($user->getEmail(), $userFound['email']);
                    $this->assertEquals($user->getPhone(), $userFound['phone']);
                    $this->assertEquals($user->getPasswordHash()->getHash(), $userFound['password']);
                    $this->assertEquals($user->getEmailVerified(), $userFound['emailVerification']);
                    $this->assertEquals($user->getPhoneVerified(), $userFound['phoneVerification']);
                    $assertedUsers = true;
                }
            }
        );

        $this->assertTrue($assertedUsers);
    }

    public function testCleanupUsers(): void
    {
        $userClient = new Users($this->appwriteClient);
        $appwriteUsers = $userClient->list();

        $deletedUsers = 0;

        foreach ($appwriteUsers["users"] as $user) {
            $userClient->delete($user['$id']);
            $this->assertTrue(true);
            $deletedUsers++;
        }
    }
}
