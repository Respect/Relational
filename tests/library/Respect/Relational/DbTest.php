<?php

namespace Respect\Relational;

class DbTest extends \PHPUnit_Framework_TestCase
{

    protected $object;

    protected function setUp()
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

    protected function tearDown()
    {
        unset($this->object);
    }

    public function testBasicStatement()
    {
        $this->assertEquals(
            'unit',
            $this->object->select('*')->from('sqlite_master')->fetch()->tbl_name
        );
    }

    public function testPassingValues()
    {
        $line = $this->object->select('*')->from('unit')->where(array('testb' => 'abc'))->fetch();
        $this->assertEquals(10, $line->testa);
    }

    public function testFetchingAll()
    {
        $all = $this->object->select('*')->from('unit')->fetchAll();
        $this->assertEquals(3, count($all));
    }

    public function testFetchingClass()
    {
        $line = $this->object->select('*')->from('unit')->fetch('Respect\Relational\testFetchingClass');
        $this->assertInstanceOF('Respect\Relational\testFetchingClass', $line);
    }

    public function testFetchingClassArgs()
    {
        $line = $this->object->select('*')->from('unit')->fetch('Respect\Relational\testFetchingClassArgs',
                array('foo'));
        $this->assertInstanceOF('Respect\Relational\testFetchingClassArgs', $line);
        $this->assertEquals('foo', $line->testd);
    }

    public function testFetchingCallback()
    {
        $line = $this->object->select('*')->from('unit')->fetch(
                function($row) {
                    $row->acid = 'test';
                    return $row;
                }
        );
        $this->assertEquals('test', $line->acid);
    }

    public function testFetchingInto()
    {
        $x = new testFetchingInto;
        $line = $this->object->select('*')->from('unit')->where(array('testb' => 'abc'))->fetch($x);
        $this->assertEquals('abc', $x->testb);
    }

    public function testRawSql()
    {
        $all = $this->object->query('select * from unit')->fetchAll();
        $this->assertEquals(3, count($all));
    }

    public function testFetchingArray()
    {
        $line = $this->object->select('*')->from('unit')->where(array('testb' => 'abc'))->fetch(null);
        $this->assertTrue(is_array($line));
    }

}

class testFetchingClass
{
    
}

class testFetchingInto
{

    public $testa, $testb, $testz;

}

class testFetchingClassArgs
{

    public $testd;

    public function __construct($testd)
    {
        $this->testd = $testd;
    }

}