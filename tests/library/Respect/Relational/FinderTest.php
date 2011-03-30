<?php

namespace Respect\Relational;

class FinderTest extends \PHPUnit_Framework_TestCase
{

    public function testBasicStatement()
    {
        $f = new Finder('like');
        $x = $f->comment->user[12];

        foreach (FinderIterator::recursive($f) as $x)
            echo $x->getEntityReference() . $x->getParentEntityReference() . PHP_EOL;
    }

}
