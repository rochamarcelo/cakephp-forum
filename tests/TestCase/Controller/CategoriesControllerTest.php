<?php
namespace CakeDC\Forum\Test\TestCase\Controller;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestCase;

/**
 * CakeDC\Forum\Controller\CategoriesController Test Case
 */
class CategoriesControllerTest extends IntegrationTestCase
{

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'plugin.CakeDC/Forum.categories',
        'plugin.CakeDC/Forum.posts',
        'plugin.CakeDC/Forum.moderators',
        'plugin.CakeDC/Forum.users',
    ];

    public function setUp()
    {
        parent::setUp();

        $this->session([
            'Auth' => [
                'User' => [
                    'id' => 1,
                    'username' => 'testing',
                ]
            ]
        ]);

        $this->enableRetainFlashMessages();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
    }

    /**
     * Test index method
     *
     * @return void
     */
    public function testIndex()
    {
        $this->get('/forum');

        $this->assertResponseOk();
        $this->assertResponseContains('Hardware and Technology');
        $this->assertResponseContains('Latest: <');
        $this->assertResponseNotContains('Invisible Category');

        $categories = $this->viewVariable('categories')->toArray();
        $this->assertNotEmpty($categories);
        $this->assertCount(3, $categories);
        $this->assertCount(3, $categories[0]->children);
        $this->assertEquals('Hardware and Technology', $categories[0]->title);
        $this->assertCount(3, $categories[1]->children);
        $this->assertEquals('Software', $categories[1]->title);
        $this->assertCount(3, $categories[2]->children);
        $this->assertEquals('Consumer Electronics', $categories[2]->title);
    }

    /**
     * Test $forumUserIsModerator var
     *
     * @return void
     */
    public function testForumUserIsModerator()
    {
        $this->get('/forum');
        $this->assertResponseContains('>Reports<');
        $forumUserIsModerator = $this->viewVariable('forumUserIsModerator');
        $this->assertTrue($forumUserIsModerator);

        $this->session([
            'Auth' => [
                'User' => [
                    'id' => 4,
                    'username' => 'user',
                ]
            ]
        ]);
        $this->get('/forum');
        $this->assertResponseNotContains('>Reports<');
        $forumUserIsModerator = $this->viewVariable('forumUserIsModerator');
        $this->assertFalse($forumUserIsModerator);
    }
}
