<?php

declare(strict_types=1);

namespace Respect\Data\Styles\Sakila;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Respect\Data\Styles\Sakila;
use Respect\Relational\Db;
use Respect\Relational\Mapper;
use Respect\Relational\Sql;

#[CoversClass(Sakila::class)]
class SakilaTest extends TestCase
{
    private Sakila $style;

    private Mapper $mapper;

    private PDO $conn;

    /** @var list<object> */
    private array $posts;

    /** @var list<object> */
    private array $authors;

    /** @var list<object> */
    private array $comments;

    /** @var list<object> */
    private array $categories;

    /** @var list<object> */
    private array $postsCategories;

    protected function setUp(): void
    {
        $conn = new PDO('sqlite::memory:');
        $db = new Db($conn);
        $conn->exec(
            (string) Sql::createTable(
                'post',
                [
                    'post_id INTEGER PRIMARY KEY',
                    'title VARCHAR(255)',
                    'text TEXT',
                    'author_id INTEGER',
                ],
            ),
        );
        $conn->exec(
            (string) Sql::createTable(
                'author',
                [
                    'author_id INTEGER PRIMARY KEY',
                    'name VARCHAR(255)',
                ],
            ),
        );
        $conn->exec(
            (string) Sql::createTable(
                'comment',
                [
                    'comment_id INTEGER PRIMARY KEY',
                    'post_id INTEGER',
                    'text TEXT',
                ],
            ),
        );

        $conn->exec(
            (string) Sql::createTable(
                'category',
                [
                    'category_id INTEGER PRIMARY KEY',
                    'name VARCHAR(255)',
                    'content VARCHAR(255)',
                    'description TEXT',
                ],
            ),
        );
        $conn->exec(
            (string) Sql::createTable(
                'post_category',
                [
                    'post_category_id INTEGER PRIMARY KEY',
                    'post_id INTEGER',
                    'category_id INTEGER',
                ],
            ),
        );
        $this->posts = [
            (object) [
                'post_id' => 5,
                'title' => 'Post Title',
                'text' => 'Post Text',
                'author_id' => 1,
            ],
        ];
        $this->authors = [
            (object) [
                'author_id' => 1,
                'name' => 'Author 1',
            ],
        ];
        $this->comments = [
            (object) [
                'comment_id' => 7,
                'post_id' => 5,
                'text' => 'Comment Text',
            ],
            (object) [
                'comment_id' => 8,
                'post_id' => 4,
                'text' => 'Comment Text 2',
            ],
        ];
        $this->categories = [
            (object) [
                'category_id' => 2,
                'name' => 'Sample Category',
                'content' => null,
            ],
            (object) [
                'category_id' => 3,
                'name' => 'NONON',
                'content' => null,
            ],
        ];
        $this->postsCategories = [
            (object) [
                'post_category_id' => 66,
                'post_id' => 5,
                'category_id' => 2,
            ],
        ];

        foreach ($this->authors as $author) {
            $db->insertInto('author', (array) $author)->values((array) $author)->exec();
        }

        foreach ($this->posts as $post) {
            $db->insertInto('post', (array) $post)->values((array) $post)->exec();
        }

        foreach ($this->comments as $comment) {
            $db->insertInto('comment', (array) $comment)->values((array) $comment)->exec();
        }

        foreach ($this->categories as $category) {
            $db->insertInto('category', (array) $category)->values((array) $category)->exec();
        }

        foreach ($this->postsCategories as $postCategory) {
            $db->insertInto('post_category', (array) $postCategory)->values((array) $postCategory)->exec();
        }

        $this->conn     = $conn;
        $this->style    = new Sakila();
        $this->mapper   = new Mapper($conn);
        $this->mapper->setStyle($this->style);
        $this->mapper->entityNamespace = __NAMESPACE__ . '\\';
    }

    /** @return array<int, array<int, string>> */
    public static function tableEntityProvider(): array
    {
        return [
            ['post',           'Post'],
            ['comment',        'Comment'],
            ['category',       'Category'],
            ['post_category',  'PostCategory'],
            ['post_tag',       'PostTag'],
        ];
    }

    /** @return array<int, array<int, string>> */
    public static function manyToMantTableProvider(): array
    {
        return [
            ['post',   'category', 'post_category'],
            ['user',   'group',    'user_group'],
            ['group',  'profile',  'group_profile'],
        ];
    }

    /** @return array<int, array<int, string>> */
    public static function columnsPropertyProvider(): array
    {
        return [
            ['id'],
            ['text'],
            ['name'],
            ['content'],
            ['created'],
        ];
    }

    /** @return array<int, array<int, string>> */
    public static function keyProvider(): array
    {
        return [
            ['post',       'post_id'],
            ['author',     'author_id'],
            ['tag',        'tag_id'],
            ['user',       'user_id'],
        ];
    }

    #[DataProvider('tableEntityProvider')]
    public function testTableAndEntitiesMethods(string $table, string $entity): void
    {
        $this->assertEquals($entity, $this->style->styledName($table));
        $this->assertEquals($table, $this->style->realName($entity));
    }

    #[DataProvider('columnsPropertyProvider')]
    public function testColumnsAndPropertiesMethods(string $column): void
    {
        $this->assertEquals($column, $this->style->styledProperty($column));
        $this->assertEquals($column, $this->style->realProperty($column));
        $this->assertFalse($this->style->isRemoteIdentifier($column));
        $this->assertNull($this->style->remoteFromIdentifier($column));
    }

    #[DataProvider('manyToMantTableProvider')]
    public function testTableFromLeftRightTable(string $left, string $right, string $table): void
    {
        $this->assertEquals($table, $this->style->composed($left, $right));
    }

    #[DataProvider('keyProvider')]
    public function testForeign(string $table, string $key): void
    {
        $this->assertTrue($this->style->isRemoteIdentifier($key));
        $this->assertEquals($table, $this->style->remoteFromIdentifier($key));
        $this->assertEquals($key, $this->style->identifier($table));
        $this->assertEquals($key, $this->style->remoteIdentifier($table));
    }

    public function testFetchingEntityTyped(): void
    {
        $mapper = $this->mapper;
        $comment = $mapper->comment[8]->fetch();
        $this->assertInstanceOf(__NAMESPACE__ . '\Comment', $comment);
    }

    public function testFetchingAllEntityTyped(): void
    {
        $mapper = $this->mapper;
        $comment = $mapper->comment->fetchAll();
        $this->assertInstanceOf(__NAMESPACE__ . '\Comment', $comment[1]);

        $categories = $mapper->post_category->category->fetch();
        $this->assertInstanceOf(__NAMESPACE__ . '\PostCategory', $categories);
        $this->assertInstanceOf(
            __NAMESPACE__ . '\Category',
            $categories->category_id,
        );
    }

    public function testFetchingAllEntityTypedNested(): void
    {
        $mapper = $this->mapper;
        $comment = $mapper->comment->post->author->fetchAll();
        $this->assertInstanceOf(__NAMESPACE__ . '\Comment', $comment[0]);
        $this->assertInstanceOf(__NAMESPACE__ . '\Post', $comment[0]->post_id);
        $this->assertInstanceOf(
            __NAMESPACE__ . '\Author',
            $comment[0]->post_id->author_id,
        );
    }

    public function testPersistingEntityTyped(): void
    {
        $mapper = $this->mapper;
        $comment = $mapper->comment[8]->fetch();
        $this->assertInstanceOf(__NAMESPACE__ . '\Comment', $comment);
        $comment->text = 'HeyHey';
        $mapper->comment->persist($comment);
        $mapper->flush();
        $result = $this->conn->query(
            'select text from comment where comment_id=8',
        )->fetchColumn(0);
        $this->assertEquals('HeyHey', $result);
    }

    public function testPersistingNewEntityTyped(): void
    {
        $mapper = $this->mapper;
        $comment = new Comment();
        $comment->text = 'HeyHey';
        $mapper->comment->persist($comment);
        $mapper->flush();
        $result = $this->conn->query(
            'select text from comment where comment_id=9',
        )->fetchColumn(0);
        $this->assertEquals('HeyHey', $result);
    }
}

class Post
{
    public mixed $post_id = null;

    public string|null $title = null;

    public string|null $text = null;

    public mixed $author_id = null;
}

class Author
{
    public mixed $author_id = null;

    public string|null $name = null;
}

class Comment
{
    public mixed $comment_id = null;

    public mixed $post_id = null;

    public string|null $text = null;
}

class Category
{
    public mixed $category_id = null;

    public string|null $name = null;

    public string|null $content = null;

    public string|null $description = null;
}

class PostCategory
{
    public mixed $post_category_id = null;

    public mixed $post_id = null;

    public mixed $category_id = null;
}
