<?php

declare(strict_types=1);

namespace Respect\Data\Styles;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[CoversClass(AbstractStyle::class)]
class AbstractStyleTest extends TestCase
{
    private AbstractStyle $style;

    protected function setUp(): void
    {
        $this->style = new class extends AbstractStyle {
            public function styledProperty(string $name): string
            {
                return $name;
            }

            public function realName(string $name): string
            {
                return $name;
            }

            public function realProperty(string $name): string
            {
                return $name;
            }

            public function styledName(string $name): string
            {
                return $name;
            }

            public function identifier(string $name): string
            {
                return 'id';
            }

            public function remoteIdentifier(string $name): string
            {
                return $name . '_id';
            }

            public function composed(string $left, string $right): string
            {
                return $left . '_' . $right;
            }

            public function isRemoteIdentifier(string $name): bool
            {
                return false;
            }

            public function remoteFromIdentifier(string $name): string|null
            {
                return null;
            }
        };
    }

    /** @return array<array{string, string}> */
    public static function singularPluralProvider(): array
    {
        return [
            ['post', 'posts'],
            ['comment', 'comments'],
            ['category', 'categories'],
            ['tag', 'tags'],
            ['entity', 'entities'],
        ];
    }

    /** @return array<array{string, string, string}> */
    public static function camelCaseToSeparatorProvider(): array
    {
        return [
            ['-', 'HenriqueMoody', 'Henrique-Moody'],
            [' ', 'AlexandreGaigalas', 'Alexandre Gaigalas'],
            ['_', 'AugustoPascutti', 'Augusto_Pascutti'],
        ];
    }

    #[DataProvider('singularPluralProvider')]
    public function testPluralToSingularAndViceVersa(string $singular, string $plural): void
    {
        $pluralToSingular = new ReflectionMethod($this->style, 'pluralToSingular');
        $this->assertEquals($singular, $pluralToSingular->invoke($this->style, $plural));

        $singularToPlural = new ReflectionMethod($this->style, 'singularToPlural');
        $this->assertEquals($plural, $singularToPlural->invoke($this->style, $singular));
    }

    #[DataProvider('camelCaseToSeparatorProvider')]
    public function testCamelCaseToSeparatorAndViceVersa(
        string $separator,
        string $camelCase,
        string $separated,
    ): void {
        $camelCaseToSeparatorMethod = new ReflectionMethod($this->style, 'camelCaseToSeparator');
        $this->assertEquals(
            $separated,
            $camelCaseToSeparatorMethod->invoke($this->style, $camelCase, $separator),
        );

        $separatorToCamelCaseMethod = new ReflectionMethod($this->style, 'separatorToCamelCase');
        $this->assertEquals(
            $camelCase,
            $separatorToCamelCaseMethod->invoke($this->style, $separated, $separator),
        );
    }
}
