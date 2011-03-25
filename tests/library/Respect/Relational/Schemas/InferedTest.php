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

    public function testHydrate()
    {
        //A PDO::FETCH_NAMED should return somethink like this
        $row = array(
            'id' => array($cId = 11, $pId = 1),
            'text' => array($cText = 'Comment Text', $pText = 'Post Text'),
            'post_id' => $pId,
            'title' => $pTitle = 'Post Title'
        );
        $freak = $this->object->hydrate(array('comment', 'post'), $row);
        $this->assertEquals($cId, $freak->id);
        $this->assertEquals($pId, $freak->post_id->id);
        $this->assertEquals($cText, $freak->text);
        $this->assertEquals($pText, $freak->post_id->text);
        $this->assertEquals($pTitle, $freak->title);
    }

    public function testHydrateDuplicateEntity()
    {
        //A PDO::FETCH_NAMED should return somethink like this
        $row = array(
            'id' => array(1, 11, 2),
            'screen_name' => array('foo', 'bar'),
            'user_id' => array(1, 2),
            'follower_id' => array(2, 1)
        );
        $freak = $this->object->hydrate(array('user', 'follower', 'user'), $row);
        print_r($freak);
    }

}
