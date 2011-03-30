<?php

namespace Respect\Relational;

class FinderTest extends \PHPUnit_Framework_TestCase
{

    public function testBasicStatement()
    {
        $f = new Finder('like');
        $x = $f->comment->user[12];

        $s = new Schemas\Infered();
        echo $s->generateQuery($f);
    }

}
