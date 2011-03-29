<?php

namespace Respect\Relational;

class FinderTest extends \PHPUnit_Framework_TestCase
{

    public function testBasicStatement()
    {
        $f = new Finder('user');
        $x = $f->comment[12](new Finder('author'))->like->foo->bar->baz;

        foreach (FinderIterator::recursive($f) as $a)
            echo $a->getEntityReference();
    }

}
