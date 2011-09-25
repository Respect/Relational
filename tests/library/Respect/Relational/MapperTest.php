<?php

namespace Respect\Relational {

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
                    'name VARCHAR(255)',
                    'category_id INTEGER'
                )));
            $conn->exec((string) Sql::createTable('post_category', array(
                    'id INTEGER PRIMARY KEY',
                    'post_id INTEGER',
                    'category_id INTEGER'
                )));
            $conn->exec((string) 'ATTACH DATABASE "" AS information_schema');
            $conn->exec((string) Sql::createTable('information_schema.key_column_usage', array(
                    'column_name VARCHAR',
                    'table_name VARCHAR',
                    'constraint_name VARCHAR'
                )));
            $conn->exec((string) Sql::createTable('information_schema.table_constraints', array(
                    'constraint_type VARCHAR',
                    'constraint_name VARCHAR'
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
                    'id' => 2,
                    'name' => 'Sample Category'
                ),
                array(
                    'id' => 3,
                    'name' => 'NONON'
                )
            );
            $postsCategories = array(
                array(
                    'id' => 66,
                    'post_id' => 5,
                    'category_id' => 2
                )
            );
            $columnUsage = array(
                array('table_name' => 'post', 'constraint_name' => 'post', 'column_name' => 'id'),
                array('table_name' => 'comment', 'constraint_name' => 'comment', 'column_name' => 'id'),
                array('table_name' => 'category', 'constraint_name' => 'category', 'column_name' => 'id'),
                array('table_name' => 'post_category', 'constraint_name' => 'post_category', 'column_name' => 'id'),
            );
            $constraints = array(
                array('constraint_type' => 'PRIMARY KEY', 'constraint_name' => 'post'),
                array('constraint_type' => 'PRIMARY KEY', 'constraint_name' => 'comment'),
                array('constraint_type' => 'PRIMARY KEY', 'constraint_name' => 'category'),
                array('constraint_type' => 'PRIMARY KEY', 'constraint_name' => 'post_category'),
            );

            foreach ($posts as $post)
                $db->insertInto('post', $post)->values($post)->exec();

            foreach ($comments as $comment)
                $db->insertInto('comment', $comment)->values($comment)->exec();

            foreach ($categories as $category)
                $db->insertInto('category', $category)->values($category)->exec();

            foreach ($postsCategories as $postCategory)
                $db->insertInto('post_category', $postCategory)->values($postCategory)->exec();

            foreach ($columnUsage as $cu)
                $db->insertInto('information_schema.key_column_usage', $cu)->values($cu)->exec();

            foreach ($constraints as $c)
                $db->insertInto('information_schema.table_constraints', $c)->values($c)->exec();

            $mapper = new Mapper($conn);
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
            $entities->rewind();
            $one = $entities->current();
            $entities->next();
            $two = $entities->current();
            $entities->next();
            $this->assertEquals(null, $entities->current());
            $this->assertEquals(1, $one->id);
            $this->assertEquals('comm doido', $one->text);
            $this->assertEquals($two, $one->post_id);
            $this->assertEquals(5, $two->id);
            $this->assertEquals('post loko', $two->title);
            $this->assertEquals('opaaa', $two->text);
        }

        public function testFetchHydratedSingle()
        {
            $finder = Finder::comment();
            $schema = new Schemas\Infered();
            $conn = new \PDO('sqlite::memory:');
            $statement = $conn->query("SELECT 1 AS id, 5 AS post_id, 'comm doido' AS text");
            $statement->setFetchMode(\PDO::FETCH_NUM);
            $entities = $schema->fetchHydrated($finder, $statement);
            $entities->rewind();
            $one = $entities->current();
            $entities->next();
            $this->assertEquals(null, $entities->current());
            $this->assertEquals(1, $one->id);
            $this->assertEquals('comm doido', $one->text);
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

        public function testExtraQuery()
        {
            $mapper = $this->object;
            $comment = $mapper->comment->fetchAll(Sql::limit(1));
            $this->assertEquals(1, count($comment));
        }

        public function testNtoN()
        {
            $mapper = $this->object;
            $comments = $mapper->comment->post->post_category->category[2]->fetchAll();
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
            $c3 = $mapper->category[2]->fetch();
            $this->assertTrue($mapper->isTracked($c7));
            $this->assertTrue($mapper->isTracked($c8));
            $this->assertTrue($mapper->isTracked($p5));
            $this->assertTrue($mapper->isTracked($c3));
            $this->assertSame($c7, $mapper->getTracked('comment', 7));
            $this->assertSame($c8, $mapper->getTracked('comment', 8));
            $this->assertSame($p5, $mapper->getTracked('post', 5));
            $this->assertSame($c3, $mapper->getTracked('category', 2));
            $this->assertFalse($mapper->getTracked('none', 3));
            $this->assertFalse($mapper->getTracked('comment', 9889));
        }

        public function testSimplePersist()
        {
            $mapper = $this->object;
            $entity = (object) array('id' => 4, 'name' => 'inserted', 'category_id' => null);
            $mapper->persist(
                $entity, 'category'
            );
            $mapper->flush();
            $result = $this->conn->query('select * from category where id=4')->fetch(PDO::FETCH_OBJ);
            $this->assertEquals($entity, $result);
        }

        public function testSubCategory()
        {
            $mapper = $this->object;
            $entity = (object) array('id' => 8, 'name' => 'inserted', 'category_id' => 2);
            $mapper->persist(
                $entity, 'category'
            );
            $mapper->flush();
            $result = $this->conn->query('select * from category where id=8')->fetch(PDO::FETCH_OBJ);
            $result2 = $mapper->category[8]->category->fetch();
            $this->assertEquals($result->id, $result2->id);
            $this->assertEquals($result->name, $result2->name);
            $this->assertEquals($entity, $result);
        }

        public function testAutoIncrementPersist()
        {
            $mapper = $this->object;
            $entity = (object) array('id' => null, 'name' => 'inserted', 'category_id' => null);
            $mapper->persist(
                $entity, 'category'
            );
            $mapper->flush();
            $result = $this->conn->query('select * from category where name="inserted"')->fetch(PDO::FETCH_OBJ);
            $this->assertEquals($entity, $result);
            $this->assertEquals(4, $result->id);
        }

        public function testPassedIdentity()
        {
            $mapper = $this->object;

            $post = new \stdClass;
            $post->id = null;
            $post->title = 12345;
            $post->text = 'text abc';

            $comment = new \stdClass;
            $comment->id = null;
            $comment->post_id = $post;
            $comment->text = 'abc';

            $mapper->persist($post, 'post');
            $mapper->persist($comment, 'comment');
            $mapper->flush();

            $postId = $this->conn
                ->query('select id from post where title = 12345')
                ->fetchColumn(0);

            $comment = $this->conn->query('select * from comment where post_id = ' . $postId)
                ->fetchObject();

            $this->assertEquals('abc', $comment->text);
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

        public function testTyped()
        {
            $db = new Db($this->conn);
            $schema = new Schemas\Typed(new Schemas\Infered(), __NAMESPACE__);
            $mapper = new Mapper($db, $schema);
            $c8 = $mapper->comment[8]->fetch();
            $c8->text = 'abc';
            $mapper->persist($c8, 'comment');
            $mapper->flush();
            $this->assertInstanceOf(__NAMESPACE__ . '\\Comment', $c8);
        }

        public function testTypedInherited()
        {
            $db = new Db($this->conn);
            $schema = new Schemas\Typed(new Schemas\Infered(), __NAMESPACE__);
            $mapper = new Mapper($db, $schema);
            $cc = $mapper->comment->post[5]->fetch();
            $cc->text = 'abc';
            $mapper->persist($cc, 'comment');
            $mapper->flush();
            $this->assertInstanceOf(__NAMESPACE__ . '\\Comment', $cc);
            $this->assertInstanceOf(__NAMESPACE__ . '\\Post', $cc->post_id);
        }

        public function testInflected()
        {
            $db = new Db($this->conn);
            $schema = new Schemas\Inflected(new Schemas\Infered(), __NAMESPACE__);
            $mapper = new Mapper($db, $schema);
            $c8 = $mapper->comment[8]->fetch();
            $c8->postId = 333;
            $mapper->persist($c8, 'comment');
            $mapper->flush();
            $result = $this->conn->query('select post_id from comment where id=8')->fetchColumn(0);
            $this->assertEquals(333, $result);
            $this->assertObjectHasAttribute('postId', $c8);
            $this->assertFalse(isset($c8->post_id));
        }

        public function testeInflectedTypedEntityName()
        {

            $db = new Db($this->conn);
            $schema = new Schemas\Typed(new Schemas\Inflected(new Schemas\Infered()), __NAMESPACE__);
            $mapper = new Mapper($db, $schema);
            $c66 = $mapper->postCategory[66]->fetch();
            $this->assertInstanceOf(__NAMESPACE__ . '\\PostCategory', $c66);
            $this->assertObjectHasAttribute('postId', $c66);
            $c66->categoryId = 3;
            $mapper->persist($c66);
            $mapper->flush();
        }

        public function testeInflectedTypedEntityName2()
        {

            $db = new Db($this->conn);
            $schema = new Schemas\Inflected(new Schemas\Typed(new Schemas\Infered(), __NAMESPACE__));
            $mapper = new Mapper($db, $schema);
            $c66 = $mapper->post_category[66]->fetch();
            $this->assertInstanceOf(__NAMESPACE__ . '\\Post_Category', $c66);
            $this->assertObjectHasAttribute('postId', $c66);
        }

        public function testReflectedFetch()
        {
            $db = new Db($this->conn);
            $schema = new Schemas\FullyTyped(new Schemas\Reflected('Test'), 'Test');
            $mapper = new Mapper($db, $schema);
            $c5 = $mapper->comment->post[5]->fetch();
            $this->assertInstanceof('Test\\Comment', $c5);
            $this->assertObjectHasAttribute('post_id', $c5);
        }

        public function testReflectedInflectedPersistWithAutoincrement()
        {
            $db = new Db($this->conn);
            $schema = new Schemas\Inflected(new Schemas\FullyTyped(new Schemas\Reflected('Test'), 'Test'));
            $mapper = new Mapper($db, $schema);
            $entity = new \Test\Category;
            $entity->setName('inserted');
            $entity->setCategoryId(null);
            $mapper->persist($entity);
            $mapper->flush();
            $result = $this->conn->query('select * from category where name="inserted"')->fetch(PDO::FETCH_OBJ);
            $this->assertEquals($result->id, $entity->getId());
            $this->assertEquals($result->name, $entity->getName());
            $this->assertEquals($result->category_id, $entity->getCategoryId());
        }

        public function testEngineeredInflectedPersistWithAutoincrement()
        {
            $db = new Db($this->conn);
            $schema = new Schemas\Inflected(new Schemas\FullyTyped(new Schemas\ReverseEngineered($db), 'Test'));
            $mapper = new Mapper($db, $schema);
            $entity = new \Test\Category;
            $entity->setName('inserted');
            $entity->setCategoryId(null);
            $mapper->persist($entity, 'category');
            $mapper->flush();
            $result = $this->conn->query('select * from category where name="inserted"')->fetch(PDO::FETCH_OBJ);
            $this->assertEquals($result->id, $entity->getId());
            $this->assertEquals($result->name, $entity->getName());
            $this->assertEquals($result->category_id, $entity->getCategoryId());
        }

    }

    class Comment
    {
        
    }

    class PostCategory
    {
        
    }

    class Post_Category
    {
        
    }

    class Post
    {
        
    }

}

namespace Test {

    class Category
    {

        protected $id, $name, $categoryId;

        public function __construct($id=null)
        {
            $this->setId($id);
        }

        public function getId()
        {
            return $this->id;
        }

        public function setId($id)
        {
            $this->id = $id;
        }

        public function getName()
        {
            return $this->name;
        }

        public function setName($name)
        {
            $this->name = $name;
        }

        public function getCategoryId()
        {
            return $this->categoryId;
        }

        public function setCategoryId($categoryId)
        {
            $this->categoryId = $categoryId;
        }

    }

    class Post
    {

        protected $id, $title, $text;

        public function __construct($id=null)
        {
            $this->setId($id);
        }

        public function setTitle($title)
        {
            $this->title = $title;
        }

        public function setText($text)
        {
            $this->text = $text;
        }

        public function getId()
        {
            return $this->id;
        }

        public function setId($id)
        {
            $this->id = $id;
        }

        public function getTitle()
        {
            return $this->title;
        }

        public function getText()
        {
            return $this->text;
        }

    }

    class Comment
    {

        protected $id, $post_id, $text;

        public function __construct($id=null)
        {
            
        }

        public function getId()
        {
            return $this->id;
        }

        public function setId($id)
        {
            $this->id = $id;
        }

        public function getPost_id()
        {
            return $this->post_id;
        }

        public function setPost_id(Post $post_id)
        {
            $this->post_id = $post_id;
        }

        public function getText()
        {
            return $this->text;
        }

        public function setText($text)
        {
            $this->text = $text;
        }

    }

}