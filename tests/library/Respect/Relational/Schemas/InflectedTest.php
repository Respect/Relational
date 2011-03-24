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

    public function testHydrate()
    {
        //A PDO::FETCH_NAMED should return somethink like this
        $row = array(
            'id' => array($cId = 11, $pId = 1),
            'text' => array($cText = 'Comment Text', $pText = 'Post Text'),
            'post_id' => $pId,
            'title' => $pTitle = 'Post Title',
            'author_name' => $pAuthor = 'Teste'
        );
        $freak = $this->object->hydrate(array('comment', 'post'), $row);
        $this->assertEquals($cId, $freak->id);
        $this->assertEquals($pId, $freak->postId->id);
        $this->assertEquals($cText, $freak->text);
        $this->assertEquals($pText, $freak->postId->text);
        $this->assertEquals($pTitle, $freak->title);
        $this->assertEquals($pAuthor, $freak->authorName);
        $this->assertObjectNotHasAttribute('author_name', $freak);
    }

}
