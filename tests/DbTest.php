<?php

declare(strict_types=1);

namespace Respect\Relational;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Db::class)]
class DbTest extends TestCase
{

    protected $object;

    protected function setUp(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers())) {
            $this->markTestSkipped('PDO_SQLITE is not available');
        }
        $db = new \PDO('sqlite::memory:');
        $db->query('CREATE TABLE unit (testez INTEGER PRIMARY KEY AUTOINCREMENT, testa INT, testb VARCHAR(255))');
        $db->query("INSERT INTO unit (testa, testb) VALUES (10, 'abc')");
        $db->query("INSERT INTO unit (testa, testb) VALUES (20, 'def')");
        $db->query("INSERT INTO unit (testa, testb) VALUES (30, 'ghi')");
        $this->object = new Db($db);
    }

    protected function tearDown(): void
    {
        unset($this->object);
    }

    public function testBasicStatement(): void
    {
        $this->assertEquals(
            'unit',
            $this->object->select('*')->from('sqlite_master')->fetch()->tbl_name
        );
    }

    public function testPassingValues(): void
    {
        $line = $this->object->select('*')->from('unit')->where(array('testb' => 'abc'))->fetch();
        $this->assertEquals(10, $line->testa);
    }

    public function testFetchingAll(): void
    {
        $all = $this->object->select('*')->from('unit')->fetchAll();
        $this->assertEquals(3, count($all));
    }

    public function testFetchingClass(): void
    {
        $line = $this->object->select('*')->from('unit')->fetch('Respect\Relational\testFetchingClass');
        $this->assertInstanceOF('Respect\Relational\testFetchingClass', $line);
    }

    public function testFetchingClassArgs(): void
    {
        $line = $this->object->select('*')->from('unit')->fetch('Respect\Relational\testFetchingClassArgs',
                array('foo'));
        $this->assertInstanceOF('Respect\Relational\testFetchingClassArgs', $line);
        $this->assertEquals('foo', $line->testd);
    }

    public function testFetchingCallback(): void
    {
        $line = $this->object->select('*')->from('unit')->fetch(
                function($row) {
                    $row->acid = 'test';
                    return $row;
                }
        );
        $this->assertEquals('test', $line->acid);
    }

    public function testFetchingInto(): void
    {
        $x = new testFetchingInto;
        $line = $this->object->select('*')->from('unit')->where(array('testb' => 'abc'))->fetch($x);
        $this->assertEquals('abc', $x->testb);
    }

    public function testRawSql(): void
    {
        $all = $this->object->query('select * from unit')->fetchAll();
        $this->assertEquals(3, count($all));
    }

    public function testFetchingArray(): void
    {
        $line = $this->object->select('*')->from('unit')->where(array('testb' => 'abc'))->fetch(\PDO::FETCH_ASSOC);
        $this->assertTrue(is_array($line));
    }

    public function testFetchingArray2(): void
    {
        $line = $this->object->select('*')->from('unit')->where(array('testb' => 'abc'))->fetch(array());
        $this->assertTrue(is_array($line));
    }

    public function testGetSql(): void
    {
        $sql = $this->object->select('*')->from('unit')->where(array('testb' => 'abc'))->getSql();
        $this->assertEquals('SELECT * FROM unit WHERE testb = ?', (string) $sql);
        $this->assertEquals(array('abc'), $sql->getParams());
    }

    public function testRawSqlWithParams(): void
    {
        $line = $this->object->query('SELECT * FROM unit WHERE testb = ?', array('abc'))->fetch();
        $this->assertEquals(10, $line->testa);
    }
}

class testFetchingClass
{
    public $testa, $testb, $testez;
}

class testFetchingInto
{
    public $testa, $testb, $testez;
}

class testFetchingClassArgs
{
    public $testd, $testa, $testb, $testez;

    public function __construct($testd)
    {
        $this->testd = $testd;
    }

}
