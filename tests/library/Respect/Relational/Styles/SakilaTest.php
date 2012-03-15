<?php

namespace Respect\Relational\Styles;

class SakilaTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var Respect\Relational\Styles\Sakila
     */
    private $style;


    public function tableEntityProvider()
    {
        return array(
            array('post',           'Post'),
            array('comment',        'Comment'),
            array('category',       'Category'),
            array('post_category',  'PostCategory'),
            array('post_tag',       'PostTag'),
        );
    }

    public function manyToMantTableProvider()
    {
        return array(
            array('post',   'category', 'post_category'),
            array('user',   'group',    'user_group'),
            array('group',  'profile',  'group_profile'),
        );
    }

    public function columnsPropertyProvider()
    {
        return array(
            array('id'),
            array('post_id'),
            array('creator_id'),
            array('text'),
            array('created'),
        );
    }
    
    public function keyProvider()
    {
        return array(
            array('post',       'post_id'),
            array('author',     'author_id'),
            array('tag',        'tag_id'),
            array('user',       'user_id'),
        );
    }


    public function setUp()
    {
        $this->style = new Sakila();
    }

    public function tearDown()
    {
        $this->style = null;
    }

    /**
     * @dataProvider tableEntityProvider
     */
    public function test_table_and_entities_methods($table, $entity)
    {
        $this->assertEquals($entity, $this->style->tableToEntity($table));
        $this->assertEquals($table, $this->style->entityToTable($entity));
    }

    /**
     * @dataProvider columnsPropertyProvider
     */
    public function test_columns_and_properties_methods($column)
    {
        $this->assertEquals($column, $this->style->columnToProperty($column));
        $this->assertEquals($column, $this->style->propertyToColumn($column));
    }


    /**
     * @dataProvider manyToMantTableProvider
     */
    public function test_table_from_right_left_table($right, $left, $table)
    {
        $this->assertEquals($table, $this->style->manyFromRightLeft($right, $left));
    }


    /**
     * @dataProvider manyToMantTableProvider
     */
    public function test_table_from_left_right_table($left, $right, $table)
    {
        $this->assertEquals($table, $this->style->manyFromLeftRight($left, $right));
    }
    
    /**
     * @dataProvider keyProvider
     */
    public function test_foreign($table, $key)
    {
        $this->assertEquals($key, $this->style->primaryFromTable($table));
        $this->assertEquals($key, $this->style->foreignFromTable($table));
    }

}

