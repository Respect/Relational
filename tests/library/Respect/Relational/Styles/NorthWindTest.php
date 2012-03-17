<?php

namespace Respect\Relational\Styles;

class NorthWindTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var Respect\Relational\Styles\NorthWind
     */
    private $style;


    public function tableEntityProvider()
    {
        return array(
            array('Posts',              'Posts'),
            array('Comments',           'Comments'),
            array('Categories',         'Categories'),
            array('PostCategories',     'PostCategories'),
            array('PostTags',           'PostTags'),
        );
    }

    public function manyToMantTableProvider()
    {
        return array(
            array('Posts',  'Categories',   'PostCategories'),
            array('Users',  'Groups',       'UserGroups'),
            array('Groups', 'Profiles',     'GroupProfiles'),
        );
    }

    public function columnsPropertyProvider()
    {
        return array(
            array('GroupID'),
            array('PostID'),
            array('CreatorID'),
            array('Text'),
            array('Created'),
        );
    }
    
    public function keyProvider()
    {
        return array(
            array('Posts',      'PostID'),
            array('Authors',    'AuthorID'),
            array('Tags',       'TagID'),
            array('Users',      'UserID'),
        );
    }


    public function setUp()
    {
        $this->style = new NorthWind();
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
    public function test_table_from_left_right_table($left, $right, $table)
    {
        $this->assertEquals($table, $this->style->manyFromLeftRight($left, $right));
    }
    
    /**
     * @dataProvider keyProvider
     */
    public function test_keys($table, $foreign)
    {
        $this->assertEquals($foreign, $this->style->primaryFromTable($table));
        $this->assertEquals($foreign, $this->style->foreignFromTable($table));
    }

}

