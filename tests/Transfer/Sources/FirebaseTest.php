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
use Utopia\Transfer\Sources\Firebase;
use Utopia\Transfer\Resources\Project;

class FirebaseTest extends TestCase
{
    /**
     * @var Firebase
     */
    public $firebase;

    /**
     * @var array
     */
    public $serviceAccount;
    
    public function setUp(): void
    {
        $this->serviceAccount = json_decode(getEnv("FIREBASE_TEST_ACCOUNT"), true);

        $this->firebase = new Firebase(
            $this->serviceAccount,
            Firebase::AUTH_SERVICEACCOUNT
        );
    }

    public function testGetProjects(): void
    {
        $projects = $this->firebase->getProjects();

        $this->assertIsArray($projects);
        $this->assertNotEmpty($projects);
    }

    public function testSetProject(): void
    {
        $projects = $this->firebase->getProjects();

        /**
         * @var Project $testProject
         */
        $testProject = null;

        foreach($projects as $project) {
            /** @var Project $project */
            if($project->getId() == $this->serviceAccount['project_id']) {
                $testProject = $project;
                break;
            }
        }

        $this->assertIsObject($testProject);

        $this->firebase->setProject($testProject);

        $this->assertEquals($projects[0], $this->firebase->getProject());
    }


    public function testGetUsers(): void
    {
        $projects = $this->firebase->getProjects();

        $this->firebase->setProject($projects[0]);

        $result = [];

        $this->firebase->exportUsers(500, function (array $users) use (&$result) {
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
    }
}