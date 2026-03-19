<?php

declare(strict_types=1);

namespace Respect\Relational;

use Datetime;
use DomainException;
use Exception;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Respect\Data\Collections\Composite;
use Respect\Data\Collections\Filtered;
use Respect\Data\Collections\Typed;
use Respect\Data\EntityFactory;
use Respect\Data\Styles;
use stdClass;
use Throwable;
use TypeError;

use function array_keys;
use function array_reverse;
use function array_values;
use function count;
use function current;
use function date;
use function end;
use function get_object_vars;
use function reset;

#[CoversClass(Mapper::class)]
class MapperTest extends TestCase
{
    protected PDO $conn;

    protected Mapper $mapper;

    /** @var list<object> */
    protected array $posts;

    /** @var list<object> */
    protected array $authors;

    /** @var list<object> */
    protected array $comments;

    /** @var list<object> */
    protected array $categories;

    /** @var list<object> */
    protected array $postsCategories;

    /** @var list<object> */
    protected array $issues;

    protected function setUp(): void
    {
        $conn = new PDO('sqlite::memory:');
        $db = new Db($conn);
        $conn->exec((string) Sql::createTable('post', [
            'id INTEGER PRIMARY KEY',
            'title VARCHAR(255)',
            'text TEXT',
            'author_id INTEGER',
        ]));
        $conn->exec((string) Sql::createTable('author', [
            'id INTEGER PRIMARY KEY',
            'name VARCHAR(255)',
        ]));
        $conn->exec((string) Sql::createTable('comment', [
            'id INTEGER PRIMARY KEY',
            'post_id INTEGER',
            'text TEXT',
            'datetime DATETIME',
        ]));
        $conn->exec((string) Sql::createTable('category', [
            'id INTEGER PRIMARY KEY',
            'name VARCHAR(255)',
            'category_id INTEGER',
        ]));
        $conn->exec((string) Sql::createTable('post_category', [
            'id INTEGER PRIMARY KEY',
            'post_id INTEGER',
            'category_id INTEGER',
        ]));
        $conn->exec((string) Sql::createTable('issues', [
            'id INTEGER PRIMARY KEY',
            'type VARCHAR(255)',
            'title VARCHAR(22)',
        ]));
        $this->posts = [
            (object) [
                'id' => 5,
                'title' => 'Post Title',
                'text' => 'Post Text',
                'author_id' => 1,
            ],
        ];
        $this->authors = [
            (object) [
                'id' => 1,
                'name' => 'Author 1',
            ],
        ];
        $this->comments = [
            (object) [
                'id' => 7,
                'post_id' => 5,
                'text' => 'Comment Text',
                'datetime' => '2012-06-19 00:35:42',
            ],
            (object) [
                'id' => 8,
                'post_id' => 4,
                'text' => 'Comment Text 2',
                'datetime' => '2012-06-19 00:35:42',
            ],
        ];
        $this->categories = [
            (object) [
                'id' => 2,
                'name' => 'Sample Category',
                'category_id' => null,
            ],
            (object) [
                'id' => 3,
                'name' => 'NONON',
                'category_id' => null,
            ],
        ];
        $this->postsCategories = [
            (object) [
                'id' => 66,
                'post_id' => 5,
                'category_id' => 2,
            ],
        ];
        $this->issues = [
            (object) [
                'id' => 1,
                'type' => 'bug',
                'title' => 'Bug 1',
            ],
            (object) [
                'id' => 2,
                'type' => 'improvement',
                'title' => 'Improvement 1',
            ],
        ];

        foreach ($this->authors as $author) {
            $cols = (array) $author;
            $db->insertInto('author', array_keys($cols))->values(array_values($cols))->exec();
        }

        foreach ($this->posts as $post) {
            $cols = (array) $post;
            $db->insertInto('post', array_keys($cols))->values(array_values($cols))->exec();
        }

        foreach ($this->comments as $comment) {
            $cols = (array) $comment;
            $db->insertInto('comment', array_keys($cols))->values(array_values($cols))->exec();
        }

        foreach ($this->categories as $category) {
            $cols = (array) $category;
            $db->insertInto('category', array_keys($cols))->values(array_values($cols))->exec();
        }

        foreach ($this->postsCategories as $postCategory) {
            $cols = (array) $postCategory;
            $db->insertInto('post_category', array_keys($cols))->values(array_values($cols))->exec();
        }

        foreach ($this->issues as $issue) {
            $cols = (array) $issue;
            $db->insertInto('issues', array_keys($cols))->values(array_values($cols))->exec();
        }

        $mapper = new Mapper($conn);
        $this->mapper = $mapper;
        $this->conn = $conn;
    }

    public function testCreatingWithDbInstance(): void
    {
        $db = new Db($this->conn);
        $mapper = new Mapper($db);
        $this->assertSame($db, $mapper->db);
    }

    public function testGetDefinedDbInstance(): void
    {
        $db = new Db($this->conn);
        $mapper = new Mapper($db);

        $this->assertSame($db, $mapper->db);
    }

    public function testCreatingWithInvalidArgsShouldThrowException(): void
    {
        $this->expectException(TypeError::class);
        new Mapper('foo');
    }

    public function testRollingBackTransaction(): void
    {
        $conn = $this->createMock(PDO::class);
        $conn->expects($this->any())
            ->method('prepare')
            ->will($this->throwException(new Exception()));
        $conn->expects($this->once())
            ->method('rollback');
        $mapper = new Mapper($conn);
        $obj = new stdClass();
        $obj->id = null;
        $mapper->foo->persist($obj);
        try {
            $mapper->flush();
        } catch (Throwable) {
            //OK!
        }
    }

    public function testIgnoringLastInsertIdErrors(): void
    {
        $conn = $this->createStub(PDO::class);
        $conn->method('getAttribute')
            ->willReturn('sqlite');
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')
            ->willReturn(true);
        $conn->method('prepare')
            ->willReturn($stmt);
        $conn->method('lastInsertId')
            ->willThrowException(new PDOException());
        $conn->method('beginTransaction')
            ->willReturn(true);
        $conn->method('commit')
            ->willReturn(true);
        $mapper = new Mapper($conn);
        $obj = new stdClass();
        $obj->id = null;
        $obj->name = 'bar';
        $mapper->foo->persist($obj);
        $mapper->flush();
        $this->assertNull($obj->id);
        $this->assertEquals('bar', $obj->name);
    }

    public function testRemovingUntrackedObject(): void
    {
        $comment = new stdClass();
        $comment->id = 7;
        $this->assertNotEmpty($this->mapper->comment[7]->fetch());
        $this->mapper->comment->remove($comment);
        $this->mapper->flush();
        $this->assertEmpty($this->mapper->comment[7]->fetch());
    }

    public function testFetchingSingleEntityFromCollectionShouldReturnFirstRecordFromTable(): void
    {
        $expectedFirstComment = reset($this->comments);
        $fetchedFirstComment = $this->mapper->comment->fetch();
        $this->assertEquals($expectedFirstComment, $fetchedFirstComment);
    }

    public function testFetchingAllEntitesFromCollectionShouldReturnAllRecords(): void
    {
        $expectedCategories = $this->categories;
        $fetchedCategories = $this->mapper->category->fetchAll();
        $this->assertEquals($expectedCategories, $fetchedCategories);
    }

    public function testExtraSqlOnSingleFetchShouldBeAppliedOnMapperSql(): void
    {
        $expectedLast = end($this->comments);
        $fetchedLast = $this->mapper->comment->fetch(Sql::orderBy('id DESC'));
        $this->assertEquals($expectedLast, $fetchedLast);
    }

    public function testExtraSqlOnFetchAllShouldBeAppliedOnMapperSql(): void
    {
        $expectedComments = array_reverse($this->comments);
        $fetchedComments = $this->mapper->comment->fetchAll(Sql::orderBy('id DESC'));
        $this->assertEquals($expectedComments, $fetchedComments);
    }

    public function testMultipleConditionsAcrossCollectionsProduceAndClause(): void
    {
        $mapper = $this->mapper;
        $comment = $mapper->comment[7]->post[5]->fetch();
        $this->assertEquals(7, $comment->id);
        $this->assertEquals(5, $comment->post_id->id);
        $this->assertEquals('Post Title', $comment->post_id->title);
    }

    public function testNestedCollectionsShouldHydrateResults(): void
    {
        $mapper = $this->mapper;
        $comment = $mapper->comment->post[5]->fetch();
        $this->assertEquals(7, $comment->id);
        $this->assertEquals('Comment Text', $comment->text);
        $this->assertEquals(4, count(get_object_vars($comment)));
        $this->assertEquals(5, $comment->post_id->id);
        $this->assertEquals('Post Title', $comment->post_id->title);
        $this->assertEquals('Post Text', $comment->post_id->text);
        $this->assertEquals(4, count(get_object_vars($comment->post_id)));
    }

    public function testOneToN(): void
    {
        $mapper = $this->mapper;
        $comments = $mapper->comment->post($mapper->author)->fetchAll();
        $comment = current($comments);
        $this->assertEquals(1, count($comments));
        $this->assertEquals(7, $comment->id);
        $this->assertEquals('Comment Text', $comment->text);
        $this->assertEquals(4, count(get_object_vars($comment)));
        $this->assertEquals(5, $comment->post_id->id);
        $this->assertEquals('Post Title', $comment->post_id->title);
        $this->assertEquals('Post Text', $comment->post_id->text);
        $this->assertEquals(4, count(get_object_vars($comment->post_id)));
        $this->assertEquals(1, $comment->post_id->author_id->id);
        $this->assertEquals('Author 1', $comment->post_id->author_id->name);
        $this->assertEquals(2, count(get_object_vars($comment->post_id->author_id)));
    }

    public function testNtoN(): void
    {
        $mapper = $this->mapper;
        $comments = $mapper->comment->post->post_category->category[2]->fetchAll();
        $comment = current($comments);
        $this->assertEquals(1, count($comments));
        $this->assertEquals(7, $comment->id);
        $this->assertEquals('Comment Text', $comment->text);
        $this->assertEquals(4, count(get_object_vars($comment)));
        $this->assertEquals(5, $comment->post_id->id);
        $this->assertEquals('Post Title', $comment->post_id->title);
        $this->assertEquals('Post Text', $comment->post_id->text);
        $this->assertEquals(4, count(get_object_vars($comment->post_id)));
    }

    public function testManyToManyReverse(): void
    {
        $mapper = $this->mapper;
        $cat = $mapper->category->post_category->post[5]->fetch();
        $this->assertEquals(2, $cat->id);
        $this->assertEquals('Sample Category', $cat->name);
    }

    public function testSimplePersist(): void
    {
        $mapper = $this->mapper;
        $entity = (object) ['id' => 4, 'name' => 'inserted', 'category_id' => null];
        $mapper->category->persist($entity);
        $mapper->flush();
        $result = $this->query('select * from category where id=4')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertEquals($entity, $result);
    }

    public function testSimplePersistCollection(): void
    {
        $mapper = $this->mapper;
        $entity = (object) ['id' => 4, 'name' => 'inserted', 'category_id' => null];
        $mapper->category->persist($entity);
        $mapper->flush();
        $result = $this->query('select * from category where id=4')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertEquals($entity, $result);
    }

    public function testNestedPersistCollection(): void
    {
        $postWithAuthor = (object) [
            'id' => null,
            'title' => 'hi',
            'text' => 'hi text',
            'author_id' => (object) [
                'id' => null,
                'name' => 'New',
            ],
        ];
        $this->mapper->post->author->persist($postWithAuthor);
        $this->mapper->flush();
        $author = $this->query(
            'select * from author order by id desc limit 1',
        )->fetch(PDO::FETCH_OBJ);
        $post = $this->query(
            'select * from post order by id desc limit 1',
        )->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('New', $author->name);
        $this->assertEquals('hi', $post->title);
    }

    public function testNestedPersistCollectionShortcut(): void
    {
        $postWithAuthor = (object) [
            'id' => null,
            'title' => 'hi',
            'text' => 'hi text',
            'author_id' => (object) [
                'id' => null,
                'name' => 'New',
            ],
        ];
        $this->mapper->postAuthor = $this->mapper->post->author;
        $this->mapper->postAuthor->persist($postWithAuthor);
        $this->mapper->flush();
        $author = $this->query(
            'select * from author order by id desc limit 1',
        )->fetch(PDO::FETCH_OBJ);
        $post = $this->query(
            'select * from post order by id desc limit 1',
        )->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('New', $author->name);
        $this->assertEquals('hi', $post->title);
    }

    public function testNestedPersistCollectionWithChildrenShortcut(): void
    {
        $postWithAuthor = (object) [
            'id' => null,
            'title' => 'hi',
            'text' => 'hi text',
            'author_id' => (object) [
                'id' => null,
                'name' => 'New',
            ],
        ];
        $this->mapper->postAuthor = $this->mapper->post($this->mapper->author);
        $this->mapper->postAuthor->persist($postWithAuthor);
        $this->mapper->flush();
        $author = $this->query(
            'select * from author order by id desc limit 1',
        )->fetch(PDO::FETCH_OBJ);
        $post = $this->query(
            'select * from post order by id desc limit 1',
        )->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('New', $author->name);
        $this->assertEquals('hi', $post->title);
    }

    public function testSubCategory(): void
    {
        $mapper = $this->mapper;
        $entity = (object) ['id' => 8, 'name' => 'inserted', 'category_id' => 2];
        $mapper->category->persist($entity);
        $mapper->flush();
        $result = $this->query('select * from category where id=8')
            ->fetch(PDO::FETCH_OBJ);
        $result2 = $mapper->category[8]->category->fetch();
        $this->assertEquals($result->id, $result2->id);
        $this->assertEquals($result->name, $result2->name);
        $this->assertEquals($entity, $result);
    }

    public function testSubCategoryCondition(): void
    {
        $mapper = $this->mapper;
        $entity = (object) ['id' => 8, 'name' => 'inserted', 'category_id' => 2];
        $mapper->category->persist($entity);
        $mapper->flush();
        $result = $this->query('select * from category where id=8')
            ->fetch(PDO::FETCH_OBJ);
        $result2 = $mapper->category(['id' => 8])->category->fetch();
        $this->assertEquals($result->id, $result2->id);
        $this->assertEquals($result->name, $result2->name);
        $this->assertEquals($entity, $result);
    }

    public function testAutoIncrementPersist(): void
    {
        $mapper = $this->mapper;
        $entity = (object) ['id' => null, 'name' => 'inserted', 'category_id' => null];
        $mapper->category->persist($entity);
        $mapper->flush();
        $result = $this->query(
            'select * from category where name="inserted"',
        )->fetch(PDO::FETCH_OBJ);
        $this->assertEquals($entity, $result);
        $this->assertEquals(4, $result->id);
    }

    public function testPassedIdentity(): void
    {
        $mapper = $this->mapper;

        $post = new stdClass();
        $post->id = null;
        $post->title = 12345;
        $post->text = 'text abc';

        $comment = new stdClass();
        $comment->id = null;
        $comment->post_id = $post;
        $comment->text = 'abc';

        $mapper->post->persist($post);
        $mapper->comment->persist($comment);
        $mapper->flush();

        $postId = $this->query('select id from post where title = 12345')
            ->fetchColumn(0);

        $comment = $this->query('select * from comment where post_id = ' . $postId)
            ->fetchObject();

        self::assertInstanceOf(stdClass::class, $comment);
        $this->assertEquals('abc', $comment->text);
    }

    public function testJoinedPersist(): void
    {
        $mapper = $this->mapper;
        $entity = $mapper->comment[8]->fetch();
        $entity->text = 'HeyHey';
        $mapper->comment->persist($entity);
        $mapper->flush();
        $result = $this->query('select text from comment where id=8')
            ->fetchColumn(0);
        $this->assertEquals('HeyHey', $result);
    }

    public function testRemove(): void
    {
        $mapper = $this->mapper;
        $c8 = $mapper->comment[8]->fetch();
        $pre = (int) $this->query('select count(*) from comment')->fetchColumn(0);
        $mapper->comment->remove($c8);
        $mapper->flush();
        $total = (int) $this->query('select count(*) from comment')->fetchColumn(0);
        $this->assertEquals($total, $pre - 1);
    }

    public function testFetchingEntityTyped(): void
    {
        $mapper = new Mapper($this->conn, new EntityFactory(entityNamespace: '\Respect\Relational\\'));
        $comment = $mapper->comment[8]->fetch();
        $this->assertInstanceOf('\Respect\Relational\Comment', $comment);
    }

    public function testFetchingAllEntityTyped(): void
    {
        $mapper = new Mapper($this->conn, new EntityFactory(entityNamespace: '\Respect\Relational\\'));
        $comment = $mapper->comment->fetchAll();
        $this->assertInstanceOf('\Respect\Relational\Comment', $comment[1]);
    }

    public function testFetchingAllEntityTypedNested(): void
    {
        $mapper = new Mapper($this->conn, new EntityFactory(entityNamespace: '\Respect\Relational\\'));
        $comment = $mapper->comment->post->fetchAll();
        $this->assertInstanceOf('\Respect\Relational\Comment', $comment[0]);
        $this->assertInstanceOf('\Respect\Relational\Post', $comment[0]->post_id);
    }

    public function testPersistingEntityTyped(): void
    {
        $mapper = new Mapper($this->conn, new EntityFactory(entityNamespace: '\Respect\Relational\\'));
        $comment = $mapper->comment[8]->fetch();
        $comment->text = 'HeyHey';
        $mapper->comment->persist($comment);
        $mapper->flush();
        $result = $this->query('select text from comment where id=8')
            ->fetchColumn(0);
        $this->assertEquals('HeyHey', $result);
    }

    public function testPersistingNewEntityTyped(): void
    {
        $mapper = new Mapper($this->conn, new EntityFactory(entityNamespace: '\Respect\Relational\\'));
        $comment = new Comment();
        $comment->text = 'HeyHey';
        $mapper->comment->persist($comment);
        $mapper->flush();
        $result = $this->query('select text from comment where id=9')
            ->fetchColumn(0);
        $this->assertEquals('HeyHey', $result);
    }

    public function testSettersAndGettersDatetimeAsObject(): void
    {
        $mapper = new Mapper($this->conn, new EntityFactory(entityNamespace: '\Respect\Relational\\'));
        $post = new Post();
        $post->id = 44;
        $post->text = 'Test using datetime setters';
        $post->setDatetime(new Datetime('now'));
        $mapper->post->persist($post);
        $mapper->flush();

        $result = $mapper->post[44]->fetch();
        $this->assertInstanceOf('\Datetime', $result->getDatetime());
        $this->assertEquals(date('Y-m-d'), $result->getDatetime()->format('Y-m-d'));
    }

    public function testStyle(): void
    {
        $this->assertInstanceOf(
            'Respect\Data\Styles\Stylable',
            $this->mapper->style,
        );
        $this->assertInstanceOf(
            'Respect\Data\Styles\Standard',
            $this->mapper->style,
        );
        $styles = [
            new Styles\CakePHP(),
            new Styles\NorthWind(),
            new Styles\Sakila(),
            new Styles\Standard(),
        ];
        foreach ($styles as $style) {
            $mapper = new Mapper($this->conn, new EntityFactory(style: $style));
            $this->assertEquals($style, $mapper->style);
        }
    }

    public function testFetchingaSingleFilteredCollectionShouldNotBringFilteredChildren(): void
    {
        $mapper = $this->mapper;
        $mapper->authorsWithPosts = Filtered::post()->author();
        $author = $mapper->authorsWithPosts->fetch();
        $this->assertEquals($this->authors[0], $author);
    }

    public function testPersistingaPreviouslyFetchedFilteredEntityBackIntoItsCollection(): void
    {
        $mapper = $this->mapper;
        $mapper->authorsWithPosts = Filtered::post()->author();
        $author = $mapper->authorsWithPosts->fetch();
        $author->name = 'Author Changed';
        $mapper->authorsWithPosts->persist($author);
        $mapper->flush();
        $result = $this->query('select name from author where id=1')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('Author Changed', $result->name);
    }

    public function testPersistingaPreviouslyFetchedFilteredEntityBackIntoaForeignCompatibleCollection(): void
    {
        $mapper = $this->mapper;
        $mapper->authorsWithPosts = Filtered::post()->author();
        $author = $mapper->authorsWithPosts->fetch();
        $author->name = 'Author Changed';
        $mapper->author->persist($author);
        $mapper->flush();
        $result = $this->query('select name from author where id=1')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('Author Changed', $result->name);
    }

    public function testPersistingaNewlyCreatedFilteredEntityIntoItsCollection(): void
    {
        $mapper = $this->mapper;
        $mapper->authorsWithPosts = Filtered::post()->author();
        $author = new stdClass();
        $author->id = null;
        $author->name = 'Author Changed';
        $mapper->authorsWithPosts->persist($author);
        $mapper->flush();
        $result = $this->query(
            'select name from author order by id desc',
        )->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('Author Changed', $result->name);
    }

    public function testPersistingaNewlyCreatedFilteredEntityIntoaForeignCompatibleCollection(): void
    {
        $mapper = $this->mapper;
        $mapper->authorsWithPosts = Filtered::post()->author();
        $author = new stdClass();
        $author->id = null;
        $author->name = 'Author Changed';
        $mapper->author->persist($author);
        $mapper->flush();
        $result = $this->query(
            'select name from author order by id desc',
        )->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('Author Changed', $result->name);
    }

    public function testFechingMultipleFilteredCollectionsShouldNotBringFilteredChildren(): void
    {
        $mapper = $this->mapper;
        $mapper->authorsWithPosts = Filtered::post()->author();
        $authors = $mapper->authorsWithPosts->fetchAll();
        $this->assertEquals($this->authors, $authors);
    }

    public function testFilteredCollectionsShouldHydrateNonFilteredPartsAsUsual(): void
    {
        $mapper = $this->mapper;
        $mapper->postsFromAuthorsWithComments = Filtered::comment()->post()->author();
        $post = $mapper->postsFromAuthorsWithComments->fetch();
        $this->assertEquals(
            (object) (['author_id' => $post->author_id] + (array) $this->posts[0]),
            $post,
        );
        $this->assertEquals($this->authors[0], $post->author_id);
    }

    public function testFilteredCollectionsShouldPersistHydratedNonFilteredPartsAsUsual(): void
    {
        $mapper = $this->mapper;
        $mapper->postsFromAuthorsWithComments = Filtered::comment()->post()->author();
        $post = $mapper->postsFromAuthorsWithComments->fetch();
        $this->assertEquals(
            (object) (['author_id' => $post->author_id] + (array) $this->posts[0]),
            $post,
        );
        $this->assertEquals($this->authors[0], $post->author_id);
        $post->title = 'Title Changed';
        $post->author_id->name = 'John';
        $mapper->postsFromAuthorsWithComments->persist($post);
        $mapper->flush();
        $result = $this->query('select title from post where id=5')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('Title Changed', $result->title);
        $result = $this->query('select name from author where id=1')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('John', $result->name);
    }

    public function testMultipleFilteredCollectionsDontPersist(): void
    {
        $mapper = $this->mapper;
        $mapper->authorsWithPosts = Filtered::comment()->post->stack(Filtered::author());
        $post = $mapper->authorsWithPosts->fetch();
        $this->assertEquals(
            (object) ['id' => '5', 'author_id' => 1, 'text' => 'Post Text', 'title' => 'Post Title'],
            $post,
        );
        $post->title = 'Title Changed';
        $post->author_id = $mapper->author[1]->fetch();
        $post->author_id->name = 'A';
        $mapper->postsFromAuthorsWithComments->persist($post);
        $mapper->flush();
        $result = $this->query('select title from post where id=5')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('Title Changed', $result->title);
        $result = $this->query('select name from author where id=1')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertNotEquals('A', $result->name);
    }

    public function testMultipleFilteredCollectionsDontPersistNewlyCreateObjects(): void
    {
        $mapper = $this->mapper;
        $mapper->authorsWithPosts = Filtered::comment()->post->stack(Filtered::author());
        $post = $mapper->authorsWithPosts->fetch();
        $this->assertEquals(
            (object) ['id' => '5', 'author_id' => 1, 'text' => 'Post Text', 'title' => 'Post Title'],
            $post,
        );
        $post->title = 'Title Changed';
        $post->author_id = new stdClass();
        $post->author_id->id = null;
        $post->author_id->name = 'A';
        $mapper->postsFromAuthorsWithComments->persist($post);
        $mapper->flush();
        $result = $this->query('select title from post where id=5')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('Title Changed', $result->title);
        $result = $this->query(
            'select name from author order by id desc',
        )->fetch(PDO::FETCH_OBJ);
        $this->assertNotEquals('A', $result->name);
    }

    public function testMultipleFilteredCollectionsFetchAtOnceDontPersist(): void
    {
        $mapper = $this->mapper;
        $mapper->authorsWithPosts = Filtered::comment()->post->stack(Filtered::author());
        $post = $mapper->authorsWithPosts->fetchAll();
        $post = $post[0];
        $this->assertEquals(
            (object) ['id' => '5', 'author_id' => 1, 'text' => 'Post Text', 'title' => 'Post Title'],
            $post,
        );
        $post->title = 'Title Changed';
        $post->author_id = $mapper->author[1]->fetch();
        $post->author_id->name = 'A';
        $mapper->postsFromAuthorsWithComments->persist($post);
        $mapper->flush();
        $result = $this->query('select title from post where id=5')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('Title Changed', $result->title);
        $result = $this->query('select name from author where id=1')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertNotEquals('A', $result->name);
    }

    public function testReusingRegisteredFilteredCollectionsKeepsTheirFiltering(): void
    {
        $mapper = $this->mapper;
        $mapper->commentFil = Filtered::comment();
        $mapper->author = Filtered::author();
        $post = $mapper->commentFil->post->author->fetch();
        $this->assertEquals(
            (object) ['id' => '5', 'author_id' => 1, 'text' => 'Post Text', 'title' => 'Post Title'],
            $post,
        );
        $post->title = 'Title Changed';
        $mapper->postsFromAuthorsWithComments->persist($post);
        $mapper->flush();
        $result = $this->query('select title from post where id=5')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('Title Changed', $result->title);
    }

    public function testReusingRegisteredFilteredCollectionsKeepsTheirFilteringOnFetchAll(): void
    {
        $mapper = $this->mapper;
        $mapper->commentFil = Filtered::comment();
        $mapper->author = Filtered::author();
        $post = $mapper->commentFil->post->author->fetchAll();
        $post = $post[0];
        $this->assertEquals(
            (object) ['id' => '5', 'author_id' => 1, 'text' => 'Post Text', 'title' => 'Post Title'],
            $post,
        );
        $post->title = 'Title Changed';
        $mapper->postsFromAuthorsWithComments->persist($post);
        $mapper->flush();
        $result = $this->query('select title from post where id=5')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('Title Changed', $result->title);
    }

    public function testRegisteredFilteredCollectionsByColumnKeepsTheirFiltering(): void
    {
        $mapper = $this->mapper;
        $mapper->post = Filtered::by('title')->post();
        $post = $mapper->post->fetch();
        $this->assertEquals(
            (object) ['id' => '5', 'title' => 'Post Title'],
            $post,
        );
        $post->title = 'Title Changed';
        $mapper->postsFromAuthorsWithComments->persist($post);
        $mapper->flush();
        $result = $this->query('select title from post where id=5')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('Title Changed', $result->title);
    }

    public function testRegisteredFilteredCollectionsByColumnKeepsTheirFilteringOnFetchAll(): void
    {
        $mapper = $this->mapper;
        $mapper->post = Filtered::by('title')->post();
        $post = $mapper->post->fetchAll();
        $post = $post[0];
        $this->assertEquals(
            (object) ['id' => '5', 'title' => 'Post Title'],
            $post,
        );
        $post->title = 'Title Changed';
        $mapper->postsFromAuthorsWithComments->persist($post);
        $mapper->flush();
        $result = $this->query('select title from post where id=5')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('Title Changed', $result->title);
    }

    public function testRegisteredFilteredWildcardCollectionsKeepsTheirFiltering(): void
    {
        $mapper = $this->mapper;
        $mapper->post = Filtered::by('*')->post();
        $post = $mapper->post->fetch();
        $this->assertEquals((object) ['id' => '5'], $post);
        $post->title = 'Title Changed';
        $mapper->postsFromAuthorsWithComments->persist($post);
        $mapper->flush();
        $result = $this->query('select title from post where id=5')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('Title Changed', $result->title);
    }

    public function testRegisteredFilteredWildcardCollectionsKeepsTheirFilteringOnFetchAll(): void
    {
        $mapper = $this->mapper;
        $mapper->post = Filtered::by('*')->post();
        $post = $mapper->post->fetchAll();
        $post = $post[0];
        $this->assertEquals((object) ['id' => '5'], $post);
        $post->title = 'Title Changed';
        $mapper->postsFromAuthorsWithComments->persist($post);
        $mapper->flush();
        $result = $this->query('select title from post where id=5')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('Title Changed', $result->title);
    }

    public function testFetchingRegisteredFilteredCollectionsAlongsideNormal(): void
    {
        $mapper = $this->mapper;
        $mapper->post = Filtered::by('*')->post()->author();
        $post = $mapper->post->fetchAll();
        $post = $post[0];
        $this->assertEquals(
            (object) ['id' => '5', 'author_id' => $post->author_id],
            $post,
        );
        $this->assertEquals(
            (object) ['name' => 'Author 1', 'id' => 1],
            $post->author_id,
        );
        $post->title = 'Title Changed';
        $mapper->postsFromAuthorsWithComments->persist($post);
        $mapper->flush();
        $result = $this->query('select title from post where id=5')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('Title Changed', $result->title);
    }

    public function testCompositesBringResultsFromTwoTables(): void
    {
        $mapper = $this->mapper;
        $mapper->postComment = Composite::with(['comment' => ['text']])->post()->author();
        $post = $mapper->postComment->fetch();
        $this->assertEquals(
            (object) ['name' => 'Author 1', 'id' => 1],
            $post->author_id,
        );
        $this->assertEquals(
            (object) [
                'id' => '5',
                'author_id' => $post->author_id,
                'text' => 'Comment Text',
                'title' => 'Post Title',
                'comment_id' => 7,
            ],
            $post,
        );
    }

    public function testCompositesPersistsResultsOnTwoTables(): void
    {
        $mapper = $this->mapper;
        $mapper->postComment = Composite::with(['comment' => ['text']])->post()->author();
        $post = $mapper->postComment->fetch();
        $this->assertEquals(
            (object) ['name' => 'Author 1', 'id' => 1],
            $post->author_id,
        );
        $this->assertEquals(
            (object) [
                'id' => '5',
                'author_id' => $post->author_id,
                'text' => 'Comment Text',
                'title' => 'Post Title',
                'comment_id' => 7,
            ],
            $post,
        );
        $post->title = 'Title Changed';
        $post->text = 'Comment Changed';
        $mapper->postsFromAuthorsWithComments->persist($post);
        $mapper->flush();
        $result = $this->query('select title from post where id=5')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('Title Changed', $result->title);
        $result = $this->query('select text from comment where id=7')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('Comment Changed', $result->text);
    }

    public function testCompositesPersistsNewlyCreatedEntitiesOnTwoTables(): void
    {
        $mapper = $this->mapper;
        $mapper->postComment = Composite::with(['comment' => ['text']])->post()->author();
        $post = (object) ['text' => 'Comment X', 'title' => 'Post X', 'id' => null];
        $post->author_id = (object) ['name' => 'Author X', 'id' => null];
        $mapper->postComment->persist($post);
        $mapper->flush();
        $result = $this->query(
            'select title, text from post order by id desc',
        )->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('Post X', $result->title);
        $this->assertEquals('', $result->text);
        $result = $this->query(
            'select text from comment order by id desc',
        )->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('Comment X', $result->text);
    }

    public function testCompositesPersistDoesNotDropColumnsWithMatchingValues(): void
    {
        $mapper = $this->mapper;
        $mapper->postComment = Composite::with(['comment' => ['text']])->post()->author();
        $post = $mapper->postComment->fetch();
        $post->title = 'Same Value';
        $post->text = 'Same Value';
        $mapper->postComment->persist($post);
        $mapper->flush();
        $result = $this->query('select title from post where id=5')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('Same Value', $result->title);
        $result = $this->query('select text from comment where id=7')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('Same Value', $result->text);
    }

    public function testCompositesAll(): void
    {
        $mapper = $this->mapper;
        $mapper->postComment = Composite::with(['comment' => ['text']])->post()->author();
        $post = $mapper->postComment->fetchAll();
        $post = $post[0];
        $this->assertEquals(
            (object) ['name' => 'Author 1', 'id' => 1],
            $post->author_id,
        );
        $this->assertEquals(
            (object) [
                'id' => '5',
                'author_id' => $post->author_id,
                'text' => 'Comment Text',
                'title' => 'Post Title',
                'comment_id' => 7,
            ],
            $post,
        );
        $post->title = 'Title Changed';
        $post->text = 'Comment Changed';
        $mapper->postsFromAuthorsWithComments->persist($post);
        $mapper->flush();
        $result = $this->query('select title from post where id=5')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('Title Changed', $result->title);
        $result = $this->query('select text from comment where id=7')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('Comment Changed', $result->text);
    }

    public function testTyped(): void
    {
        $mapper = new Mapper($this->conn, new EntityFactory(entityNamespace: '\Respect\Relational\\'));
        $mapper->typedIssues = Typed::by('type')->issues();
        $issues = $mapper->typedIssues->fetchAll();
        $this->assertInstanceOf('\\Respect\Relational\\Bug', $issues[0]);
        $this->assertInstanceOf('\\Respect\Relational\\Improvement', $issues[1]);
        $this->assertEquals((array) $this->issues[0], (array) $issues[0]);
        $this->assertEquals((array) $this->issues[1], (array) $issues[1]);
        $issues[0]->title = 'Title Changed';
        $mapper->typedIssues->persist($issues[0]);
        $mapper->flush();
        $result = $this->query('select title from issues where id=1')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('Title Changed', $result->title);
    }

    public function testTypedSingle(): void
    {
        $mapper = new Mapper($this->conn, new EntityFactory(entityNamespace: '\Respect\Relational\\'));
        $mapper->typedIssues = Typed::by('type')->issues();
        $issue = $mapper->typedIssues->fetch();
        $this->assertInstanceOf('\\Respect\Relational\\Bug', $issue);
        $this->assertEquals((array) $this->issues[0], (array) $issue);
        $issue->title = 'Title Changed';
        $mapper->typedIssues->persist($issue);
        $mapper->flush();
        $result = $this->query('select title from issues where id=1')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('Title Changed', $result->title);
    }

    public function testPersistNewWithArrayobject(): void
    {
        $mapper = $this->mapper;
        $arrayEntity = ['id' => 10, 'name' => 'array_object_category', 'category_id' => null];
        $entity = (object) $arrayEntity;
        $mapper->category->persist($entity);
        $mapper->flush();
        $result = $this->query('select * from category where id=10')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('array_object_category', $result->name);
    }

    public function testFetchingEntityWithoutPublicPropertiesTyped(): void
    {
        $mapper = new Mapper($this->conn, new EntityFactory(entityNamespace: '\Respect\Relational\OtherEntity\\'));
        $post = $mapper->post[5]->fetch();
        $this->assertInstanceOf('\Respect\Relational\OtherEntity\Post', $post);
    }

    public function testFetchingAllEntityWithoutPublicPropertiesTyped(): void
    {
        $mapper = new Mapper($this->conn, new EntityFactory(entityNamespace: '\Respect\Relational\OtherEntity\\'));
        $posts = $mapper->post->fetchAll();
        $this->assertInstanceOf('\Respect\Relational\OtherEntity\Post', $posts[0]);
    }

    public function testFetchingAllEntityWithoutPublicPropertiesTypedNested(): void
    {
        $mapper = new Mapper($this->conn, new EntityFactory(entityNamespace: '\Respect\Relational\OtherEntity\\'));
        $posts = $mapper->post->author->fetchAll();
        $this->assertInstanceOf('\Respect\Relational\OtherEntity\Post', $posts[0]);
        $this->assertInstanceOf(
            '\Respect\Relational\OtherEntity\Author',
            $posts[0]->getAuthor(),
        );
    }

    public function testPersistingEntityWithoutPublicPropertiesTyped(): void
    {
        $mapper = new Mapper($this->conn, new EntityFactory(entityNamespace: '\Respect\Relational\OtherEntity\\'));

        $post = $mapper->post[5]->fetch();
        $post->setText('HeyHey');

        $mapper->post->persist($post);
        $mapper->flush();
        $result = $this->query('select text from post where id=5')
            ->fetchColumn(0);
        $this->assertEquals('HeyHey', $result);
    }

    public function testPersistingNewEntityWithoutPublicPropertiesTyped(): void
    {
        $mapper = new Mapper($this->conn, new EntityFactory(entityNamespace: '\Respect\Relational\OtherEntity\\'));

        $author = new OtherEntity\Author();
        $author->setId(1);
        $author->setName('Author 1');

        $post = new OtherEntity\Post();
        $post->setAuthor($author);
        $post->setTitle('My New Post Title');
        $post->setText('My new Post Text');
        $mapper->post->persist($post);
        $mapper->flush();
        $result = $this->query('select text from post where id=6')
            ->fetchColumn(0);
        $this->assertEquals('My new Post Text', $result);
    }

    public function testShouldExecuteEntityConstructorByDefault(): void
    {
        $mapper = new Mapper($this->conn, new EntityFactory(entityNamespace: 'Respect\\Relational\\OtherEntity\\'));

        try {
            $mapper->comment->fetch();
            $this->fail('This should throws exception');
        } catch (DomainException $e) {
            $this->assertEquals('Exception from __construct', $e->getMessage());
        }
    }

    public function testShouldNotExecuteEntityConstructorWhenDisabled(): void
    {
        $mapper = new Mapper($this->conn, new EntityFactory(
            entityNamespace: 'Respect\\Relational\\OtherEntity\\',
            disableConstructor: true,
        ));

        $this->assertInstanceOf(
            'Respect\\Relational\\OtherEntity\\Comment',
            $mapper->comment->fetch(),
        );
    }

    public function testFetchWithConditionUsingColumnValue(): void
    {
        $mapper = $this->mapper;
        $comments = $mapper->comment(['post_id' => 5])->fetchAll();
        $this->assertCount(1, $comments);
    }

    public function testPersistNewEntityWithNoAutoIncrementId(): void
    {
        $conn = $this->createStub(PDO::class);
        $conn->method('getAttribute')
            ->willReturn('sqlite');
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')
            ->willReturn(true);
        $conn->method('prepare')
            ->willReturn($stmt);
        $conn->method('lastInsertId')
            ->willReturn('0');
        $conn->method('beginTransaction')
            ->willReturn(true);
        $conn->method('commit')
            ->willReturn(true);
        $mapper = new Mapper($conn);
        $obj = new stdClass();
        $obj->id = null;
        $obj->name = 'test';
        $mapper->foo->persist($obj);
        $mapper->flush();
        $this->assertNull($obj->id);
    }

    public function testFetchReturnsDbInstance(): void
    {
        $db = new Db($this->conn);
        $mapper = new Mapper($db);
        $this->assertInstanceOf(Db::class, $mapper->db);
    }

    private function query(string $sql): PDOStatement
    {
        $stmt = $this->conn->query($sql);
        self::assertInstanceOf(PDOStatement::class, $stmt);

        return $stmt;
    }
}
