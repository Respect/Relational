<?php

namespace Respect\Data\Styles;

class AbstractStyleTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var Respect\Data\Styles\AbstractStyle
     */
    private $style;

    public function singularPluralProvider()
    {
        return array(
            array('post', 'posts'),
            array('comment', 'comments'),
            array('category', 'categories'),
            array('tag', 'tags'),
            array('entity', 'entities'),
        );
    }

    public function camelCaseToSeparatorProvider()
    {
        return array(
            array('-', 'HenriqueMoody', 'Henrique-Moody'),
            array(' ', 'AlexandreGaigalas', 'Alexandre Gaigalas'),
            array('_', 'AugustoPascutti', 'Augusto_Pascutti'),
        );
    }


    public function setUp()
    {
        $this->style = $this->getMockForAbstractClass('\Respect\Data\Styles\AbstractStyle');
    }

    public function tearDown()
    {
        $this->style = null;
    }

    /**
     * @dataProvider singularPluralProvider
     */
    public function test_plural_to_singular_and_vice_versa($singular, $plural)
    {
        $pluralToSingular = new \ReflectionMethod($this->style, 'pluralToSingular');
        $pluralToSingular->setAccessible(true);
        $this->assertEquals($singular,  $pluralToSingular->invoke($this->style, $plural));

        $singularToPlural = new \ReflectionMethod($this->style, 'singularToPlural');
        $singularToPlural->setAccessible(true);
        $this->assertEquals($plural,    $singularToPlural->invoke($this->style, $singular));
    }

    /**
     * @dataProvider camelCaseToSeparatorProvider
     */
    public function test_camel_case_to_separator_and_vice_versa($separator, $camelCase, $separated)
    {
        $camelCaseToSeparatorMethod = new \ReflectionMethod($this->style, 'camelCaseToSeparator');
        $camelCaseToSeparatorMethod->setAccessible(true);
        $this->assertEquals($separated,  $camelCaseToSeparatorMethod->invoke($this->style, $camelCase, $separator));

        $separatorToCamelCaseMethod = new \ReflectionMethod($this->style, 'separatorToCamelCase');
        $separatorToCamelCaseMethod->setAccessible(true);
        $this->assertEquals($camelCase, $separatorToCamelCaseMethod->invoke($this->style, $separated, $separator));
    }

}

