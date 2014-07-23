<?php

namespace Respect\Relational;

class SqlStaticTest extends \PHPUnit_Framework_TestCase
{

        public function testStaticSelectWhereOr()
        {
            $data = array('other_column' => '456');
            $sql  = (string) Sql::or($data);
            $this->assertEquals("OR other_column = ?", $sql);
        }

}

