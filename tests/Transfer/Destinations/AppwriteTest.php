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
use Utopia\Transfer\Resources\Hash;
use Utopia\Transfer\Resources\User;
use Appwrite\Client;
use Appwrite\Services\Users;

class AppwriteTest extends TestCase
{
    /**
     * @var Appwrite
     */
    public $appwrite;

    /**
     * @var Client
     */
    public $client;

    public function setUp(): void
    {
        $this->appwrite = new Appwrite(
            getenv("APPWRITE_TEST_PROJECT"),
            getenv("APPWRITE_TEST_ENDPOINT"),
            getenv("APPWRITE_TEST_KEY")
        );

        $this->client = new Client();
        $this->client
            ->setEndpoint(getenv("APPWRITE_TEST_ENDPOINT"))
            ->setProject(getenv("APPWRITE_TEST_PROJECT"))
            ->setKey(getenv("APPWRITE_TEST_KEY"));
    }

    public function testGetSupportedResources(): void
    {
        $this->assertIsArray($this->appwrite->getSupportedResources());
        $this->assertNotEmpty($this->appwrite->getSupportedResources());
    }

    public function testImportUserPassword(): void
    {
        $users = new Users($this->client);

        /**
         * Hash: SHA256,
         * Password: 'password'
         */
        $user = new User(
            '123456789',
            'test@user.com',
            "Walter O'brien",
            new Hash('5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8'),
            '',
            [User::TYPE_EMAIL]
        );

        $this->appwrite->importPasswordUser($user);

        // Check User Exists in Appwrite
        $response = $users->get($user->getId());
        $this->assertEquals($user->getId(), $response['$id']);
        $this->assertEquals($user->getEmail(), $response['email']);
        $this->assertEquals($user->getPasswordHash()->getHash(), $response['password']);

        // Cleanup
        $users->delete($user->getId());
    }
}
