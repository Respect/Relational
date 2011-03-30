<?php

namespace Respect\Relational;

class FinderTest extends \PHPUnit_Framework_TestCase
{

    public function testBasicStatement()
    {
        $f = new Finder('like');

        $i = new Schemas\Infered();
        $q = $i->generateQuery($f);

        $this->assertEquals(
            'SELECT like1.* FROM like AS like1',
            (string) $q
        );
    }

    public function testJoinSimple()
    {
        $f = new Finder('like');
        $x = $f->comment->user[12];

        $i = new Schemas\Infered();
        $q = $i->generateQuery($f);

        $this->assertEquals(
            'SELECT like1.*, comment1.*, user1.* FROM like AS like1 INNER JOIN comment AS comment1 ON like1.comment_id = comment1.id INNER JOIN user AS user1 ON comment1.user_id = user1.id',
            (string) $q
        );
    }

    public function testJoinNtoN()
    {
        $f = new Finder('like');
        $x = $f->like_user->user[12];

        $i = new Schemas\Infered();
        $q = $i->generateQuery($f);

        $this->assertEquals(
            'SELECT like1.*, like_user1.*, user1.* FROM like AS like1 INNER JOIN like_user AS like_user1 ON like_user1.like_id = like1.id INNER JOIN user AS user1 ON like_user1.user_id = user1.id',
            (string) $q
        );
    }

}
