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
        $line = $this->object->select('*')->from('unit')
            ->where([['testb', '=', 'abc']])->fetch();
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
        $this->object->select('*')->from('unit')
            ->where([['testb', '=', 'abc']])->fetch($x);
        $this->assertEquals('abc', $x->testb);
    }

    public function testFluentSelect(): void
    {
        $all = $this->object->select('*')->from('unit')->fetchAll();
        $this->assertEquals(3, count($all));
    }

    public function testFetchingArray(): void
    {
        $line = $this->object->select('*')->from('unit')
            ->where([['testb', '=', 'abc']])->fetch(PDO::FETCH_ASSOC);
        $this->assertTrue(is_array($line));
    }

    public function testFetchingArray2(): void
    {
        $line = $this->object->select('*')->from('unit')
            ->where([['testb', '=', 'abc']])->fetch([]);
        $this->assertTrue(is_array($line));
    }

    public function testGetSql(): void
    {
        $sql = $this->object->select('*')->from('unit')
            ->where([['testb', '=', 'abc']])->getSql();
        $this->assertEquals('SELECT * FROM unit WHERE testb = ?', (string) $sql);
        $this->assertEquals(['abc'], $sql->getParams());
    }

    public function testFluentSelectWithParams(): void
    {
        $line = $this->object->select('*')->from('unit')
            ->where([['testb', '=', 'abc']])->fetch();
        $this->assertEquals(10, $line->testa);
    }

    public function testExecReturnsTrueOnSuccess(): void
    {
        $result = $this->object->insertInto('unit', ['testa', 'testb'])
            ->values([40, 'jkl'])
            ->exec();
        $this->assertTrue($result);
    }

    public function testGetConnectionReturnsPdoInstance(): void
    {
        $connection = $this->object->getConnection();
        $this->assertInstanceOf(PDO::class, $connection);
    }

    public function testFetchAllWithCallback(): void
    {
        $all = $this->object->select('*')->from('unit')->fetchAll(
            static function ($row) {
                $row->extra = 'callback';

                return $row;
            },
        );
        $this->assertCount(3, $all);
        $this->assertEquals('callback', $all[0]->extra);
    }

    protected function tearDown(): void
    {
        unset($this->object);
    }
}
