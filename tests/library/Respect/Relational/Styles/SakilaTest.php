<?php

namespace Respect\Data\Styles\Sakila;

use PDO,
    Respect\Relational\Db,
    Respect\Relational\Sql,
    Respect\Data\Styles\Sakila,
    Respect\Relational\Mapper;

class SakilaTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var Respect\Data\Styles\Sakila
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
                'post',
                array(
                    'post_id INTEGER PRIMARY KEY',
                    'title VARCHAR(255)',
                    'text TEXT',
                    'author_id INTEGER',
                )
            )
        );
        $conn->exec(
            (string) Sql::createTable(
                'author',
                array(
                    'author_id INTEGER PRIMARY KEY',
                    'name VARCHAR(255)'
                )
            )
        );
        $conn->exec(
            (string) Sql::createTable(
                'comment',
                array(
                    'comment_id INTEGER PRIMARY KEY',
                    'post_id INTEGER',
                    'text TEXT',
                )
            )
        );

        $conn->exec(
            (string) Sql::createTable(
                'category',
                array(
                    'category_id INTEGER PRIMARY KEY',
                    'name VARCHAR(255)',
                    'content VARCHAR(255)',
                    'description TEXT'
                )
            )
        );
        $conn->exec(
            (string) Sql::createTable(
                'post_category',
                array(
                    'post_category_id INTEGER PRIMARY KEY',
                    'post_id INTEGER',
                    'category_id INTEGER'
                )
            )
        );
        $this->posts = array(
            (object) array(
                'post_id' => 5,
                'title' => 'Post Title',
                'text' => 'Post Text',
                'author_id' => 1
            )
        );
        $this->authors = array(
            (object) array(
                'author_id' => 1,
                'name' => 'Author 1'
            )
        );
        $this->comments = array(
            (object) array(
                'comment_id' => 7,
                'post_id' => 5,
                'text' => 'Comment Text'
            ),
            (object) array(
                'comment_id' => 8,
                'post_id' => 4,
                'text' => 'Comment Text 2'
            )
        );
        $this->categories = array(
            (object) array(
                'category_id' => 2,
                'name' => 'Sample Category',
                'content' => null
            ),
            (object) array(
                'category_id' => 3,
                'name' => 'NONON',
                'content' => null
            )
        );
        $this->postsCategories = array(
            (object) array(
                'post_category_id' => 66,
                'post_id' => 5,
                'category_id' => 2
            )
        );

        foreach ($this->authors as $author)
            $db->insertInto('author', (array) $author)->values((array) $author)->exec();

        foreach ($this->posts as $post)
            $db->insertInto('post', (array) $post)->values((array) $post)->exec();

        foreach ($this->comments as $comment)
            $db->insertInto('comment', (array) $comment)->values((array) $comment)->exec();

        foreach ($this->categories as $category)
            $db->insertInto('category', (array) $category)->values((array) $category)->exec();

        foreach ($this->postsCategories as $postCategory)
            $db->insertInto('post_category', (array) $postCategory)->values((array) $postCategory)->exec();

        $this->conn     = $conn;
        $this->style    = new Sakila();
        $this->mapper   = new Mapper($conn);
        $this->mapper->setStyle($this->style);
        $this->mapper->entityNamespace = __NAMESPACE__ . '\\';
    }

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
            array('text'),
            array('name'),
            array('content'),
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
    public function test_foreign($table, $key)
    {
        $this->assertTrue($this->style->isRemoteIdentifier($key));
        $this->assertEquals($table, $this->style->remoteFromIdentifier($key));
        $this->assertEquals($key, $this->style->identifier($table));
        $this->assertEquals($key, $this->style->remoteIdentifier($table));
    }

    public function test_fetching_entity_typed()
    {
        $mapper = $this->mapper;
        $comment = $mapper->comment[8]->fetch();
        $this->assertInstanceOf(__NAMESPACE__ . '\Comment', $comment);
    }

    public function test_fetching_all_entity_typed()
    {
        $mapper = $this->mapper;
        $comment = $mapper->comment->fetchAll();
        $this->assertInstanceOf(__NAMESPACE__ . '\Comment', $comment[1]);
        
        $categories = $mapper->post_category->category->fetch();
        $this->assertInstanceOf(__NAMESPACE__ . '\PostCategory', $categories);
        $this->assertInstanceOf(__NAMESPACE__ . '\Category', $categories->category_id);
    }

    public function test_fetching_all_entity_typed_nested()
    {
        $mapper = $this->mapper;
        $comment = $mapper->comment->post->author->fetchAll();
        $this->assertInstanceOf(__NAMESPACE__ . '\Comment', $comment[0]);
        $this->assertInstanceOf(__NAMESPACE__ . '\Post',    $comment[0]->post_id);
        $this->assertInstanceOf(__NAMESPACE__ . '\Author',  $comment[0]->post_id->author_id);
    }

    public function test_persisting_entity_typed()
    {
        $mapper = $this->mapper;
        $comment = $mapper->comment[8]->fetch();
        $this->assertInstanceOf(__NAMESPACE__ . '\Comment', $comment);
        $comment->Text = 'HeyHey';
        $mapper->comment->persist($comment);
        $mapper->flush();
        $result = $this->conn->query('select text from comment where comment_id=8')->fetchColumn(0);
        $this->assertEquals('HeyHey', $result);
    }

    public function test_persisting_new_entity_typed()
    {
        $mapper = $this->mapper;
        $comment = new Comment();
        $comment->Text = 'HeyHey';
        $mapper->comment->persist($comment);
        $mapper->flush();
        $result = $this->conn->query('select text from comment where comment_id=9')->fetchColumn(0);
        $this->assertEquals('HeyHey', $result);
    }

}

class Post
{
    public $post_id, $title, $text, $author_id;
}
class Author
{
    public $author_id, $name;
}
class Comment
{
    public $comment_id, $post_id, $text;
}
class Category
{
    public $category_id, $name, $description;
}
class PostCategory
{
    public $post_category_id, $post_id, $category_id;
}
