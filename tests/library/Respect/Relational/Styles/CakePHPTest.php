<?php

namespace Respect\Relational\Styles\CakePHP;

use PDO,
    Respect\Relational\Db,
    Respect\Relational\Sql,
    Respect\Relational\Styles\CakePHP,
    Respect\Relational\Mapper;

class CakePHPTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var Respect\Relational\Styles\CakePHP
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
                'posts',
                array(
                    'id INTEGER PRIMARY KEY',
                    'title VARCHAR(255)',
                    'text TEXT',
                    'author_id INTEGER',
                )
            )
        );
        $conn->exec(
            (string) Sql::createTable(
                'authors',
                array(
                    'id INTEGER PRIMARY KEY',
                    'name VARCHAR(255)'
                )
            )
        );
        $conn->exec(
            (string) Sql::createTable(
                'comments',
                array(
                    'id INTEGER PRIMARY KEY',
                    'post_id INTEGER',
                    'text TEXT',
                )
            )
        );

        $conn->exec(
            (string) Sql::createTable(
                    //$id, $name, $category_id
                'categories',
                array(
                    'id INTEGER PRIMARY KEY',
                    'name VARCHAR(255)',
                    'category_id INTEGER'
                )
            )
        );
        $conn->exec(
            (string) Sql::createTable(
                'post_categories',
                array(
                    'id INTEGER PRIMARY KEY',
                    'post_id INTEGER',
                    'category_id INTEGER'
                )
            )
        );
        $this->posts = array(
            (object) array(
                'id' => 5,
                'title' => 'Post Title',
                'text' => 'Post Text',
                'author_id' => 1
            )
        );
        $this->authors = array(
            (object) array(
                'id' => 1,
                'name' => 'Author 1'
            )
        );
        $this->comments = array(
            (object) array(
                'id' => 7,
                'post_id' => 5,
                'text' => 'Comment Text'
            ),
            (object) array(
                'id' => 8,
                'post_id' => 4,
                'text' => 'Comment Text 2'
            )
        );
        $this->categories = array(
            (object) array(
                'id' => 2,
                'name' => 'Sample Category',
                'category_id' => null
            ),
            (object) array(
                'id' => 3,
                'name' => 'NONON',
                'category_id' => null
            )
        );
        $this->postsCategories = array(
            (object) array(
                'id' => 66,
                'post_id' => 5,
                'category_id' => 2
            )
        );

        foreach ($this->authors as $author)
            $db->insertInto('authors', (array) $author)->values((array) $author)->exec();

        foreach ($this->posts as $post)
            $db->insertInto('posts', (array) $post)->values((array) $post)->exec();

        foreach ($this->comments as $comment)
            $db->insertInto('comments', (array) $comment)->values((array) $comment)->exec();

        foreach ($this->categories as $category)
            $db->insertInto('categories', (array) $category)->values((array) $category)->exec();

        foreach ($this->postsCategories as $postCategory)
            $db->insertInto('post_categories', (array) $postCategory)->values((array) $postCategory)->exec();

        $this->conn     = $conn;
        $this->style    = new CakePHP();
        $this->mapper   = new Mapper($conn);
        $this->mapper->setStyle($this->style);
        $this->mapper->entityNamespace = __NAMESPACE__ . '\\';
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
        $this->assertEquals($entity, $this->style->styledName($table));
        $this->assertEquals($table, $this->style->realName($entity));
        $this->assertEquals('id', $this->style->identifier($table));
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
     * @dataProvider foreignProvider
     */
    public function test_foreign($table, $foreign)
    {
        $this->assertTrue($this->style->isRemoteIdentifier($foreign));
        $this->assertEquals($table, $this->style->remoteFromIdentifier($foreign));
        $this->assertEquals($foreign, $this->style->remoteIdentifier($table));
    }

    public function test_fetching_entity_typed()
    {
        $mapper = $this->mapper;
        $comment = $mapper->comments[8]->fetch();
        $this->assertInstanceOf(__NAMESPACE__ . '\Comment', $comment);
    }

    public function test_fetching_all_entity_typed()
    {
        $mapper = $this->mapper;
        $comment = $mapper->comments->fetchAll();
        $this->assertInstanceOf(__NAMESPACE__ . '\Comment', $comment[1]);
        
        $categories = $mapper->post_categories->categories->fetch();
        $this->assertInstanceOf(__NAMESPACE__ . '\PostCategory', $categories);
        $this->assertInstanceOf(__NAMESPACE__ . '\Category', $categories->category_id);
    }

    public function test_fetching_all_entity_typed_nested()
    {
        $mapper = $this->mapper;
        $comment = $mapper->comments->posts->authors->fetchAll();
        $this->assertInstanceOf(__NAMESPACE__ . '\Comment', $comment[0]);
        $this->assertInstanceOf(__NAMESPACE__ . '\Post',    $comment[0]->post_id);
        $this->assertInstanceOf(__NAMESPACE__ . '\Author',  $comment[0]->post_id->author_id);
    }

    public function test_persisting_entity_typed()
    {
        $mapper = $this->mapper;
        $comment = $mapper->comments[8]->fetch();
        $this->assertInstanceOf(__NAMESPACE__ . '\Comment', $comment);
        $comment->text = 'HeyHey';
        $mapper->comments->persist($comment);
        $mapper->flush();
        $result = $this->conn->query('select text from comments where id=8')->fetchColumn(0);
        $this->assertEquals('HeyHey', $result);
    }

    public function test_persisting_new_entity_typed()
    {
        $mapper = $this->mapper;
        $comment = new Comment();
        $comment->text = 'HeyHey';
        $mapper->comments->persist($comment);
        $mapper->flush();
        $result = $this->conn->query('select text from comments where id=9')->fetchColumn(0);
        $this->assertEquals('HeyHey', $result);
    }

    public function tableEntityProvider()
    {
        return array(
            array('posts',              'Post'),
            array('comments',           'Comment'),
            array('categories',         'Category'),
            array('post_categories',    'PostCategory'),
            array('post_tags',          'PostTag'),
        );
    }

    public function manyToMantTableProvider()
    {
        return array(
            array('post',   'category', 'post_categories'),
            array('user',   'group',    'user_groups'),
            array('group',  'profile',  'group_profiles'),
        );
    }

    public function columnsPropertyProvider()
    {
        return array(
            array('id'),
            array('text'),
            array('name'),
            array('content'),
            array('created'),
        );
    }

    public function foreignProvider()
    {
        return array(
            array('posts',      'post_id'),
            array('authors',    'author_id'),
            array('tags',       'tag_id'),
            array('users',      'user_id'),
        );
    }

}

class Post
{
    public $id, $title, $text, $author_id;
}
class Author
{
    public $id, $name;
}
class Comment
{
    public $id, $post_id, $text;
}
class Category
{
    public $id, $name, $category_id;
}
class PostCategory
{
    public $id, $post_id, $category_id;
}
