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
        $post = array(
            'id' => 5,
            'title' => 'Post Title',
            'text' => 'Post Text'
        );
        $comment = array(
            'id' => 7,
            'post_id' => 5,
            'text' => 'Comment Text'
        );
        $db->insertInto('post', $post)->values($post)->exec();
        $db->insertInto('comment', $comment)->values($comment)->exec();
        $schema = new Schemas\Infered();
        $mapper = new Mapper($db, $schema);
        $this->object = $mapper;
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

    public function testBasicStatement()
    {
        $mapper = $this->object;
        $comment = $mapper->comment->post->fetch();
        $this->assertEquals(7, $comment->id);
        $this->assertEquals('Comment Text', $comment->text);
        $this->assertEquals(3, count(get_object_vars($comment)));
        $this->assertEquals(5, $comment->post_id->id);
        $this->assertEquals('Post Title', $comment->post_id->title);
        $this->assertEquals('Post Text', $comment->post_id->text);
        $this->assertEquals(3, count(get_object_vars($comment->post_id)));
    }

}
