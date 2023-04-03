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
use Utopia\App;
use Utopia\Transfer\Sources\Appwrite;
use Utopia\Transfer\Resources\Project;

class AppwriteSourceTest extends TestCase
{
    /**
     * @var Appwrite
     */
    public $appwrite;

    /**
     * @var array
     */
    public $serviceAccount;

    public function setUp(): void
    {
        $this->appwrite = new Appwrite(
            getenv('SOURCE_APPWRITE_TEST_PROJECT'),
            getenv('SOURCE_APPWRITE_TEST_ENDPOINT'),
            getenv('SOURCE_APPWRITE_TEST_KEY')
        );
    }


    public function testGetUsers(): void
    {
        $result = [];

        $this->appwrite->exportUsers(
            100,
            public function (array $users) use (&$result) {
                $result = array_merge($result, $users);
            }
        );

        foreach ($result as $user) {
            /**
 * @var User $user
*/
            $this->assertIsObject($user);
        }

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    public function testGetDatabases(): void
    {
        $result = [];

        $this->appwrite->exportDatabases(
            100,
            public function (array $databases) use (&$result) {
                $result = array_merge($result, $databases);
            }
        );

        foreach ($result as $database) {
            /**
 * @var Database $database
*/
            $this->assertIsObject($database);
            $this->assertNotEmpty($database->getCollections());
        }
    }
}
