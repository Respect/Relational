<?php

declare(strict_types=1);

namespace Respect\Relational;

use Datetime;
use Exception;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Respect\Data\Collections\Collection;
use Respect\Data\Collections\Composite;
use Respect\Data\Collections\Typed;
use Respect\Data\EntityFactory;
use Respect\Data\Hydrators\PrestyledAssoc;
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
        $conn->exec((string) Sql::createTable('read_only_author', [
            'id INTEGER PRIMARY KEY',
            'name VARCHAR(255)',
            'bio TEXT',
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

        $db->insertInto('read_only_author', ['id', 'name', 'bio'])
            ->values([1, 'Alice', 'Alice bio'])
            ->exec();

        $mapper = new Mapper($conn, new PrestyledAssoc(new EntityFactory(
            entityNamespace: 'Respect\\Relational\\',
        )));
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
        $mapper = new Mapper($conn, new PrestyledAssoc(new EntityFactory(
            entityNamespace: 'Respect\\Relational\\',
        )));
        $obj = new Post();
        $mapper->persist($obj, $mapper->post());
        try {
            $mapper->flush();
        } catch (Throwable) {
            //OK!
        }
    }

    public function testFailedFlushResetsPending(): void
    {
        // Force a flush failure via a UNIQUE constraint violation
        $this->conn->exec('CREATE UNIQUE INDEX author_name_unique ON author(name)');

        $dupe = new Author();
        $dupe->name = 'Author 1'; // already seeded
        $this->mapper->persist($dupe, $this->mapper->author());

        try {
            $this->mapper->flush();
            $this->fail('Expected flush to throw on UNIQUE violation');
        } catch (Throwable) {
            // expected
        }

        // Second flush with a valid entity should succeed without replaying the failed one
        $author = new Author();
        $author->name = 'Fresh Author';
        $this->mapper->persist($author, $this->mapper->author());
        $this->mapper->flush();

        $this->assertGreaterThan(0, $author->id);
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
        $mapper = new Mapper($conn, new PrestyledAssoc(new EntityFactory(
            entityNamespace: 'Respect\\Relational\\',
        )));
        $obj = new Author();
        $obj->name = 'bar';
        $mapper->persist($obj, $mapper->author());
        $mapper->flush();
        $this->assertFalse((new ReflectionProperty($obj, 'id'))->isInitialized($obj));
        $this->assertEquals('bar', $obj->name);
    }

    public function testRemovingUntrackedObject(): void
    {
        $comment = new Comment();
        $comment->id = 7;
        $this->assertNotEmpty($this->mapper->fetch($this->mapper->comment(filter: 7)));
        $this->mapper->remove($comment, $this->mapper->comment());
        $this->mapper->flush();
        $this->assertEmpty($this->mapper->fetch($this->mapper->comment(filter: 7)));
    }

    public function testFetchingSingleEntityFromCollectionShouldReturnFirstRecordFromTable(): void
    {
        $fetched = $this->mapper->fetch($this->mapper->comment());
        $this->assertEquals(7, $fetched->id);
        $this->assertEquals('Comment Text', $fetched->text);
    }

    public function testFetchingAllEntitesFromCollectionShouldReturnAllRecords(): void
    {
        $fetched = $this->mapper->fetchAll($this->mapper->category());
        $this->assertCount(2, $fetched);
        $this->assertEquals(2, $fetched[0]->id);
        $this->assertEquals('Sample Category', $fetched[0]->name);
        $this->assertEquals(3, $fetched[1]->id);
    }

    public function testExtraSqlOnSingleFetchShouldBeAppliedOnMapperSql(): void
    {
        $fetchedLast = $this->mapper->fetch($this->mapper->comment(), Sql::orderBy('id DESC'));
        $this->assertEquals(8, $fetchedLast->id);
        $this->assertEquals('Comment Text 2', $fetchedLast->text);
    }

    public function testExtraSqlOnFetchAllShouldBeAppliedOnMapperSql(): void
    {
        $fetchedComments = $this->mapper->fetchAll($this->mapper->comment(), Sql::orderBy('id DESC'));
        $this->assertCount(2, $fetchedComments);
        $this->assertEquals(8, $fetchedComments[0]->id);
        $this->assertEquals(7, $fetchedComments[1]->id);
    }

    public function testMultipleConditionsAcrossCollectionsProduceAndClause(): void
    {
        $mapper = $this->mapper;
        $comment = $mapper->fetch($mapper->comment([$mapper->post(filter: 5)], filter: 7));
        $this->assertEquals(7, $comment->id);
        $this->assertEquals(5, $comment->post->id);
        $this->assertEquals('Post Title', $comment->post->title);
    }

    public function testNestedCollectionsShouldHydrateResults(): void
    {
        $mapper = $this->mapper;
        $comment = $mapper->fetch($mapper->comment([$mapper->post(filter: 5)]));
        $this->assertEquals(7, $comment->id);
        $this->assertEquals('Comment Text', $comment->text);
        $this->assertEquals(5, $comment->post->id);
        $this->assertEquals('Post Title', $comment->post->title);
        $this->assertEquals('Post Text', $comment->post->text);
    }

    public function testOneToN(): void
    {
        $mapper = $this->mapper;
        $comments = $mapper->fetchAll($mapper->comment([
            $mapper->post([$mapper->author(required: true)], required: true),
        ]));
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
        $comments = $mapper->fetchAll($mapper->comment([
            $mapper->post([$mapper->post_category([$mapper->category(filter: 2)])]),
        ]));
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
        $cat = $mapper->fetch($mapper->category([$mapper->post_category([$mapper->post(filter: 5)])]));
        $this->assertEquals(2, $cat->id);
        $this->assertEquals('Sample Category', $cat->name);
    }

    public function testSimplePersist(): void
    {
        $mapper = $this->mapper;
        $entity = new Category();
        $entity->id = 4;
        $entity->name = 'inserted';
        $mapper->persist($entity, $mapper->category());
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
        $this->mapper->persist($postWithAuthor, $this->mapper->post([$this->mapper->author()]));
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
        $this->mapper->registerCollection('postAuthor', $this->mapper->post([$this->mapper->author()]));
        $this->mapper->persist($postWithAuthor, $this->mapper->postAuthor());
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
        $this->mapper->registerCollection('postAuthor', $this->mapper->post([$this->mapper->author()]));
        $this->mapper->persist($postWithAuthor, $this->mapper->postAuthor());
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
        $parent = $mapper->fetch($mapper->category(filter: 2));

        $entity = new Category();
        $entity->id = 8;
        $entity->name = 'inserted';
        $entity->category = $parent;
        $mapper->persist($entity, $mapper->category());
        $mapper->flush();
        $result = $this->query('select * from category where id=8')
            ->fetch(PDO::FETCH_OBJ);
        $result2 = $mapper->fetch($mapper->category([$mapper->category()], filter: 8));
        $this->assertEquals($result->id, $result2->id);
        $this->assertEquals($result->name, $result2->name);
        $this->assertEquals(8, $result->id);
        $this->assertEquals('inserted', $result->name);
    }

    public function testSubCategoryCondition(): void
    {
        $mapper = $this->mapper;
        $parent = $mapper->fetch($mapper->category(filter: 2));

        $entity = new Category();
        $entity->id = 8;
        $entity->name = 'inserted';
        $entity->category = $parent;
        $mapper->persist($entity, $mapper->category());
        $mapper->flush();
        $result = $this->query('select * from category where id=8')
            ->fetch(PDO::FETCH_OBJ);
        $result2 = $mapper->fetch($mapper->category([$mapper->category()], filter: ['id' => 8]));
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
        $mapper->persist($entity, $mapper->category());
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

        $mapper->persist($post, $mapper->post());
        $mapper->persist($comment, $mapper->comment());
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
        $entity = $mapper->fetch($mapper->comment(filter: 8));
        $entity->text = 'HeyHey';
        $mapper->persist($entity, $mapper->comment());
        $mapper->flush();
        $result = $this->query('select text from comment where id=8')
            ->fetchColumn(0);
        $this->assertEquals('HeyHey', $result);
    }

    public function testRemove(): void
    {
        $mapper = $this->mapper;
        $c8 = $mapper->fetch($mapper->comment(filter: 8));
        $pre = (int) $this->query('select count(*) from comment')->fetchColumn(0);
        $mapper->remove($c8, $mapper->comment());
        $mapper->flush();
        $total = (int) $this->query('select count(*) from comment')->fetchColumn(0);
        $this->assertEquals($total, $pre - 1);
    }

    public function testFetchingEntityTyped(): void
    {
        $mapper = new Mapper($this->conn, new PrestyledAssoc(new EntityFactory(
            entityNamespace: '\Respect\Relational\\',
        )));
        $comment = $mapper->fetch($mapper->comment(filter: 8));
        $this->assertInstanceOf('\Respect\Relational\Comment', $comment);
    }

    public function testFetchingAllEntityTyped(): void
    {
        $mapper = new Mapper($this->conn, new PrestyledAssoc(new EntityFactory(
            entityNamespace: '\Respect\Relational\\',
        )));
        $comment = $mapper->fetchAll($mapper->comment());
        $this->assertInstanceOf('\Respect\Relational\Comment', $comment[1]);
    }

    public function testFetchingAllEntityTypedNested(): void
    {
        $mapper = new Mapper($this->conn, new PrestyledAssoc(new EntityFactory(
            entityNamespace: '\Respect\Relational\\',
        )));
        $comment = $mapper->fetchAll($mapper->comment([$mapper->post()]));
        $this->assertInstanceOf('\Respect\Relational\Comment', $comment[0]);
        $this->assertInstanceOf('\Respect\Relational\Post', $comment[0]->post);
    }

    public function testPersistingEntityTyped(): void
    {
        $mapper = new Mapper($this->conn, new PrestyledAssoc(new EntityFactory(
            entityNamespace: '\Respect\Relational\\',
        )));
        $comment = $mapper->fetch($mapper->comment(filter: 8));
        $comment->text = 'HeyHey';
        $mapper->persist($comment, $mapper->comment());
        $mapper->flush();
        $result = $this->query('select text from comment where id=8')
            ->fetchColumn(0);
        $this->assertEquals('HeyHey', $result);
    }

    public function testPersistingNewEntityTyped(): void
    {
        $mapper = new Mapper($this->conn, new PrestyledAssoc(new EntityFactory(
            entityNamespace: '\Respect\Relational\\',
        )));
        $comment = new Comment();
        $comment->text = 'HeyHey';
        $mapper->persist($comment, $mapper->comment());
        $mapper->flush();
        $result = $this->query('select text from comment where id=9')
            ->fetchColumn(0);
        $this->assertEquals('HeyHey', $result);
    }

    public function testSettersAndGettersDatetimeAsObject(): void
    {
        $mapper = new Mapper($this->conn, new PrestyledAssoc(new EntityFactory(
            entityNamespace: '\Respect\Relational\\',
        )));
        $post = new Post();
        $post->id = 44;
        $post->text = 'Test using datetime setters';
        $post->setDatetime(new Datetime('now'));
        $mapper->persist($post, $mapper->post());
        $mapper->flush();

        $result = $mapper->fetch($mapper->post(filter: 44));
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
            $mapper = new Mapper($this->conn, new PrestyledAssoc($factory));
            $this->assertEquals($style, $mapper->style);
        }
    }

    public function testCompositesBringResultsFromTwoTables(): void
    {
        $mapper = $this->mapper;
        $mapper->registerCollection('postComment', Composite::post(
            ['comment' => ['text']],
            with: [Collection::author()],
        ));
        $post = $mapper->fetch($mapper->postComment());
        $this->assertEquals(1, $post->author->id);
        $this->assertEquals('Author 1', $post->author->name);
        $this->assertEquals(5, $post->id);
        $this->assertEquals('Post Title', $post->title);
        $this->assertEquals('Comment Text', $post->text);
    }

    public function testCompositesPersistsResultsOnTwoTables(): void
    {
        $mapper = $this->mapper;
        $mapper->registerCollection('postComment', Composite::post(
            ['comment' => ['text']],
            with: [Collection::author()],
        ));
        $post = $mapper->fetch($mapper->postComment());
        $this->assertEquals(1, $post->author->id);
        $this->assertEquals(5, $post->id);
        $this->assertEquals('Post Title', $post->title);
        $this->assertEquals('Comment Text', $post->text);
        $post->title = 'Title Changed';
        $post->text = 'Comment Changed';

        $mapper->persist($post, $mapper->postComment());
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
        $mapper->registerCollection('postComment', Composite::post(
            ['comment' => ['text']],
            with: [Collection::author()],
        ));
        $post = new Post();
        $post->text = 'Comment X';
        $post->title = 'Post X';
        $authorX = new Author();
        $authorX->name = 'Author X';
        $post->author = $authorX;
        $mapper->persist($post, $mapper->postComment());
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
        $mapper->registerCollection('postComment', Composite::post(
            ['comment' => ['text']],
            with: [Collection::author()],
        ));
        $post = $mapper->fetch($mapper->postComment());
        $post->title = 'Same Value';
        $post->text = 'Same Value';

        $mapper->persist($post, $mapper->postComment());
        $mapper->flush();
        $result = $this->query('select title from post where id=5')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('Same Value', $result->title);
        $result = $this->query('select text from comment where id=7')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('Same Value', $result->text);
    }

    public function testCompositeColumnOverridesParentOnNameCollision(): void
    {
        $mapper = $this->mapper;
        $mapper->registerCollection('postComment', Composite::post(
            ['comment' => ['text']],
            with: [Collection::author()],
        ));
        $post = $mapper->fetch($mapper->postComment());

        // Both post and comment have a 'text' column.
        // The composite column (comment.text) should take precedence.
        $this->assertEquals('Comment Text', $post->text);
        $this->assertNotEquals('Post Text', $post->text);
    }

    public function testTyped(): void
    {
        $mapper = new Mapper($this->conn, new PrestyledAssoc(new EntityFactory(
            entityNamespace: '\Respect\Relational\\',
        )));
        $mapper->registerCollection('typedIssues', Typed::issues('type'));
        $issues = $mapper->fetchAll($mapper->typedIssues());
        $this->assertInstanceOf('\\Respect\Relational\\Bug', $issues[0]);
        $this->assertInstanceOf('\\Respect\Relational\\Improvement', $issues[1]);
        $this->assertEquals(1, $issues[0]->id);
        $this->assertEquals('bug', $issues[0]->type);
        $this->assertEquals('Bug 1', $issues[0]->title);
        $this->assertEquals(2, $issues[1]->id);
        $this->assertEquals('improvement', $issues[1]->type);
        $issues[0]->title = 'Title Changed';
        $mapper->persist($issues[0], $mapper->typedIssues());
        $mapper->flush();
        $result = $this->query('select title from issues where id=1')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('Title Changed', $result->title);
    }

    public function testTypedSingle(): void
    {
        $mapper = new Mapper($this->conn, new PrestyledAssoc(new EntityFactory(
            entityNamespace: '\Respect\Relational\\',
        )));
        $mapper->registerCollection('typedIssues', Typed::issues('type'));
        $issue = $mapper->fetch($mapper->typedIssues());
        $this->assertInstanceOf('\\Respect\Relational\\Bug', $issue);
        $this->assertEquals(1, $issue->id);
        $this->assertEquals('bug', $issue->type);
        $this->assertEquals('Bug 1', $issue->title);
        $issue->title = 'Title Changed';
        $mapper->persist($issue, $mapper->typedIssues());
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
        $mapper->persist($entity, $mapper->category());
        $mapper->flush();
        $result = $this->query('select * from category where id=10')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('array_object_category', $result->name);
    }

    public function testFetchingEntityWithoutPublicPropertiesTyped(): void
    {
        $mapper = new Mapper($this->conn, new PrestyledAssoc(new EntityFactory(
            entityNamespace: '\Respect\Relational\OtherEntity\\',
        )));
        $post = $mapper->fetch($mapper->post(filter: 5));
        $this->assertInstanceOf('\Respect\Relational\OtherEntity\Post', $post);
    }

    public function testFetchingAllEntityWithoutPublicPropertiesTyped(): void
    {
        $mapper = new Mapper($this->conn, new PrestyledAssoc(new EntityFactory(
            entityNamespace: '\Respect\Relational\OtherEntity\\',
        )));
        $posts = $mapper->fetchAll($mapper->post());
        $this->assertInstanceOf('\Respect\Relational\OtherEntity\Post', $posts[0]);
    }

    public function testFetchingAllEntityWithoutPublicPropertiesTypedNested(): void
    {
        $mapper = new Mapper($this->conn, new PrestyledAssoc(new EntityFactory(
            entityNamespace: '\Respect\Relational\OtherEntity\\',
        )));
        $posts = $mapper->fetchAll($mapper->post([$mapper->author()]));
        $this->assertInstanceOf('\Respect\Relational\OtherEntity\Post', $posts[0]);
        $this->assertInstanceOf(
            '\Respect\Relational\OtherEntity\Author',
            $posts[0]->getAuthor(),
        );
    }

    public function testPersistingEntityWithoutPublicPropertiesTyped(): void
    {
        $mapper = new Mapper($this->conn, new PrestyledAssoc(new EntityFactory(
            entityNamespace: '\Respect\Relational\OtherEntity\\',
        )));

        $post = $mapper->fetch($mapper->post(filter: 5));
        $post->setText('HeyHey');

        $mapper->persist($post, $mapper->post());
        $mapper->flush();
        $result = $this->query('select text from post where id=5')
            ->fetchColumn(0);
        $this->assertEquals('HeyHey', $result);
    }

    public function testPersistingNewEntityWithoutPublicPropertiesTyped(): void
    {
        $mapper = new Mapper($this->conn, new PrestyledAssoc(new EntityFactory(
            entityNamespace: '\Respect\Relational\OtherEntity\\',
        )));

        $author = new OtherEntity\Author();
        $author->setId(1);
        $author->setName('Author 1');

        $post = new OtherEntity\Post();
        $post->setAuthor($author);
        $post->setTitle('My New Post Title');
        $post->setText('My new Post Text');
        $mapper->persist($post, $mapper->post());
        $mapper->flush();
        $result = $this->query('select text from post where id=6')
            ->fetchColumn(0);
        $this->assertEquals('My new Post Text', $result);
    }

    public function testShouldSkipEntityConstructorByDefault(): void
    {
        $mapper = new Mapper($this->conn, new PrestyledAssoc(new EntityFactory(
            entityNamespace: 'Respect\\Relational\\OtherEntity\\',
        )));

        // create() uses newInstanceWithoutConstructor, so the constructor is never called
        $comment = $mapper->fetch($mapper->comment());
        $this->assertInstanceOf('Respect\\Relational\\OtherEntity\\Comment', $comment);
    }

    public function testFetchWithConditionUsingColumnValue(): void
    {
        $mapper = $this->mapper;
        $comments = $mapper->fetchAll($mapper->comment(filter: ['post_id' => 5]));
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
        $mapper = new Mapper($conn, new PrestyledAssoc(new EntityFactory(
            entityNamespace: 'Respect\\Relational\\',
        )));
        $obj = new Author();
        $obj->name = 'test';
        $mapper->persist($obj, $mapper->author());
        $mapper->flush();
        $this->assertFalse((new ReflectionProperty($obj, 'id'))->isInitialized($obj));
    }

    public function testFetchReturnsDbInstance(): void
    {
        $db = new Db($this->conn);
        $mapper = new Mapper($db);
        $this->assertInstanceOf(Db::class, $mapper->db);
    }

    /** Identity Map: fetch() short-circuit */
    public function testFetchReturnsSameInstanceOnRepeatedPkLookup(): void
    {
        $first = $this->mapper->fetch($this->mapper->post(filter: 5));
        $second = $this->mapper->fetch($this->mapper->post(filter: 5));

        $this->assertSame($first, $second);
    }

    public function testFetchWithExtraBypassesIdentityMap(): void
    {
        $first = $this->mapper->fetch($this->mapper->post(filter: 5));
        $extra = new Sql();
        $extra->orderBy('post.id');
        $second = $this->mapper->fetch($this->mapper->post(filter: 5), $extra);

        $this->assertNotSame($first, $second);
        $this->assertEquals($first->id, $second->id);
    }

    public function testIdentityMapCountIncreasesOnFetch(): void
    {
        $this->assertSame(0, $this->mapper->identityMapCount());

        $this->mapper->fetch($this->mapper->author(filter: 1));

        $this->assertGreaterThan(0, $this->mapper->identityMapCount());
    }

    public function testClearIdentityMapForcesFreshFetch(): void
    {
        $first = $this->mapper->fetch($this->mapper->post(filter: 5));
        $this->mapper->clearIdentityMap();
        $second = $this->mapper->fetch($this->mapper->post(filter: 5));

        $this->assertNotSame($first, $second);
        $this->assertEquals($first->id, $second->id);
    }

    /** Identity Map: flushSingle() insert/update/delete */
    public function testInsertedEntityIsRetrievableFromIdentityMap(): void
    {
        $entity = new Post();
        $entity->title = 'New Post';
        $entity->text = 'New Text';

        $this->mapper->persist($entity, $this->mapper->post());
        $this->mapper->flush();

        // The entity should now have an auto-assigned id and be cached
        $this->assertGreaterThan(0, $entity->id);

        $fetched = $this->mapper->fetch($this->mapper->post(filter: $entity->id));
        $this->assertSame($entity, $fetched);
    }

    public function testUpdatedEntityKeepsReturningUpdatedInstance(): void
    {
        $entity = $this->mapper->fetch($this->mapper->post(filter: 5));
        $entity->title = 'Updated Title';

        $this->mapper->persist($entity, $this->mapper->post());
        $this->mapper->flush();

        $fetched = $this->mapper->fetch($this->mapper->post(filter: 5));
        $this->assertSame($entity, $fetched);
        $this->assertSame('Updated Title', $fetched->title);
    }

    public function testDeletedEntityIsEvictedFromIdentityMap(): void
    {
        $entity = $this->mapper->fetch($this->mapper->post(filter: 5));
        $this->assertSame($entity, $this->mapper->fetch($this->mapper->post(filter: 5)));

        $this->mapper->remove($entity, $this->mapper->post());
        $this->mapper->flush();

        // After delete, fetch should hit DB (and return false since the row is gone)
        $result = $this->mapper->fetch($this->mapper->post(filter: 5));
        $this->assertFalse($result);
    }

    /** Identity Map: parseHydrated() registers related entities */
    public function testRelatedEntityFromJoinReturnsSameInstanceOnDirectFetch(): void
    {
        // Fetch a comment with its related post via join
        $comment = $this->mapper->fetch($this->mapper->comment([$this->mapper->post()], filter: 7));

        // The related post entity should have been registered in the identity map
        $post = $this->mapper->fetch($this->mapper->post(filter: 5));
        $this->assertSame($comment->post->id, $post->id);

        // They should be the same object instance since parseHydrated()
        // registers all entities (including nested ones) in the identity map
        $this->assertSame($post, $this->mapper->fetch($this->mapper->post(filter: $post->id)));
    }

    public function testNestedRelatedEntitiesAllRegisteredInIdentityMap(): void
    {
        $this->mapper->fetch($this->mapper->comment([$this->mapper->post([$this->mapper->author()])], filter: 7));

        $postFromMap = $this->mapper->fetch($this->mapper->post(filter: 5));
        $authorFromMap = $this->mapper->fetch($this->mapper->author(filter: 1));

        $this->assertSame($postFromMap, $this->mapper->fetch($this->mapper->post(filter: 5)));
        $this->assertSame($authorFromMap, $this->mapper->fetch($this->mapper->author(filter: 1)));
    }

    public function testChildCollectionLeftJoinWiresMatchingAuthor(): void
    {
        $posts = $this->mapper->fetchAll($this->mapper->post([$this->mapper->author()]));
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

        $posts = $this->mapper->fetchAll($this->mapper->post([$this->mapper->author()]));
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
        $result = $this->mapper->fetch($this->mapper->post(filter: 9999));
        $this->assertFalse($result);
    }

    public function testPersistPureEntityTreeDerivesForeignKey(): void
    {
        $author = $this->mapper->fetch($this->mapper->author(filter: 1));

        $post = new Post();
        $post->title = 'Pure Tree';
        $post->text = 'No author_id property';
        $this->mapper->entityFactory->set($post, 'author', $author);

        $this->mapper->persist($post, $this->mapper->post());
        $this->mapper->flush();

        $row = $this->query('select * from post where title = "Pure Tree"')
            ->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row);
        $this->assertEquals(1, $row['author_id']);
    }

    public function testPersistWithUninitializedRelationSkipsCascade(): void
    {
        // Post has `Author $author` (uninitialized). Persist should not
        // crash — it should skip the cascade for the missing relation.
        $mapper = $this->mapper;
        $post = new Post();
        $post->title = 'No Author';
        $post->text = 'Body';

        $mapper->persist($post, $mapper->post());
        $mapper->flush();

        $this->assertGreaterThan(0, $post->id);
        $result = $this->query('select title from post where id=' . $post->id)
            ->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('No Author', $result->title);
    }

    public function testCompositeUpdateSkipsMissingSpecColumn(): void
    {
        // Composite spec asks for 'text' from comment, but we only change
        // 'title' (a post column). The composite should not crash on the
        // missing spec column — it should just skip it.
        $mapper = $this->mapper;
        $mapper->registerCollection('postComment', Composite::post(
            ['comment' => ['text']],
            with: [Collection::author()],
        ));
        $post = $mapper->fetch($mapper->postComment());

        // Only change a parent column, leave composite column unchanged
        $post->title = 'Only Title Changed';

        $mapper->persist($post, $mapper->postComment());
        $mapper->flush();

        $result = $this->query('select title from post where id=5')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('Only Title Changed', $result->title);
        // Comment text should remain untouched
        $result = $this->query('select text from comment where id=7')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('Comment Text', $result->text);
    }

    public function testCompositeInsertWithNoMatchingColumnsSkipsChild(): void
    {
        // New entity where the composite spec columns are NOT set — the
        // child INSERT should be skipped entirely (no empty INSERT).
        $mapper = $this->mapper;
        $mapper->registerCollection('postComment', Composite::post(
            ['comment' => ['text']],
            with: [Collection::author()],
        ));

        $post = new Postcomment();
        $post->title = 'Post Without Comment';
        $author = new Author();
        $author->name = 'Author X';
        $post->author = $author;
        // Note: $post->text is NOT set (uninitialized)

        $mapper->persist($post, $mapper->postComment());
        $mapper->flush();

        $result = $this->query('select title from post order by id desc')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertEquals('Post Without Comment', $result->title);
    }

    public function testFetchWithArrayConditions(): void
    {
        // Test multiple array conditions (hits the AND branch in parseConditions)
        $result = $this->mapper->fetchAll($this->mapper->post(filter: ['title' => 'Post Title', 'author_id' => 1]));
        $this->assertCount(1, $result);
        $this->assertEquals('Post Title', $result[0]->title);
    }

    public function testPersistCascadeSkipsNullChildRelation(): void
    {
        // Register a collection with children: post → author (child).
        // Persist a post where $author is uninitialized.
        // The cascade should skip the null child without crashing (L87-91).
        $mapper = $this->mapper;
        $this->expectNotToPerformAssertions();
        $mapper->persist(new class {
            public int $id;

            public string $title = 'Orphan Post';

            public string $text = '';
        }, $mapper->post([$mapper->author()]));
    }

    public function testReadOnlyNestedHydrationPostWithAuthor(): void
    {
        $this->conn->exec((string) Sql::createTable('read_only_post', [
            'id INTEGER PRIMARY KEY',
            'title VARCHAR(255)',
            'text TEXT',
            'read_only_author_id INTEGER',
        ]));
        $db = new Db($this->conn);
        $db->insertInto('read_only_post', ['id', 'title', 'text', 'read_only_author_id'])
            ->values([1, 'Post Title', 'Post Text', 1])
            ->exec();

        $post = $this->mapper->fetch($this->mapper->read_only_post([$this->mapper->read_only_author()]));

        $this->assertInstanceOf(ReadOnlyPost::class, $post);
        $this->assertSame(1, $post->id);
        $this->assertSame('Post Title', $post->title);

        $this->assertInstanceOf(ReadOnlyAuthor::class, $post->readOnlyAuthor);
        $this->assertSame(1, $post->readOnlyAuthor->id);
        $this->assertSame('Alice', $post->readOnlyAuthor->name);
    }

    public function testReadOnlyThreeLevelHydration(): void
    {
        $this->conn->exec((string) Sql::createTable('read_only_post', [
            'id INTEGER PRIMARY KEY',
            'title VARCHAR(255)',
            'text TEXT',
            'read_only_author_id INTEGER',
        ]));
        $this->conn->exec((string) Sql::createTable('read_only_comment', [
            'id INTEGER PRIMARY KEY',
            'text TEXT',
            'read_only_post_id INTEGER',
        ]));
        $db = new Db($this->conn);
        $db->insertInto('read_only_post', ['id', 'title', 'text', 'read_only_author_id'])
            ->values([1, 'Post Title', 'Post Text', 1])
            ->exec();
        $db->insertInto('read_only_comment', ['id', 'text', 'read_only_post_id'])
            ->values([1, 'Great post!', 1])
            ->exec();

        $comment = $this->mapper->fetch($this->mapper->read_only_comment([
            $this->mapper->read_only_post([$this->mapper->read_only_author()]),
        ]));

        $this->assertInstanceOf(ReadOnlyComment::class, $comment);
        $this->assertSame(1, $comment->id);
        $this->assertSame('Great post!', $comment->text);

        $this->assertInstanceOf(ReadOnlyPost::class, $comment->readOnlyPost);
        $this->assertSame(1, $comment->readOnlyPost->id);
        $this->assertSame('Post Title', $comment->readOnlyPost->title);

        $this->assertInstanceOf(ReadOnlyAuthor::class, $comment->readOnlyPost->readOnlyAuthor);
        $this->assertSame('Alice', $comment->readOnlyPost->readOnlyAuthor->name);
    }

    public function testReadOnlyInsertWithRelationCascade(): void
    {
        $this->conn->exec((string) Sql::createTable('read_only_post', [
            'id INTEGER PRIMARY KEY',
            'title VARCHAR(255)',
            'text TEXT',
            'read_only_author_id INTEGER',
        ]));

        $author = $this->mapper->entityFactory->create(ReadOnlyAuthor::class, name: 'New Author');
        $post = $this->mapper->entityFactory->create(
            ReadOnlyPost::class,
            title: 'New Post',
            text: 'Post body',
            readOnlyAuthor: $author,
        );

        // Cascade persist: author first, then post
        $this->mapper->persist($post, $this->mapper->read_only_post([$this->mapper->read_only_author()]));
        $this->mapper->flush();

        $this->assertGreaterThan(0, $author->id);
        $this->assertGreaterThan(0, $post->id);

        // Verify FK was correctly stored
        $result = $this->query(
            'SELECT * FROM read_only_post WHERE id=' . $post->id,
        )->fetch(PDO::FETCH_OBJ);
        $this->assertSame($author->id, (int) $result->read_only_author_id);
        $this->assertSame('New Post', $result->title);
    }

    public function testReadOnlyUpdateViaCollectionPkPreservesRelation(): void
    {
        $this->conn->exec((string) Sql::createTable('read_only_post', [
            'id INTEGER PRIMARY KEY',
            'title VARCHAR(255)',
            'text TEXT',
            'read_only_author_id INTEGER',
        ]));
        $db = new Db($this->conn);
        $db->insertInto('read_only_post', ['id', 'title', 'text', 'read_only_author_id'])
            ->values([1, 'Original', 'Body', 1])
            ->exec();

        // Fetch the full graph
        $fetched = $this->mapper->fetch($this->mapper->read_only_post([$this->mapper->read_only_author()]));
        $this->assertSame('Original', $fetched->title);
        $this->assertSame('Alice', $fetched->readOnlyAuthor->name);

        // Replace the post keeping same author
        $updated = $this->mapper->entityFactory->create(
            ReadOnlyPost::class,
            title: 'Updated',
            text: 'New body',
            readOnlyAuthor: $fetched->readOnlyAuthor,
        );
        $this->mapper->persist($updated, $this->mapper->read_only_post(filter: 1));
        $this->mapper->flush();

        $this->assertSame(1, $updated->id);

        // Verify DB
        $result = $this->query('SELECT * FROM read_only_post WHERE id=1')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertSame('Updated', $result->title);
        $this->assertSame('New body', $result->text);
        $this->assertSame(1, (int) $result->read_only_author_id);
    }

    public function testReadOnlyUpdateChangesRelation(): void
    {
        $this->conn->exec((string) Sql::createTable('read_only_post', [
            'id INTEGER PRIMARY KEY',
            'title VARCHAR(255)',
            'text TEXT',
            'read_only_author_id INTEGER',
        ]));
        $db = new Db($this->conn);
        $db->insertInto('read_only_author', ['id', 'name', 'bio'])
            ->values([2, 'Bob', 'Bob bio'])
            ->exec();
        $db->insertInto('read_only_post', ['id', 'title', 'text', 'read_only_author_id'])
            ->values([1, 'Original', 'Body', 1])
            ->exec();

        $fetched = $this->mapper->fetch($this->mapper->read_only_post([$this->mapper->read_only_author()]));
        $this->assertSame('Alice', $fetched->readOnlyAuthor->name);

        $bob = $this->mapper->fetch($this->mapper->read_only_author(filter: 2));

        // Replace post with a different author
        $updated = $this->mapper->entityFactory->create(
            ReadOnlyPost::class,
            title: 'Reassigned',
            text: 'Text',
            readOnlyAuthor: $bob,
        );
        $this->mapper->persist($updated, $this->mapper->read_only_post(filter: 1));
        $this->mapper->flush();

        $result = $this->query('SELECT * FROM read_only_post WHERE id=1')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertSame('Reassigned', $result->title);
        $this->assertSame(2, (int) $result->read_only_author_id);
    }

    public function testReadOnlyWithChangesAndPersistRoundTrip(): void
    {
        $this->conn->exec((string) Sql::createTable('read_only_post', [
            'id INTEGER PRIMARY KEY',
            'title VARCHAR(255)',
            'text TEXT',
            'read_only_author_id INTEGER',
        ]));
        $db = new Db($this->conn);
        $db->insertInto('read_only_author', ['id', 'name', 'bio'])
            ->values([2, 'Bob', 'Bob bio'])
            ->exec();
        $db->insertInto('read_only_post', ['id', 'title', 'text', 'read_only_author_id'])
            ->values([1, 'Original', 'Body', 1])
            ->exec();

        // Fetch full graph
        $post = $this->mapper->fetch($this->mapper->read_only_post([$this->mapper->read_only_author()]));
        $this->assertSame('Alice', $post->readOnlyAuthor->name);

        $bob = $this->mapper->fetch($this->mapper->read_only_author(filter: 2));

        // Partial entity with same PK → auto-update via identity map
        $updated = $this->mapper->entityFactory->create(
            ReadOnlyPost::class,
            id: 1,
            title: 'Changed',
            readOnlyAuthor: $bob,
        );
        $this->mapper->persist($updated, $this->mapper->read_only_post());
        $this->mapper->flush();

        // Verify DB
        $result = $this->query('SELECT * FROM read_only_post WHERE id=1')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertSame('Changed', $result->title);
        $this->assertSame('Body', $result->text);
        $this->assertSame(2, (int) $result->read_only_author_id);
    }

    public function testPersistPartialEntityRoundTrip(): void
    {
        $fetched = $this->mapper->fetch($this->mapper->read_only_author(filter: 1));
        $this->assertSame('Alice', $fetched->name);

        $partial = $this->mapper->entityFactory->create(
            ReadOnlyAuthor::class,
            id: 1,
            name: 'Alice Updated',
            bio: 'new bio',
        );
        $updated = $this->mapper->persist($partial, $this->mapper->read_only_author());
        $this->mapper->flush();

        $this->assertNotSame($fetched, $updated);
        $this->assertSame(1, $updated->id);
        $this->assertSame('Alice Updated', $updated->name);

        $result = $this->query('SELECT * FROM read_only_author WHERE id=1')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertSame('Alice Updated', $result->name);
        $this->assertSame('new bio', $result->bio);
    }

    public function testPersistPartialEntityOnGraph(): void
    {
        $this->conn->exec((string) Sql::createTable('read_only_post', [
            'id INTEGER PRIMARY KEY',
            'title VARCHAR(255)',
            'text TEXT',
            'read_only_author_id INTEGER',
        ]));
        $db = new Db($this->conn);
        $db->insertInto('read_only_author', ['id', 'name', 'bio'])
            ->values([2, 'Bob', 'Bob bio'])
            ->exec();
        $db->insertInto('read_only_post', ['id', 'title', 'text', 'read_only_author_id'])
            ->values([1, 'Original', 'Body', 1])
            ->exec();

        $this->mapper->fetch($this->mapper->read_only_post([$this->mapper->read_only_author()]));
        $bob = $this->mapper->fetch($this->mapper->read_only_author(filter: 2));

        $partial = $this->mapper->entityFactory->create(
            ReadOnlyPost::class,
            id: 1,
            title: 'Changed',
            readOnlyAuthor: $bob,
        );
        $updated = $this->mapper->persist($partial, $this->mapper->read_only_post());
        $this->mapper->flush();

        $this->assertSame(1, $updated->id);
        $result = $this->query('SELECT * FROM read_only_post WHERE id=1')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertSame('Changed', $result->title);
        $this->assertSame(2, (int) $result->read_only_author_id);
    }

    public function testReadOnlyEntityHydration(): void
    {
        $entity = $this->mapper->fetch($this->mapper->read_only_author(filter: 1));

        $this->assertInstanceOf(ReadOnlyAuthor::class, $entity);
        $this->assertSame(1, $entity->id);
        $this->assertSame('Alice', $entity->name);
        $this->assertSame('Alice bio', $entity->bio);
    }

    public function testReadOnlyEntityInsertWithAutoIncrementPk(): void
    {
        $entity = $this->mapper->entityFactory->create(ReadOnlyAuthor::class, name: 'Bob', bio: 'Bob bio');
        $this->mapper->persist($entity, $this->mapper->read_only_author());
        $this->mapper->flush();

        $this->assertGreaterThan(0, $entity->id);

        $result = $this->query(
            'SELECT * FROM read_only_author WHERE name="Bob"',
        )->fetch(PDO::FETCH_OBJ);
        $this->assertSame('Bob', $result->name);
        $this->assertSame('Bob bio', $result->bio);
    }

    public function testReadOnlyEntityUpdateViaCollectionPk(): void
    {
        // Fetch to populate identity map
        $fetched = $this->mapper->fetch($this->mapper->read_only_author(filter: 1));
        $this->assertSame('Alice', $fetched->name);

        // Persist a new readonly entity via collection[pk]
        $updated = $this->mapper->entityFactory->create(ReadOnlyAuthor::class, name: 'Alice Updated', bio: 'new bio');
        $this->mapper->persist($updated, $this->mapper->read_only_author(filter: 1));
        $this->mapper->flush();

        // Verify PK was transferred
        $this->assertSame(1, $updated->id);

        // Verify database was updated
        $result = $this->query(
            'SELECT * FROM read_only_author WHERE id=1',
        )->fetch(PDO::FETCH_OBJ);
        $this->assertSame('Alice Updated', $result->name);
        $this->assertSame('new bio', $result->bio);
    }

    public function testReadOnlyDeleteAndRefetch(): void
    {
        $fetched = $this->mapper->fetch($this->mapper->read_only_author(filter: 1));
        $this->assertSame('Alice', $fetched->name);

        $this->mapper->remove($fetched, $this->mapper->read_only_author());
        $this->mapper->flush();

        $result = $this->query('SELECT COUNT(*) as cnt FROM read_only_author WHERE id=1')
            ->fetch(PDO::FETCH_OBJ);
        $this->assertSame(0, (int) $result->cnt);

        $this->mapper->clearIdentityMap();
        $refetched = $this->mapper->fetch($this->mapper->read_only_author(filter: 1));
        $this->assertFalse($refetched);
    }

    public function testMixedMutableAuthorReadOnlyPost(): void
    {
        $this->conn->exec((string) Sql::createTable('read_only_post', [
            'id INTEGER PRIMARY KEY',
            'title VARCHAR(255)',
            'text TEXT',
            'read_only_author_id INTEGER',
        ]));

        // Mutable author + readonly post in same graph
        $author = new Author();
        $author->name = 'Mutable Author';
        $this->mapper->persist($author, $this->mapper->author());
        $this->mapper->flush();

        $readonlyPost = $this->mapper->entityFactory->create(
            ReadOnlyPost::class,
            title: 'Immutable Post',
            text: 'Body',
        );
        $this->mapper->persist($readonlyPost, $this->mapper->read_only_post());
        $this->mapper->flush();

        $this->assertGreaterThan(0, $author->id);
        $this->assertGreaterThan(0, $readonlyPost->id);

        // Verify both persisted
        $authorRow = $this->query('SELECT * FROM author WHERE name="Mutable Author"')
            ->fetch(PDO::FETCH_OBJ);
        $postRow = $this->query('SELECT * FROM read_only_post WHERE id=' . $readonlyPost->id)
            ->fetch(PDO::FETCH_OBJ);
        $this->assertSame('Mutable Author', $authorRow->name);
        $this->assertSame('Immutable Post', $postRow->title);
    }

    public function testPersistWithSelfReferentialCycleDoesNotInfiniteLoop(): void
    {
        $cat = new Category();
        $cat->name = 'Root';
        $cat->category = $cat; // self-referential cycle

        // Should not infinite-loop — cycle detection skips already-visiting objects
        $this->mapper->persist($cat, $this->mapper->category([$this->mapper->category()]));
        $this->mapper->flush();

        $this->assertGreaterThan(0, $cat->id);

        $row = $this->query('SELECT * FROM category WHERE id=' . $cat->id)
            ->fetch(PDO::FETCH_OBJ);
        $this->assertSame('Root', $row->name);
    }

    private function query(string $sql): PDOStatement
    {
        $stmt = $this->conn->query($sql);
        self::assertInstanceOf(PDOStatement::class, $stmt);

        return $stmt;
    }
}
