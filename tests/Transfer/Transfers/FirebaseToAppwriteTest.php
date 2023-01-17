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
use Utopia\Transfer\Destinations\Appwrite;
use Utopia\Transfer\Log;
use Utopia\Transfer\Sources\Firebase;
use Utopia\Transfer\Transfer;
use Utopia\Transfer\Resources\User;
use Appwrite\Client as AppwriteClient;
use Appwrite\Services\Users;

class FirebaseToAppwriteTest extends TestCase
{
    /**
     * @var Firebase
     */
    public $firebase;

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
        $serviceAccount = json_decode(getenv("FIREBASE_TEST_ACCOUNT"), true);

        $this->firebase = new Firebase(
            $serviceAccount,
            Firebase::AUTH_SERVICEACCOUNT
        );

        $this->firebase->setProject($serviceAccount['project_id']);

        $this->appwrite = new Appwrite(
            getenv("APPWRITE_TEST_PROJECT"),
            getenv("APPWRITE_TEST_ENDPOINT"),
            getenv("APPWRITE_TEST_KEY")
        );

        $this->appwriteClient = new AppwriteClient();
        $this->appwriteClient
            ->setEndpoint(getenv("APPWRITE_TEST_ENDPOINT"))
            ->setProject(getenv("APPWRITE_TEST_PROJECT"))
            ->setKey(getenv("APPWRITE_TEST_KEY"));

        $this->transfer = new Transfer($this->firebase, $this->appwrite);
    }

    public function testTransferUsers(): void
    {
        $this->transfer->run([Transfer::RESOURCE_USERS], function () {
        });

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

        $this->firebase->exportUsers(500, function (array $users) use ($userClient, &$assertedUsers) {
            foreach ($users as $user) {
                /** @var User $user */
                if (in_array(User::TYPE_ANONYMOUS, $user->getTypes())) {
                    continue;
                }

                try {
                    $userFound = $userClient->get($user->getId());
                } catch (\Exception $e) {
                    throw $e;
                }
                $this->assertNotEmpty($userFound);

                $this->assertEquals($user->getEmail(), $userFound['email']);
                $this->assertEquals($user->getPhone(), $userFound['phone']);
                $assertedUsers = true;
            }
        });

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
