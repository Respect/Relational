<?php

declare(strict_types=1);

namespace Respect\Relational;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function count;
use function in_array;
use function is_array;

#[CoversClass(Db::class)]
class DbTest extends TestCase
{
    protected Db $object;

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers())) {
            $this->markTestSkipped('PDO_SQLITE is not available');
        }

        $db = new PDO('sqlite::memory:');
        $db->query('CREATE TABLE unit (testez INTEGER PRIMARY KEY AUTOINCREMENT, testa INT, testb VARCHAR(255))');
        $db->query("INSERT INTO unit (testa, testb) VALUES (10, 'abc')");
        $db->query("INSERT INTO unit (testa, testb) VALUES (20, 'def')");
        $db->query("INSERT INTO unit (testa, testb) VALUES (30, 'ghi')");
        $this->object = new Db($db);
    }

    public function testBasicStatement(): void
    {
        $this->assertEquals(
            'unit',
            $this->object->select('*')->from('sqlite_master')->fetch()->tbl_name,
        );
    }

    public function testPassingValues(): void
    {
        $line = $this->object->select('*')->from('unit')->where(['testb' => 'abc'])->fetch();
        $this->assertEquals(10, $line->testa);
    }

    public function testFetchingAll(): void
    {
        $all = $this->object->select('*')->from('unit')->fetchAll();
        $this->assertEquals(3, count($all));
    }

    public function testFetchingClass(): void
    {
        $line = $this->object->select('*')->from('unit')->fetch('Respect\Relational\TestFetchingClass');
        $this->assertInstanceOF('Respect\Relational\TestFetchingClass', $line);
    }

    public function testFetchingClassArgs(): void
    {
        $line = $this->object->select('*')->from('unit')->fetch(
            'Respect\Relational\TestFetchingClassArgs',
            ['foo'],
        );
        $this->assertInstanceOF('Respect\Relational\TestFetchingClassArgs', $line);
        $this->assertEquals('foo', $line->testd);
    }

    public function testFetchingCallback(): void
    {
        $line = $this->object->select('*')->from('unit')->fetch(
            static function ($row) {
                $row->acid = 'test';

                return $row;
            },
        );
        $this->assertEquals('test', $line->acid);
    }

    public function testFetchingInto(): void
    {
        $x = new TestFetchingInto();
        $this->object->select('*')->from('unit')->where(['testb' => 'abc'])->fetch($x);
        $this->assertEquals('abc', $x->testb);
    }

    public function testRawSql(): void
    {
        $all = $this->object->query('select * from unit')->fetchAll();
        $this->assertEquals(3, count($all));
    }

    public function testFetchingArray(): void
    {
        $line = $this->object->select('*')->from('unit')
            ->where(['testb' => 'abc'])->fetch(PDO::FETCH_ASSOC);
        $this->assertTrue(is_array($line));
    }

    public function testFetchingArray2(): void
    {
        $line = $this->object->select('*')->from('unit')->where(['testb' => 'abc'])->fetch([]);
        $this->assertTrue(is_array($line));
    }

    public function testGetSql(): void
    {
        $sql = $this->object->select('*')->from('unit')->where(['testb' => 'abc'])->getSql();
        $this->assertEquals('SELECT * FROM unit WHERE testb = ?', (string) $sql);
        $this->assertEquals(['abc'], $sql->getParams());
    }

    public function testRawSqlWithParams(): void
    {
        $line = $this->object->query('SELECT * FROM unit WHERE testb = ?', ['abc'])->fetch();
        $this->assertEquals(10, $line->testa);
    }

    protected function tearDown(): void
    {
        unset($this->object);
    }
}

class TestFetchingClass
{
    public int|null $testa = null;

    public string|null $testb = null;

    public int|null $testez = null;
}

class TestFetchingInto
{
    public int|null $testa = null;

    public string|null $testb = null;

    public int|null $testez = null;
}

class TestFetchingClassArgs
{
    public function __construct(public string|null $testd = null)
    {
    }
}
