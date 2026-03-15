<?php

declare(strict_types=1);

namespace Respect\Data\Styles;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Standard::class)]
class StandardTest extends TestCase
{
    private Standard $style;

    protected function setUp(): void
    {
        $this->style = new Standard();
    }

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

    public static function manyToMantTableProvider(): array
    {
        return [
            ['post',   'category', 'post_category'],
            ['user',   'group',    'user_group'],
            ['group',  'profile',  'group_profile'],
        ];
    }

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

    public static function foreignProvider(): array
    {
        return [
            ['post',       'post_id'],
            ['author',     'author_id'],
            ['tag',        'tag_id'],
            ['user',       'user_id'],
        ];
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
