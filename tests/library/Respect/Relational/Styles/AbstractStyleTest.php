<?php

namespace Respect\Data\Styles;

class AbstractStyleTest extends \PHPUnit\Framework\TestCase
{

    /**
     * @var Respect\Data\Styles\AbstractStyle
     */
    private $style;

    public static function singularPluralProvider()
    {
        return array(
            array('post', 'posts'),
            array('comment', 'comments'),
            array('category', 'categories'),
            array('tag', 'tags'),
            array('entity', 'entities'),
        );
    }

    public static function camelCaseToSeparatorProvider()
    {
        return array(
            array('-', 'HenriqueMoody', 'Henrique-Moody'),
            array(' ', 'AlexandreGaigalas', 'Alexandre Gaigalas'),
            array('_', 'AugustoPascutti', 'Augusto_Pascutti'),
        );
    }


    protected function setUp(): void
    {
        $this->style = $this->getMockForAbstractClass('\Respect\Data\Styles\AbstractStyle');
    }

    protected function tearDown(): void
    {
        $this->style = null;
    }

    /**
     * @dataProvider singularPluralProvider
     */
    public function test_plural_to_singular_and_vice_versa($singular, $plural)
    {
        $pluralToSingular = new \ReflectionMethod($this->style, 'pluralToSingular');
        $this->assertEquals($singular,  $pluralToSingular->invoke($this->style, $plural));

        $singularToPlural = new \ReflectionMethod($this->style, 'singularToPlural');
        $this->assertEquals($plural,    $singularToPlural->invoke($this->style, $singular));
    }

    /**
     * @dataProvider camelCaseToSeparatorProvider
     */
    public function test_camel_case_to_separator_and_vice_versa($separator, $camelCase, $separated)
    {
        $camelCaseToSeparatorMethod = new \ReflectionMethod($this->style, 'camelCaseToSeparator');
        
        $this->assertEquals($separated,  $camelCaseToSeparatorMethod->invoke($this->style, $camelCase, $separator));

        $separatorToCamelCaseMethod = new \ReflectionMethod($this->style, 'separatorToCamelCase');
        $this->assertEquals($camelCase, $separatorToCamelCaseMethod->invoke($this->style, $separated, $separator));
    }

}

