<?php

declare(strict_types=1);

namespace Respect\Data\Styles;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Standard::class)]
class StandardTest extends TestCase
{

    /**
     * @var Respect\Data\Styles\Standard
     */
    private $style;


    public static function tableEntityProvider(): array
    {
        return array(
            array('post',           'Post'),
            array('comment',        'Comment'),
            array('category',       'Category'),
            array('post_category',  'PostCategory'),
            array('post_tag',       'PostTag'),
        );
    }

    public static function manyToMantTableProvider(): array
    {
        return array(
            array('post',   'category', 'post_category'),
            array('user',   'group',    'user_group'),
            array('group',  'profile',  'group_profile'),
        );
    }

    public static function columnsPropertyProvider(): array
    {
        return array(
            array('id'),
            array('text'),
            array('name'),
            array('content'),
            array('created'),
        );
    }

    public static function foreignProvider(): array
    {
        return array(
            array('post',       'post_id'),
            array('author',     'author_id'),
            array('tag',        'tag_id'),
            array('user',       'user_id'),
        );
    }


    protected function setUp(): void
    {
        $this->style = new Standard();
    }

    protected function tearDown(): void
    {
        $this->style = null;
    }

    #[DataProvider('tableEntityProvider')]
    public function test_table_and_entities_methods($table, $entity): void
    {
        $this->assertEquals($entity, $this->style->styledName($table));
        $this->assertEquals($table, $this->style->realName($entity));
        $this->assertEquals('id', $this->style->identifier($table));
    }

    #[DataProvider('columnsPropertyProvider')]
    public function test_columns_and_properties_methods($name): void
    {
        $this->assertEquals($name, $this->style->styledProperty($name));
        $this->assertEquals($name, $this->style->realProperty($name));
        $this->assertFalse($this->style->isRemoteIdentifier($name));
        $this->assertNull($this->style->remoteFromIdentifier($name));
    }

    #[DataProvider('manyToMantTableProvider')]
    public function test_table_from_left_right_table($left, $right, $table): void
    {
        $this->assertEquals($table, $this->style->composed($left, $right));
    }

    #[DataProvider('foreignProvider')]
    public function test_foreign($table, $foreign): void
    {
        $this->assertTrue($this->style->isRemoteIdentifier($foreign));
        $this->assertEquals($table, $this->style->remoteFromIdentifier($foreign));
        $this->assertEquals($foreign, $this->style->remoteIdentifier($table));
    }

}
