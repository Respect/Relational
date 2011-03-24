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

}
