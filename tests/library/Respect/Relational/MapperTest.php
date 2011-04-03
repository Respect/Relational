<?php

namespace Respect\Relational;

use PDO;

class MapperTest extends \PHPUnit_Framework_TestCase
{

    protected $object;

    public function setUp()
    {
        $conn = new PDO('sqlite::memory:');
        $db = new Db($conn);
        $conn->exec((string) Sql::createTable('post', array(
                'id INTEGER PRIMARY KEY',
                'title VARCHAR(255)',
                'text TEXT',
            )));
        $conn->exec((string) Sql::createTable('comment', array(
                'id INTEGER PRIMARY KEY',
                'post_id INTEGER',
                'text TEXT',
            )));
        $conn->exec((string) Sql::createTable('category', array(
                'id INTEGER PRIMARY KEY',
                'name VARCHAR(255)'
            )));
        $conn->exec((string) Sql::createTable('post_category', array(
                'id INTEGER PRIMARY KEY',
                'post_id INTEGER',
                'category_id INTEGER'
            )));
        $posts = array(
            array(
                'id' => 5,
                'title' => 'Post Title',
                'text' => 'Post Text'
            )
        );
        $comments = array(
            array(
                'id' => 7,
                'post_id' => 5,
                'text' => 'Comment Text'
            ),
            array(
                'id' => 8,
                'post_id' => 4,
                'text' => 'Comment Text 2'
            )
        );
        $categories = array(
            array(
                'id' => 3,
                'name' => 'Sample Category'
            )
        );
        $postsCategories = array(
            array(
                'id' => 66,
                'post_id' => 5,
                'category_id' => 3
            )
        );

        foreach ($posts as $post)
            $db->insertInto('post', $post)->values($post)->exec();

        foreach ($comments as $comment)
            $db->insertInto('comment', $comment)->values($comment)->exec();

        foreach ($categories as $category)
            $db->insertInto('category', $category)->values($category)->exec();

        foreach ($postsCategories as $postCategory)
            $db->insertInto('post_category', $postCategory)->values($postCategory)->exec();

        $schema = new Schemas\Infered();
        $mapper = new Mapper($db, $schema);
        $this->object = $mapper;
        $this->conn = $conn;
    }

    public function testFetchHydrated()
    {
        $finder = Finder::comment()->post();
        $schema = new Schemas\Infered();
        $conn = new \PDO('sqlite::memory:');
        $statement = $conn->query("SELECT 1 AS id, 5 AS post_id, 'comm doido' AS text, 5 AS id, 'post loko' AS title, 'opaaa' AS text");
        $statement->setFetchMode(\PDO::FETCH_NUM);
        $entities = $schema->fetchHydrated($finder, $statement);
        $this->assertArrayHasKey('comment', $entities);
        $this->assertArrayHasKey('post', $entities);
        $this->assertArrayHasKey(1, $entities['comment']);
        $this->assertArrayHasKey(5, $entities['post']);
        $this->assertEquals(5, $entities['post'][5]->id);
        $this->assertEquals(1, $entities['comment'][1]->id);
        $this->assertSame($entities['post'][5], $entities['comment'][1]->post_id);
        $this->assertEquals('comm doido', $entities['comment'][1]->text);
        $this->assertEquals('opaaa', $entities['post'][5]->text);
        $this->assertEquals('post loko', $entities['post'][5]->title);
        $this->assertEquals(3, count(get_object_vars($entities['post'][5])));
        $this->assertEquals(3, count(get_object_vars($entities['comment'][1])));
    }

    public function testFetchHydratedSingle()
    {
        $finder = Finder::comment();
        $schema = new Schemas\Infered();
        $conn = new \PDO('sqlite::memory:');
        $statement = $conn->query("SELECT 1 AS id, 5 AS post_id, 'comm doido' AS text");
        $statement->setFetchMode(\PDO::FETCH_NUM);
        $entities = $schema->fetchHydrated($finder, $statement);
        $this->assertArrayHasKey('comment', $entities);
        $this->assertArrayHasKey(1, $entities['comment']);
        $this->assertEquals(1, $entities['comment'][1]->id);
        $this->assertEquals('comm doido', $entities['comment'][1]->text);
        $this->assertEquals(3, count(get_object_vars($entities['comment'][1])));
    }

    public function testBasicStatementSingle()
    {
        $mapper = $this->object;
        $comments = $mapper->comment->post[5]->fetchAll();
        $comment = current($comments);
        $this->assertEquals(1, count($comments));
        $this->assertEquals(7, $comment->id);
        $this->assertEquals('Comment Text', $comment->text);
        $this->assertEquals(3, count(get_object_vars($comment)));
        $this->assertEquals(5, $comment->post_id->id);
        $this->assertEquals('Post Title', $comment->post_id->title);
        $this->assertEquals('Post Text', $comment->post_id->text);
        $this->assertEquals(3, count(get_object_vars($comment->post_id)));
    }

    public function testBasicStatement()
    {
        $mapper = $this->object;
        $comment = $mapper->comment->post[5]->fetch();
        $this->assertEquals(7, $comment->id);
        $this->assertEquals('Comment Text', $comment->text);
        $this->assertEquals(3, count(get_object_vars($comment)));
        $this->assertEquals(5, $comment->post_id->id);
        $this->assertEquals('Post Title', $comment->post_id->title);
        $this->assertEquals('Post Text', $comment->post_id->text);
        $this->assertEquals(3, count(get_object_vars($comment->post_id)));
    }

    public function testNtoN()
    {
        $mapper = $this->object;
        $comments = $mapper->comment->post->post_category->category[3]->fetchAll();
        $comment = current($comments);
        $this->assertEquals(1, count($comments));
        $this->assertEquals(7, $comment->id);
        $this->assertEquals('Comment Text', $comment->text);
        $this->assertEquals(3, count(get_object_vars($comment)));
        $this->assertEquals(5, $comment->post_id->id);
        $this->assertEquals('Post Title', $comment->post_id->title);
        $this->assertEquals('Post Text', $comment->post_id->text);
        $this->assertEquals(3, count(get_object_vars($comment->post_id)));
    }

    public function testTracking()
    {
        $mapper = $this->object;
        $c7 = $mapper->comment[7]->fetch();
        $c8 = $mapper->comment[8]->fetch();
        $p5 = $mapper->post[5]->fetch();
        $c3 = $mapper->category[3]->fetch();
        $this->assertTrue($mapper->isTracked($c7));
        $this->assertTrue($mapper->isTracked($c8));
        $this->assertTrue($mapper->isTracked($p5));
        $this->assertTrue($mapper->isTracked($c3));
        $this->assertSame($c7, $mapper->getTracked('comment', 7));
        $this->assertSame($c8, $mapper->getTracked('comment', 8));
        $this->assertSame($p5, $mapper->getTracked('post', 5));
        $this->assertSame($c3, $mapper->getTracked('category', 3));
        $this->assertFalse($mapper->getTracked('none', 3));
        $this->assertFalse($mapper->getTracked('comment', 9889));
    }

    public function testSimplePersist()
    {
        $mapper = $this->object;
        $entity = (object) array('id' => 4, 'name' => 'inserted');
        $mapper->persist(
            $entity,
            'category'
        );
        $mapper->flush();
        $result = $this->conn->query('select * from category where id=4')->fetch(PDO::FETCH_OBJ);
        $this->assertEquals($entity, $result);
    }

    public function testJoinedPersist()
    {
        $mapper = $this->object;
        $entity = $mapper->comment[8]->fetch();
        $entity->text = 'HeyHey';
        $mapper->persist($entity, 'comment');
        $mapper->flush();
        $result = $this->conn->query('select text from comment where id=8')->fetchColumn(0);
        $this->assertEquals('HeyHey', $result);
    }

    public function testJoinedPersistNoName()
    {
        $mapper = $this->object;
        $entity = $mapper->comment[8]->fetch();
        $entity->text = 'HeyHey';
        $mapper->persist($entity);
        $mapper->flush();
        $result = $this->conn->query('select text from comment where id=8')->fetchColumn(0);
        $this->assertEquals('HeyHey', $result);
    }

    public function testRemove()
    {
        $mapper = $this->object;
        $c8 = $mapper->comment[8]->fetch();
        $pre = $this->conn->query('select count(*) from comment')->fetchColumn(0);
        $mapper->remove($c8);
        $mapper->flush();
        $total = $this->conn->query('select count(*) from comment')->fetchColumn(0);
        $this->assertEquals($total, $pre - 1);
    }

}
