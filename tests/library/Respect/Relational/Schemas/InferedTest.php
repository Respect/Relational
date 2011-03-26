<?php

namespace Respect\Relational\Schemas;

class InferedTest extends \PHPUnit_Framework_TestCase
{

    protected $object;

    protected function setUp()
    {
        $this->object = new Infered();
    }

    protected function tearDown()
    {
        unset($this->object);
    }

    public function testTwoEntities()
    {
        $sql = (string) current($this->object->findRelationships('bug', 'developer'))->asInnerJoin(true);
        $this->assertEquals($sql, 'FROM bug INNER JOIN developer ON bug.developer_id = developer.id');
    }

    public function testTwoEntitiesAliasBoth()
    {
        $sql = (string) current($this->object->findRelationships('bug', 'developer'))->asInnerJoin(true, 'b', 'd');
        $this->assertEquals($sql, 'FROM bug AS b INNER JOIN developer AS d ON b.developer_id = d.id');
    }

    public function testTwoEntitiesAliasFrom()
    {
        $sql = (string) current($this->object->findRelationships('bug', 'developer'))->asInnerJoin(true, 'b');
        $this->assertEquals($sql, 'FROM bug AS b INNER JOIN developer ON b.developer_id = developer.id');
    }

    public function testTwoEntitiesAliasTo()
    {
        $sql = (string) current($this->object->findRelationships('bug', 'developer'))->asInnerJoin(true, null, 'd');
        $this->assertEquals($sql, 'FROM bug INNER JOIN developer AS d ON bug.developer_id = d.id');
    }

    public function testEntityColumn()
    {
        $sql = (string) current($this->object->findRelationships('bug', 'developer_id'))->asInnerJoin(true);
        $this->assertEquals($sql, 'FROM bug INNER JOIN developer ON bug.developer_id = developer.id');
    }

    public function testColumnColumn()
    {
        $sql = (string) current($this->object->findRelationships('bug_id', 'developer_id'))->asInnerJoin(true);
        $this->assertEquals($sql, 'FROM bug INNER JOIN developer ON bug.developer_id = developer.id');
    }

    public function testHydrateSimple()
    {
        $entitiesNames = array('post');
        $row = array(
            'id' => 1,
            'title' => 'foo_value',
            'post_text' => 'foo_value2'
        );
        $freak = $this->object->hydrate($entitiesNames, $row);
        $this->assertEquals(1, $freak->id);
        $this->assertEquals('foo_value', $freak->title);
        $this->assertEquals('foo_value2', $freak->post_text);
    }

    public function testHydrateTwoEntities()
    {
        $entitiesNames = array('comment', 'post');
        $row = array(
            'id' => array(11, 1),
            'post_id' => 1,
            'title' => 'foo_value',
            'post_text' => 'foo_value2',
            'author_id' => 22,
            'comment_text' => 'bar_value2'
        );
        $freaks = $this->object->hydrate($entitiesNames, $row);
        $this->assertEquals(11, $freaks[0]->id);
        $this->assertEquals('foo_value', $freaks[0]->title);
        $this->assertEquals('foo_value2', $freaks[0]->post_text);
        $this->assertEquals(1, $freaks[0]->post_id->id);
        $this->assertEquals(22, $freaks[0]->post_id->author_id);
        $this->assertEquals('bar_value2', $freaks[0]->post_id->comment_text);
    }

    public function testHydrateRepeatedInstances()
    {
        $entitiesNames = array('user', 'user_list', 'list', 'user');
        $row = array(
            'id' => array(1, 2, 3, 4),
            'user_id' => array(1, 4),
            'list_id' => 3,
            'screen_name' => array('foo', 'bar'),
            'list_name' => 'Test'
        );
        $freaks = $this->object->hydrate($entitiesNames, $row);
        $this->assertEquals(1, $freaks[0]->id);
        $this->assertEquals(2, $freaks[1]->id);
        $this->assertEquals(3, $freaks[1]->list_id->id);
        $this->assertEquals(4, $freaks[1]->list_id->user_id->id);
        $this->assertEquals('foo', $freaks[0]->screen_name);
        $this->assertEquals('bar', $freaks[1]->list_id->user_id->screen_name);
        $this->assertEquals('Test', $freaks[1]->list_id->list_name);
    }

    public function testHydrateRepeatedInstancesTwice()
    {
        $entitiesNames = array('list', 'user', 'user_list', 'list', 'user');
        $row = array(
            'id' => array(1, 2, 3, 4, 5),
            'user_id' => array(2, 2, 5),
            'list_id' => 4,
            'screen_name' => array('foo', 'bar'),
            'list_name' => array('Haha', 'Test')
        );
        $freaks = $this->object->hydrate($entitiesNames, $row);
        $this->assertEquals(1, $freaks[0]->id);
        $this->assertEquals(2, $freaks[0]->user_id->id);
        $this->assertEquals(3, $freaks[2]->id);
        $this->assertEquals(4, $freaks[2]->list_id->id);
        $this->assertEquals(5, $freaks[2]->list_id->user_id->id);
        $this->assertEquals('foo', $freaks[1]->screen_name);
        $this->assertEquals('bar', $freaks[2]->list_id->user_id->screen_name);
        $this->assertEquals('Test', $freaks[2]->list_id->list_name);
    }

    public function testHydrateRepeatedInstancesTwiceConflictedIds()
    {
        $entitiesNames = array('list', 'user', 'user_list', 'list', 'user');
        $row = array(
            'id' => array(1, 1, 1, 1, 2),
            'user_id' => array(1, 1, 2),
            'list_id' => 1,
            'screen_name' => array('foo', 'bar'),
            'list_name' => array('Haha', 'Test')
        );
        $freaks = $this->object->hydrate($entitiesNames, $row);
        $this->assertEquals(1, $freaks[0]->id);
        $this->assertEquals(1, $freaks[0]->user_id->id);
        $this->assertEquals(1, $freaks[2]->id);
        $this->assertEquals(1, $freaks[2]->list_id->id);
        $this->assertEquals(2, $freaks[2]->list_id->user_id->id);
        $this->assertEquals('foo', $freaks[1]->screen_name);
        $this->assertEquals('bar', $freaks[2]->list_id->user_id->screen_name);
        $this->assertEquals('Test', $freaks[2]->list_id->list_name);
    }

}
