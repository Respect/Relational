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

}
