<?php

declare(strict_types=1);

namespace Respect\Data\Styles\NorthWind;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Respect\Data\Styles\NorthWind;
use Respect\Relational\Db;
use Respect\Relational\Mapper;
use Respect\Relational\Sql;

#[CoversClass(NorthWind::class)]
class NorthWindTest extends TestCase
{
    private NorthWind $style;

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
                'Posts',
                [
                    'PostID INTEGER PRIMARY KEY',
                    'Title VARCHAR(255)',
                    'Text TEXT',
                    'AuthorID INTEGER',
                ],
            ),
        );
        $conn->exec(
            (string) Sql::createTable(
                'Authors',
                [
                    'AuthorID INTEGER PRIMARY KEY',
                    'Name VARCHAR(255)',
                ],
            ),
        );
        $conn->exec(
            (string) Sql::createTable(
                'Comments',
                [
                    'CommentID INTEGER PRIMARY KEY',
                    'PostID INTEGER',
                    'Text TEXT',
                ],
            ),
        );

        $conn->exec(
            (string) Sql::createTable(
                'Categories',
                [
                    'CategoryID INTEGER PRIMARY KEY',
                    'Name VARCHAR(255)',
                    'Description TEXT',
                ],
            ),
        );
        $conn->exec(
            (string) Sql::createTable(
                'PostCategories',
                [
                    'PostCategoryID INTEGER PRIMARY KEY',
                    'PostID INTEGER',
                    'CategoryID INTEGER',
                ],
            ),
        );
        $this->posts = [
            (object) [
                'PostID' => 5,
                'Title' => 'Post Title',
                'Text' => 'Post Text',
                'AuthorID' => 1,
            ],
        ];
        $this->authors = [
            (object) [
                'AuthorID' => 1,
                'Name' => 'Author 1',
            ],
        ];
        $this->comments = [
            (object) [
                'CommentID' => 7,
                'PostID' => 5,
                'Text' => 'Comment Text',
            ],
            (object) [
                'CommentID' => 8,
                'PostID' => 4,
                'Text' => 'Comment Text 2',
            ],
        ];
        $this->categories = [
            (object) [
                'CategoryID' => 2,
                'Name' => 'Sample Category',
                'Description' => 'Category description',
            ],
            (object) [
                'CategoryID' => 3,
                'Name' => 'NONON',
                'CategoryID' => null,
            ],
        ];
        $this->postsCategories = [
            (object) [
                'PostCategoryID' => 66,
                'PostID' => 5,
                'CategoryID' => 2,
            ],
        ];

        foreach ($this->authors as $author) {
            $db->insertInto('Authors', (array) $author)->values((array) $author)->exec();
        }

        foreach ($this->posts as $post) {
            $db->insertInto('Posts', (array) $post)->values((array) $post)->exec();
        }

        foreach ($this->comments as $comment) {
            $db->insertInto('Comments', (array) $comment)->values((array) $comment)->exec();
        }

        foreach ($this->categories as $category) {
            $db->insertInto('Categories', (array) $category)->values((array) $category)->exec();
        }

        foreach ($this->postsCategories as $postCategory) {
            $db->insertInto('PostCategories', (array) $postCategory)->values((array) $postCategory)->exec();
        }

        $this->conn     = $conn;
        $this->style    = new NorthWind();
        $this->mapper   = new Mapper($conn);
        $this->mapper->setStyle($this->style);
        $this->mapper->entityNamespace = __NAMESPACE__ . '\\';
    }

    /** @return array<int, array<int, string>> */
    public static function tableEntityProvider(): array
    {
        return [
            ['Posts',              'Posts'],
            ['Comments',           'Comments'],
            ['Categories',         'Categories'],
            ['PostCategories',     'PostCategories'],
            ['PostTags',           'PostTags'],
        ];
    }

    /** @return array<int, array<int, string>> */
    public static function manyToMantTableProvider(): array
    {
        return [
            ['Posts',  'Categories',   'PostCategories'],
            ['Users',  'Groups',       'UserGroups'],
            ['Groups', 'Profiles',     'GroupProfiles'],
        ];
    }

    /** @return array<int, array<int, string>> */
    public static function columnsPropertyProvider(): array
    {
        return [
            ['Text'],
            ['Name'],
            ['Content'],
            ['Created'],
            ['Udated'],
        ];
    }

    /** @return array<int, array<int, string>> */
    public static function keyProvider(): array
    {
        return [
            ['Posts',      'PostID'],
            ['Authors',    'AuthorID'],
            ['Tags',       'TagID'],
            ['Users',      'UserID'],
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
    public function testKeys(string $table, string $foreign): void
    {
        $this->assertTrue($this->style->isRemoteIdentifier($foreign));
        $this->assertEquals($table, $this->style->remoteFromIdentifier($foreign));
        $this->assertEquals($foreign, $this->style->identifier($table));
        $this->assertEquals($foreign, $this->style->remoteIdentifier($table));
    }

    public function testFetchingEntityTyped(): void
    {
        $mapper = $this->mapper;
        $comment = $mapper->Comments[8]->fetch();
        $this->assertInstanceOf(__NAMESPACE__ . '\Comments', $comment);
    }

    public function testFetchingAllEntityTyped(): void
    {
        $mapper = $this->mapper;
        $comment = $mapper->Comments->fetchAll();
        $this->assertInstanceOf(__NAMESPACE__ . '\Comments', $comment[1]);

        $categories = $mapper->PostCategories->Categories->fetch();
        $this->assertInstanceOf(__NAMESPACE__ . '\PostCategories', $categories);
        $this->assertInstanceOf(
            __NAMESPACE__ . '\Categories',
            $categories->CategoryID,
        );
    }

    public function testFetchingAllEntityTypedNested(): void
    {
        $mapper = $this->mapper;
        $comment = $mapper->Comments->Posts->Authors->fetchAll();
        $this->assertInstanceOf(__NAMESPACE__ . '\Comments', $comment[0]);
        $this->assertInstanceOf(__NAMESPACE__ . '\Posts', $comment[0]->PostID);
        $this->assertInstanceOf(
            __NAMESPACE__ . '\Authors',
            $comment[0]->PostID->AuthorID,
        );
    }

    public function testPersistingEntityTyped(): void
    {
        $mapper = $this->mapper;
        $comment = $mapper->Comments[8]->fetch();
        $this->assertInstanceOf(__NAMESPACE__ . '\Comments', $comment);
        $comment->Text = 'HeyHey';
        $mapper->Comments->persist($comment);
        $mapper->flush();
        $result = $this->conn->query(
            'select Text from Comments where CommentID=8',
        )->fetchColumn(0);
        $this->assertEquals('HeyHey', $result);
    }

    public function testPersistingNewEntityTyped(): void
    {
        $mapper = $this->mapper;
        $comment = new Comments();
        $comment->Text = 'HeyHey';
        $mapper->Comments->persist($comment);
        $mapper->flush();
        $result = $this->conn->query(
            'select Text from Comments where CommentID=9',
        )->fetchColumn(0);
        $this->assertEquals('HeyHey', $result);
    }
}

class Posts
{
    public mixed $PostID = null;

    public string|null $Title = null;

    public string|null $Text = null;

    public mixed $AuthorID = null;
}

class Authors
{
    public mixed $AuthorID = null;

    public string|null $Name = null;
}

class Comments
{
    public mixed $CommentID = null;

    public mixed $PostID = null;

    public string|null $Text = null;
}

class Categories
{
    public mixed $CategoryID = null;

    public string|null $Name = null;

    public string|null $Description = null;
}

class PostCategories
{
    public mixed $PostCategoryID = null;

    public mixed $PostID = null;

    public mixed $CategoryID = null;
}
