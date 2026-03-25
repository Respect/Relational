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
use Throwable;
use TypeError;

use function array_keys;
use function array_values;
use function count;
use function current;
use function date;

#[CoversClass(Mapper::class)]
class MapperTest extends TestCase
{
    protected PDO $conn;

    protected Mapper $mapper;

    /** @var list<array<string, mixed>> */
    protected array $posts;

    /** @var list<array<string, mixed>> */
    protected array $authors;

    /** @var list<array<string, mixed>> */
    protected array $comments;

    /** @var list<array<string, mixed>> */
    protected array $categories;

    /** @var list<array<string, mixed>> */
    protected array $postsCategories;

    /** @var list<array<string, mixed>> */
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
            ['id' => 5, 'title' => 'Post Title', 'text' => 'Post Text', 'author_id' => 1],
        ];
        $this->authors = [
            ['id' => 1, 'name' => 'Author 1'],
        ];
        $this->comments = [
            ['id' => 7, 'post_id' => 5, 'text' => 'Comment Text', 'datetime' => '2012-06-19 00:35:42'],
            ['id' => 8, 'post_id' => 4, 'text' => 'Comment Text 2', 'datetime' => '2012-06-19 00:35:42'],
        ];
        $this->categories = [
            ['id' => 2, 'name' => 'Sample Category', 'category_id' => null],
            ['id' => 3, 'name' => 'NONON', 'category_id' => null],
        ];
        $this->postsCategories = [
            ['id' => 66, 'post_id' => 5, 'category_id' => 2],
        ];
        $this->issues = [
            ['id' => 1, 'type' => 'bug', 'title' => 'Bug 1'],
            ['id' => 2, 'type' => 'improvement', 'title' => 'Improvement 1'],
        ];

        foreach ($this->authors as $row) {
            $db->insertInto('author', array_keys($row))->values(array_values($row))->exec();
        }

        foreach ($this->posts as $row) {
            $db->insertInto('post', array_keys($row))->values(array_values($row))->exec();
        }

        foreach ($this->comments as $row) {
            $db->insertInto('comment', array_keys($row))->values(array_values($row))->exec();
        }

        foreach ($this->categories as $row) {
            $db->insertInto('category', array_keys($row))->values(array_values($row))->exec();
        }

        foreach ($this->postsCategories as $row) {
            $db->insertInto('post_category', array_keys($row))->values(array_values($row))->exec();
        }

        foreach ($this->issues as $row) {
            $db->insertInto('issues', array_keys($row))->values(array_values($row))->exec();
        }

        $mapper = new Mapper($conn, new EntityFactory(entityNamespace: 'Respect\\Relational\\'));
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
        $mapper = new Mapper($conn, new EntityFactory(entityNamespace: 'Respect\\Relational\\'));
        $obj = new Post();
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
        $mapper = new Mapper($conn, new EntityFactory(entityNamespace: 'Respect\\Relational\\'));
        $obj = new Author();
        $obj->name = 'bar';
        $mapper->author->persist($obj);
        $mapper->flush();
        $this->assertNull($obj->id);
        $this->assertEquals('bar', $obj->name);
    }

    public function testRemovingUntrackedObject(): void
    {
        $comment = new Comment();
        $comment->id = 7;
        $this->assertNotEmpty($this->mapper->comment[7]->fetch());
        $this->mapper->comment->remove($comment);
        $this->mapper->flush();
        $this->assertEmpty($this->mapper->comment[7]->fetch());
    }

    public function testFetchingSingleEntityFromCollectionShouldReturnFirstRecordFromTable(): void
    {
        $fetched = $this->mapper->comment->fetch();
        $this->assertEquals(7, $fetched->id);
        $this->assertEquals('Comment Text', $fetched->text);
    }

    public function testFetchingAllEntitesFromCollectionShouldReturnAllRecords(): void
    {
        $fetched = $this->mapper->category->fetchAll();
        $this->assertCount(2, $fetched);
        $this->assertEquals(2, $fetched[0]->id);
        $this->assertEquals('Sample Category', $fetched[0]->name);
        $this->assertEquals(3, $fetched[1]->id);
    }

    public function testExtraSqlOnSingleFetchShouldBeAppliedOnMapperSql(): void
    {
        $fetchedLast = $this->mapper->comment->fetch(Sql::orderBy('id DESC'));
        $this->assertEquals(8, $fetchedLast->id);
        $this->assertEquals('Comment Text 2', $fetchedLast->text);
    }

    public function testExtraSqlOnFetchAllShouldBeAppliedOnMapperSql(): void
    {
        $fetchedComments = $this->mapper->comment->fetchAll(Sql::orderBy('id DESC'));
        $this->assertCount(2, $fetchedComments);
        $this->assertEquals(8, $fetchedComments[0]->id);
        $this->assertEquals(7, $fetchedComments[1]->id);
    }

    public function testMultipleConditionsAcrossCollectionsProduceAndClause(): void
    {
        $mapper = $this->mapper;
        $comment = $mapper->comment[7]->post[5]->fetch();
        $this->assertEquals(7, $comment->id);
        $this->assertEquals(5, $comment->post->id);
        $this->assertEquals('Post Title', $comment->post->title);
    }

    public function testNestedCollectionsShouldHydrateResults(): void
    {
        $mapper = $this->mapper;
        $comment = $mapper->comment->post[5]->fetch();
        $this->assertEquals(7, $comment->id);
        $this->assertEquals('Comment Text', $comment->text);
        $this->assertEquals(5, $comment->post->id);
        $this->assertEquals('Post Title', $comment->post->title);
        $this->assertEquals('Post Text', $comment->post->text);
    }

    public function testOneToN(): void
    {
        $mapper = $this->mapper;
        $comments = $mapper->comment->post($mapper->author)->fetchAll();
        $comment = current($comments);
        $this->assertEquals(1, count($comments));
        $this->assertEquals(7, $comment->id);
        $this->assertEquals('Comment Text', $comment->text);
        $this->assertEquals(5, $comment->post->id);
        $this->assertEquals('Post Title', $comment->post->title);
        $this->assertEquals('Post Text', $comment->post->text);
        $this->assertEquals(1, $comment->post->author->id);
        $this->assertEquals('Author 1', $comment->post->author->name);
    }

    public function testNtoN(): void
    {
        $mapper = $this->mapper;
        $comments = $mapper->comment->post->post_category->category[2]->fetchAll();
        $comment = current($comments);
        $this->assertEquals(1, count($comments));
        $this->assertEquals(7, $comment->id);
        $this->assertEquals('Comment Text', $comment->text);
        $this->assertEquals(5, $comment->post->id);
        $this->assertEquals('Post Title', $comment->post->title);
        $this->assertEquals('Post Text', $comment->post->text);
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
        $entity = new Category();
        $entity->id = 4;
        $entity->name = 'inserted';
        $mapper->category->persist($entity);
        $mapper->flush();
        $result = $this->query('select * from category where id=4')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertEquals(4, $result->id);
        $this->assertEquals('inserted', $result->name);
    }

    public function testNestedPersistCollection(): void
    {
        $author = new Author();
        $author->name = 'New';
        $postWithAuthor = new Post();
        $postWithAuthor->title = 'hi';
        $postWithAuthor->text = 'hi text';
        $postWithAuthor->author = $author;
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
        $author = new Author();
        $author->name = 'New';
        $postWithAuthor = new Post();
        $postWithAuthor->title = 'hi';
        $postWithAuthor->text = 'hi text';
        $postWithAuthor->author = $author;
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
        $author = new Author();
        $author->name = 'New';
        $postWithAuthor = new Post();
        $postWithAuthor->title = 'hi';
        $postWithAuthor->text = 'hi text';
        $postWithAuthor->author = $author;
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
        $entity = new Category();
        $entity->id = 8;
        $entity->name = 'inserted';
        $entity->category_id = 2;
        $mapper->category->persist($entity);
        $mapper->flush();
        $result = $this->query('select * from category where id=8')
            ->fetch(PDO::FETCH_OBJ);
        $result2 = $mapper->category[8]->category->fetch();
        $this->assertEquals($result->id, $result2->id);
        $this->assertEquals($result->name, $result2->name);
        $this->assertEquals(8, $result->id);
        $this->assertEquals('inserted', $result->name);
    }

    public function testSubCategoryCondition(): void
    {
        $mapper = $this->mapper;
        $entity = new Category();
        $entity->id = 8;
        $entity->name = 'inserted';
        $entity->category_id = 2;
        $mapper->category->persist($entity);
        $mapper->flush();
        $result = $this->query('select * from category where id=8')
            ->fetch(PDO::FETCH_OBJ);
        $result2 = $mapper->category(['id' => 8])->category->fetch();
        $this->assertEquals($result->id, $result2->id);
        $this->assertEquals($result->name, $result2->name);
        $this->assertEquals(8, $result->id);
        $this->assertEquals('inserted', $result->name);
    }

    public function testAutoIncrementPersist(): void
    {
        $mapper = $this->mapper;
        $entity = new Category();
        $entity->name = 'inserted';
        $mapper->category->persist($entity);
        $mapper->flush();
        $result = $this->query(
            'select * from category where name="inserted"',
        )->fetch(PDO::FETCH_OBJ);
        $this->assertEquals(4, $result->id);
        $this->assertEquals('inserted', $result->name);
    }

    public function testPassedIdentity(): void
    {
        $mapper = $this->mapper;

        $post = new Post();
        $post->title = '12345';
        $post->text = 'text abc';

        $comment = new Comment();
        $comment->post = $post;
        $comment->text = 'abc';

        $mapper->post->persist($post);
        $mapper->comment->persist($comment);
        $mapper->flush();

        $postId = $this->query('select id from post where title = 12345')
            ->fetchColumn(0);

        $row = $this->query('select * from comment where post_id = ' . $postId)
            ->fetch(PDO::FETCH_ASSOC);

        self::assertIsArray($row);
        $this->assertEquals('abc', $row['text']);
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
        $this->assertInstanceOf('\Respect\Relational\Post', $comment[0]->post);
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
            $factory = new EntityFactory(style: $style, entityNamespace: 'Respect\\Relational\\');
            $mapper = new Mapper($this->conn, $factory);
            $this->assertEquals($style, $mapper->style);
        }
    }

    public function testFetchingaSingleFilteredCollectionShouldNotBringFilteredChildren(): void
    {
        $mapper = $this->mapper;
        $mapper->authorsWithPosts = Filtered::post()->author();
        $author = $mapper->authorsWithPosts->fetch();
        $this->assertEquals(1, $author->id);
        $this->assertEquals('Author 1', $author->name);
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
        $author = new Author();
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
        $author = new Author();
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
        $this->assertCount(1, $authors);
        $this->assertEquals(1, $authors[0]->id);
        $this->assertEquals('Author 1', $authors[0]->name);
    }

    public function testFilteredCollectionsShouldHydrateNonFilteredPartsAsUsual(): void
    {
        $mapper = $this->mapper;
        $mapper->postsFromAuthorsWithComments = Filtered::comment()->post()->author();
        $post = $mapper->postsFromAuthorsWithComments->fetch();
        $this->assertInstanceOf(Post::class, $post);
        $this->assertEquals(5, $post->id);
        $this->assertEquals('Post Title', $post->title);
        $this->assertInstanceOf(Author::class, $post->author);
        $this->assertEquals(1, $post->author->id);
        $this->assertEquals('Author 1', $post->author->name);
    }

    public function testFilteredCollectionsShouldPersistHydratedNonFilteredPartsAsUsual(): void
    {
        $mapper = $this->mapper;
        $mapper->postsFromAuthorsWithComments = Filtered::comment()->post()->author();
        $post = $mapper->postsFromAuthorsWithComments->fetch();
        $this->assertInstanceOf(Post::class, $post);
        $this->assertEquals(5, $post->id);
        $this->assertInstanceOf(Author::class, $post->author);
        $post->title = 'Title Changed';
        $post->author->name = 'John';
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
        $this->assertEquals(5, $post->id);
        $this->assertEquals('Post Title', $post->title);
        $post->title = 'Title Changed';
        $post->author = $mapper->author[1]->fetch();
        $post->author->name = 'A';
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
        $this->assertEquals(5, $post->id);
        $this->assertEquals('Post Title', $post->title);
        $post->title = 'Title Changed';
        $newAuthor = new Author();
        $newAuthor->name = 'A';
        $post->author = $newAuthor;
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
        $this->assertEquals(5, $post->id);
        $this->assertEquals('Post Title', $post->title);
        $post->title = 'Title Changed';
        $post->author = $mapper->author[1]->fetch();
        $post->author->name = 'A';
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
        $this->assertEquals(5, $post->id);
        $this->assertEquals('Post Title', $post->title);
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
        $mapper->post = Filtered::post('title');
        $post = $mapper->post->fetch();
        $this->assertEquals(5, $post->id);
        $this->assertEquals('Post Title', $post->title);
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
        $mapper->post = Filtered::post('*');
        $post = $mapper->post->fetch();
        $this->assertEquals(5, $post->id);
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
        $mapper->post = Filtered::post('*')->author();
        $post = $mapper->post->fetchAll();
        $post = $post[0];
        $this->assertInstanceOf(Post::class, $post);
        $this->assertEquals(5, $post->id);
        $this->assertInstanceOf(Author::class, $post->author);
        $this->assertEquals(1, $post->author->id);
        $this->assertEquals('Author 1', $post->author->name);
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
        $mapper->postComment = Composite::post(['comment' => ['text']])->author();
        $post = $mapper->postComment->fetch();
        $this->assertEquals(1, $post->author->id);
        $this->assertEquals('Author 1', $post->author->name);
        $this->assertEquals(5, $post->id);
        $this->assertEquals('Post Title', $post->title);
        $this->assertEquals('Comment Text', $post->text);
    }

    public function testCompositesPersistsResultsOnTwoTables(): void
    {
        $mapper = $this->mapper;
        $mapper->postComment = Composite::post(['comment' => ['text']])->author();
        $post = $mapper->postComment->fetch();
        $this->assertEquals(1, $post->author->id);
        $this->assertEquals(5, $post->id);
        $this->assertEquals('Post Title', $post->title);
        $this->assertEquals('Comment Text', $post->text);
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
        $mapper->postComment = Composite::post(['comment' => ['text']])->author();
        $post = new Post();
        $post->text = 'Comment X';
        $post->title = 'Post X';
        $authorX = new Author();
        $authorX->name = 'Author X';
        $post->author = $authorX;
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
        $mapper->postComment = Composite::post(['comment' => ['text']])->author();
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

    public function testTyped(): void
    {
        $mapper = new Mapper($this->conn, new EntityFactory(entityNamespace: '\Respect\Relational\\'));
        $mapper->typedIssues = Typed::issues('type');
        $issues = $mapper->typedIssues->fetchAll();
        $this->assertInstanceOf('\\Respect\Relational\\Bug', $issues[0]);
        $this->assertInstanceOf('\\Respect\Relational\\Improvement', $issues[1]);
        $this->assertEquals(1, $issues[0]->id);
        $this->assertEquals('bug', $issues[0]->type);
        $this->assertEquals('Bug 1', $issues[0]->title);
        $this->assertEquals(2, $issues[1]->id);
        $this->assertEquals('improvement', $issues[1]->type);
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
        $mapper->typedIssues = Typed::issues('type');
        $issue = $mapper->typedIssues->fetch();
        $this->assertInstanceOf('\\Respect\Relational\\Bug', $issue);
        $this->assertEquals(1, $issue->id);
        $this->assertEquals('bug', $issue->type);
        $this->assertEquals('Bug 1', $issue->title);
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
        $entity = new Category();
        $entity->id = 10;
        $entity->name = 'array_object_category';
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
        $mapper = new Mapper($conn, new EntityFactory(entityNamespace: 'Respect\\Relational\\'));
        $obj = new Author();
        $obj->name = 'test';
        $mapper->author->persist($obj);
        $mapper->flush();
        $this->assertNull($obj->id);
    }

    public function testFetchReturnsDbInstance(): void
    {
        $db = new Db($this->conn);
        $mapper = new Mapper($db);
        $this->assertInstanceOf(Db::class, $mapper->db);
    }

    public function testFilteredPersistUpdatesOnlyFilteredColumns(): void
    {
        $mapper = $this->mapper;
        $mapper->postTitles = Filtered::post('title');
        $post = $mapper->postTitles()->fetch();
        $this->assertEquals('Post Title', $post->title);

        $post->title = 'Changed Title';
        $mapper->postTitles()->persist($post);
        $mapper->flush();

        $row = $this->query('select * from post where id=5')->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('Changed Title', $row['title']);
        $this->assertEquals('Post Text', $row['text'], 'Non-filtered columns should remain unchanged');
        $this->assertEquals(1, $row['author_id'], 'Non-filtered columns should remain unchanged');
    }

    public function testFilteredPersistInsertsOnlyFilteredColumns(): void
    {
        $mapper = $this->mapper;
        $mapper->postTitles = Filtered::post('title');
        $post = new Post();
        $post->id = 99;
        $post->title = 'Partial Post';
        $post->text = 'Should not appear';
        $mapper->postTitles()->persist($post);
        $mapper->flush();

        $row = $this->query('select * from post where id=99')->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('Partial Post', $row['title']);
        $this->assertNull($row['text'], 'Non-filtered columns should not be inserted');
    }

    /** Identity Map: fetch() short-circuit */
    public function testFetchReturnsSameInstanceOnRepeatedPkLookup(): void
    {
        $first = $this->mapper->post(5)->fetch();
        $second = $this->mapper->post(5)->fetch();

        $this->assertSame($first, $second);
    }

    public function testFetchWithExtraBypassesIdentityMap(): void
    {
        $first = $this->mapper->post(5)->fetch();
        $extra = new Sql();
        $extra->orderBy('post.id');
        $second = $this->mapper->post(5)->fetch($extra);

        $this->assertNotSame($first, $second);
        $this->assertEquals($first->id, $second->id);
    }

    public function testIdentityMapCountIncreasesOnFetch(): void
    {
        $this->assertSame(0, $this->mapper->identityMapCount());

        $this->mapper->author(1)->fetch();

        $this->assertGreaterThan(0, $this->mapper->identityMapCount());
    }

    public function testClearIdentityMapForcesFreshFetch(): void
    {
        $first = $this->mapper->post(5)->fetch();
        $this->mapper->clearIdentityMap();
        $second = $this->mapper->post(5)->fetch();

        $this->assertNotSame($first, $second);
        $this->assertEquals($first->id, $second->id);
    }

    /** Identity Map: flushSingle() insert/update/delete */
    public function testInsertedEntityIsRetrievableFromIdentityMap(): void
    {
        $entity = new Post();
        $entity->title = 'New Post';
        $entity->text = 'New Text';

        $this->mapper->post->persist($entity);
        $this->mapper->flush();

        // The entity should now have an auto-assigned id and be cached
        $this->assertNotNull($entity->id);

        $fetched = $this->mapper->post($entity->id)->fetch();
        $this->assertSame($entity, $fetched);
    }

    public function testUpdatedEntityKeepsReturningUpdatedInstance(): void
    {
        $entity = $this->mapper->post(5)->fetch();
        $entity->title = 'Updated Title';

        $this->mapper->post->persist($entity);
        $this->mapper->flush();

        $fetched = $this->mapper->post(5)->fetch();
        $this->assertSame($entity, $fetched);
        $this->assertSame('Updated Title', $fetched->title);
    }

    public function testDeletedEntityIsEvictedFromIdentityMap(): void
    {
        $entity = $this->mapper->post(5)->fetch();
        $this->assertSame($entity, $this->mapper->post(5)->fetch());

        $this->mapper->post->remove($entity);
        $this->mapper->flush();

        // After delete, fetch should hit DB (and return false since the row is gone)
        $result = $this->mapper->post(5)->fetch();
        $this->assertFalse($result);
    }

    /** Identity Map: parseHydrated() registers related entities */
    public function testRelatedEntityFromJoinReturnsSameInstanceOnDirectFetch(): void
    {
        // Fetch a comment with its related post via join
        $comment = $this->mapper->comment(7)->post->fetch();

        // The related post entity should have been registered in the identity map
        $post = $this->mapper->post(5)->fetch();
        $this->assertSame($comment->post->id, $post->id);

        // They should be the same object instance since parseHydrated()
        // registers all entities (including nested ones) in the identity map
        $this->assertSame($post, $this->mapper->post($post->id)->fetch());
    }

    public function testNestedRelatedEntitiesAllRegisteredInIdentityMap(): void
    {
        $this->mapper->comment(7)->post->author->fetch();

        $postFromMap = $this->mapper->post(5)->fetch();
        $authorFromMap = $this->mapper->author(1)->fetch();

        $this->assertSame($postFromMap, $this->mapper->post(5)->fetch());
        $this->assertSame($authorFromMap, $this->mapper->author(1)->fetch());
    }

    public function testChildCollectionLeftJoinWiresMatchingAuthor(): void
    {
        $posts = $this->mapper->post($this->mapper->author)->fetchAll();
        $this->assertNotEmpty($posts);
        $this->assertInstanceOf(Post::class, $posts[0]);
        $this->assertInstanceOf(Author::class, $posts[0]->author);
        $this->assertEquals(1, $posts[0]->author->id);
    }

    public function testChildCollectionLeftJoinLeavesRelationNullOnMiss(): void
    {
        $db = new Db($this->conn);
        $db->insertInto('post', ['id', 'title', 'text', 'author_id'])
            ->values([99, 'Orphan Post', 'No author', 999])
            ->exec();

        $posts = $this->mapper->post($this->mapper->author)->fetchAll();
        $orphan = null;
        foreach ($posts as $p) {
            if ($p->id != 99) {
                continue;
            }

            $orphan = $p;
        }

        $this->assertInstanceOf(Post::class, $orphan);
        $this->assertEquals('Orphan Post', $orphan->title);
        $this->assertNull($this->mapper->entityFactory->get($orphan, 'author'));
    }

    public function testFetchReturnsFalseForNonExistentRow(): void
    {
        $result = $this->mapper->post(9999)->fetch();
        $this->assertFalse($result);
    }

    public function testPersistPureEntityTreeDerivesForeignKey(): void
    {
        $author = $this->mapper->author(1)->fetch();

        $post = new Post();
        $post->title = 'Pure Tree';
        $post->text = 'No author_id property';
        $this->mapper->entityFactory->set($post, 'author', $author);

        $this->mapper->post->persist($post);
        $this->mapper->flush();

        $row = $this->query('select * from post where title = "Pure Tree"')
            ->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row);
        $this->assertEquals(1, $row['author_id']);
    }

    private function query(string $sql): PDOStatement
    {
        $stmt = $this->conn->query($sql);
        self::assertInstanceOf(PDOStatement::class, $stmt);

        return $stmt;
    }
}
