<?php

namespace Respect\Relational\Styles\NorthWind;

use PDO,
    Respect\Relational\Db,
    Respect\Relational\Sql,
    Respect\Relational\Styles\NorthWind,
    Respect\Relational\Mapper;

class NorthWindTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var Respect\Relational\Styles\NorthWind
     */
    private $style;

    /**
     * @var Respect\Relational\Mapper
     */
    private $mapper;
    
    /**
     * @var PDO
     */
    private $conn;

    public function setUp()
    {

        $conn = new PDO('sqlite::memory:');
        $db = new Db($conn);
        $conn->exec(
            (string) Sql::createTable(
                'Posts',
                array(
                    'PostID INTEGER PRIMARY KEY',
                    'Title VARCHAR(255)',
                    'Text TEXT',
                    'AuthorID INTEGER',
                )
            )
        );
        $conn->exec(
            (string) Sql::createTable(
                'Authors',
                array(
                    'AuthorID INTEGER PRIMARY KEY',
                    'Name VARCHAR(255)'
                )
            )
        );
        $conn->exec(
            (string) Sql::createTable(
                'Comments',
                array(
                    'CommentID INTEGER PRIMARY KEY',
                    'PostID INTEGER',
                    'Text TEXT',
                )
            )
        );

        $conn->exec(
            (string) Sql::createTable(
                'Categories',
                array(
                    'CategoryID INTEGER PRIMARY KEY',
                    'Name VARCHAR(255)',
                    'Description TEXT'
                )
            )
        );
        $conn->exec(
            (string) Sql::createTable(
                'PostCategories',
                array(
                    'PostCategoryID INTEGER PRIMARY KEY',
                    'PostID INTEGER',
                    'CategoryID INTEGER'
                )
            )
        );
        $this->posts = array(
            (object) array(
                'PostID' => 5,
                'Title' => 'Post Title',
                'Text' => 'Post Text',
                'AuthorID' => 1
            )
        );
        $this->authors = array(
            (object) array(
                'AuthorID' => 1,
                'Name' => 'Author 1'
            )
        );
        $this->comments = array(
            (object) array(
                'CommentID' => 7,
                'PostID' => 5,
                'Text' => 'Comment Text'
            ),
            (object) array(
                'CommentID' => 8,
                'PostID' => 4,
                'Text' => 'Comment Text 2'
            )
        );
        $this->categories = array(
            (object) array(
                'CategoryID' => 2,
                'Name' => 'Sample Category',
                'Description' => 'Category description'
            ),
            (object) array(
                'CategoryID' => 3,
                'Name' => 'NONON',
                'CategoryID' => null
            )
        );
        $this->postsCategories = array(
            (object) array(
                'PostCategoryID' => 66,
                'PostID' => 5,
                'CategoryID' => 2
            )
        );

        foreach ($this->authors as $author)
            $db->insertInto('Authors', (array) $author)->values((array) $author)->exec();

        foreach ($this->posts as $post)
            $db->insertInto('Posts', (array) $post)->values((array) $post)->exec();

        foreach ($this->comments as $comment)
            $db->insertInto('Comments', (array) $comment)->values((array) $comment)->exec();

        foreach ($this->categories as $category)
            $db->insertInto('Categories', (array) $category)->values((array) $category)->exec();

        foreach ($this->postsCategories as $postCategory)
            $db->insertInto('PostCategories', (array) $postCategory)->values((array) $postCategory)->exec();

        $this->conn     = $conn;
        $this->style    = new NorthWind();
        $this->mapper   = new Mapper($conn);
        $this->mapper->setStyle($this->style);
        $this->mapper->entityNamespace = __NAMESPACE__ . '\\';
    }

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
            array('Text'),
            array('Name'),
            array('Content'),
            array('Created'),
            array('Udated'),
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

    /**
     * @dataProvider tableEntityProvider
     */
    public function test_table_and_entities_methods($table, $entity)
    {
        $this->assertEquals($entity, $this->style->styledName($table));
        $this->assertEquals($table, $this->style->realName($entity));
    }

    /**
     * @dataProvider columnsPropertyProvider
     */
    public function test_columns_and_properties_methods($column)
    {
        $this->assertEquals($column, $this->style->styledProperty($column));
        $this->assertEquals($column, $this->style->realProperty($column));
        $this->assertFalse($this->style->isRemoteIdentifier($column));
        $this->assertNull($this->style->remoteFromIdentifier($column));
    }

    /**
     * @dataProvider manyToMantTableProvider
     */
    public function test_table_from_left_right_table($left, $right, $table)
    {
        $this->assertEquals($table, $this->style->composed($left, $right));
    }
    
    /**
     * @dataProvider keyProvider
     */
    public function test_keys($table, $foreign)
    {
        $this->assertTrue($this->style->isRemoteIdentifier($foreign));
        $this->assertEquals($table, $this->style->remoteFromIdentifier($foreign));
        $this->assertEquals($foreign, $this->style->identifier($table));
        $this->assertEquals($foreign, $this->style->remoteIdentifier($table));
    }

    public function test_fetching_entity_typed()
    {
        $mapper = $this->mapper;
        $comment = $mapper->Comments[8]->fetch();
        $this->assertInstanceOf(__NAMESPACE__ . '\Comments', $comment);
    }

    public function test_fetching_all_entity_typed()
    {
        $mapper = $this->mapper;
        $comment = $mapper->Comments->fetchAll();
        $this->assertInstanceOf(__NAMESPACE__ . '\Comments', $comment[1]);
        
        $categories = $mapper->PostCategories->Categories->fetch();
        $this->assertInstanceOf(__NAMESPACE__ . '\PostCategories', $categories);
        $this->assertInstanceOf(__NAMESPACE__ . '\Categories', $categories->CategoryID);
    }

    public function test_fetching_all_entity_typed_nested()
    {
        $mapper = $this->mapper;
        $comment = $mapper->Comments->Posts->Authors->fetchAll();
        $this->assertInstanceOf(__NAMESPACE__ . '\Comments', $comment[0]);
        $this->assertInstanceOf(__NAMESPACE__ . '\Posts',    $comment[0]->PostID);
        $this->assertInstanceOf(__NAMESPACE__ . '\Authors',  $comment[0]->PostID->AuthorID);
    }

    public function test_persisting_entity_typed()
    {
        $mapper = $this->mapper;
        $comment = $mapper->Comments[8]->fetch();
        $this->assertInstanceOf(__NAMESPACE__ . '\Comments', $comment);
        $comment->Text = 'HeyHey';
        $mapper->Comments->persist($comment);
        $mapper->flush();
        $result = $this->conn->query('select Text from Comments where CommentID=8')->fetchColumn(0);
        $this->assertEquals('HeyHey', $result);
    }

    public function test_persisting_new_entity_typed()
    {
        $mapper = $this->mapper;
        $comment = new Comments();
        $comment->Text = 'HeyHey';
        $mapper->Comments->persist($comment);
        $mapper->flush();
        $result = $this->conn->query('select Text from Comments where CommentID=9')->fetchColumn(0);
        $this->assertEquals('HeyHey', $result);
    }

}



class Posts
{
    public $PostID, $Title, $Text, $AuthorID;
}

class Authors
{
    public $AuthorID, $Name;
}

class Comments
{
    public $CommentID, $PostID, $Text;
}

class Categories
{
    public $CategoryID, $Name, $Description;
}

class PostCategories
{
    public $PostCategoryID, $PostID, $CategoryID;
}

