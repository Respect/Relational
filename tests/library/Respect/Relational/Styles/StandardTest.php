<?php

namespace Respect\Data\Styles;

class StandardTest extends \PHPUnit\Framework\TestCase
{

    /**
     * @var Respect\Data\Styles\Standard
     */
    private $style;


    public static function tableEntityProvider()
    {
        return array(
            array('post',           'Post'),
            array('comment',        'Comment'),
            array('category',       'Category'),
            array('post_category',  'PostCategory'),
            array('post_tag',       'PostTag'),
        );
    }

    public static function manyToMantTableProvider()
    {
        return array(
            array('post',   'category', 'post_category'),
            array('user',   'group',    'user_group'),
            array('group',  'profile',  'group_profile'),
        );
    }

    public static function columnsPropertyProvider()
    {
        return array(
            array('id'),
            array('text'),
            array('name'),
            array('content'),
            array('created'),
        );
    }
    
    public static function foreignProvider()
    {
        return array(
            array('post',       'post_id'),
            array('author',     'author_id'),
            array('tag',        'tag_id'),
            array('user',       'user_id'),
        );
    }


    protected function setUp(): void
    {
        $this->style = new Standard();
    }

    protected function tearDown(): void
    {
        $this->style = null;
    }

    /**
     * @dataProvider tableEntityProvider
     */
    public function test_table_and_entities_methods($table, $entity)
    {
        $this->assertEquals($entity, $this->style->styledName($table));
        $this->assertEquals($table, $this->style->realName($entity));
        $this->assertEquals('id', $this->style->identifier($table));
    }

    /**
     * @dataProvider columnsPropertyProvider
     */
    public function test_columns_and_properties_methods($name)
    {
        $this->assertEquals($name, $this->style->styledProperty($name));
        $this->assertEquals($name, $this->style->realProperty($name));
        $this->assertFalse($this->style->isRemoteIdentifier($name));
        $this->assertNull($this->style->remoteFromIdentifier($name));
    }

    /**
     * @dataProvider manyToMantTableProvider
     */
    public function test_table_from_left_right_table($left, $right, $table)
    {
        $this->assertEquals($table, $this->style->composed($left, $right));
    }
    
    /**
     * @dataProvider foreignProvider
     */
    public function test_foreign($table, $foreign)
    {
        $this->assertTrue($this->style->isRemoteIdentifier($foreign));
        $this->assertEquals($table, $this->style->remoteFromIdentifier($foreign));
        $this->assertEquals($foreign, $this->style->remoteIdentifier($table));
    }

}

