<?php

namespace Respect\Relational\Schemas;

class InflectedTest extends \PHPUnit_Framework_TestCase
{

    protected $object;

    protected function setUp()
    {
        $this->object = new Inflected(new Infered);
    }

    protected function tearDown()
    {
        unset($this->object);
    }

    public function testTwoEntities()
    {
        $sql = (string) current($this->object->findRelationships('blogComment', 'blogPost'))->asInnerJoin(true);
        $this->assertEquals($sql, 'FROM blog_comment INNER JOIN blog_post ON blog_comment.blog_post_id = blog_post.id');
    }

    public function testHydrateRepeatedInstancesTwice()
    {
        $entitiesNames = array('list', 'user', 'list_membership', 'list', 'user');
        $row = array(
            'id' => array(1, 2, 3, 4, 5),
            'user_id' => array(2, 2, 5),
            'list_id' => 4,
            'screen_name' => array('foo', 'bar'),
            'list_name' => array('Haha', 'Test')
        );
        $freaks = $this->object->hydrate($entitiesNames, $row);
        $this->assertEquals(1, $freaks[0]->id);
        $this->assertEquals(2, $freaks[0]->userId->id);
        $this->assertEquals(3, $freaks[2]->id);
        $this->assertEquals(4, $freaks[2]->listId->id);
        $this->assertEquals(5, $freaks[2]->listId->userId->id);
        $this->assertEquals('foo', $freaks[1]->screenName);
        $this->assertEquals('bar', $freaks[2]->listId->userId->screenName);
        $this->assertEquals('Test', $freaks[2]->listId->listName);
    }

}
