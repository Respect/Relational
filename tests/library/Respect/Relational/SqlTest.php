<?php

namespace Respect\Relational;

class SqlTest extends \PHPUnit_Framework_TestCase
{

    protected $object;

    protected function setUp()
    {
        $this->object = new Sql;
    }

    public function testCastingObjectToStringReturnsQuery()
    {
        $sql   = $this->object->select('*')->from('table');
        $query = "SELECT * FROM table";
        $this->assertNotSame($query, $sql);
        $this->assertSame($query, (string) $sql);
        $this->assertSame($query, "$sql");
    }

    public function testSimpleSelect()
    {
        $sql = (string) $this->object->select('*')->from('table');
        $this->assertEquals("SELECT * FROM table", $sql);
    }

    public function testSelectMagicGetDistinctFromHell()
    {
        $sql = (string) $this->object->selectDistinct('*')->from('table');
        $this->assertEquals("SELECT DISTINCT * FROM table", $sql);
    }

    public function testSelectColumns()
    {
        $sql = (string) $this->object->select('column', 'other_column')->from('table');
        $this->assertEquals("SELECT column, other_column FROM table", $sql);
    }

    public function testSelectTables()
    {
        $sql = (string) $this->object->select('*')->from('table', 'other_table');
        $this->assertEquals("SELECT * FROM table, other_table", $sql);
    }

    public function testSelectInnerJoin()
    {
        $sql = (string) $this->object->select('*')->from('table')->innerJoin('other_table')->on('table.column = other_table.other_column');
        $this->assertEquals("SELECT * FROM table INNER JOIN other_table ON table.column = other_table.other_column", $sql);
    }

    public function testSelectInnerJoinArr()
    {
        $sql = (string) $this->object->select('*')->from('table')->innerJoin('other_table')->on(array('table.column' => 'other_table.other_column'));
        $this->assertEquals("SELECT * FROM table INNER JOIN other_table ON table.column = other_table.other_column", $sql);
    }

    public function testSelectWhere()
    {
        $sql = (string) $this->object->select('*')->from('table')->where('column=123');
        $this->assertEquals("SELECT * FROM table WHERE column=123", $sql);
    }

    public function testSelectWhereBetween()
    {
        $sql = (string) $this->object->select('*')->from('table')->where('column')->between(1, 2);
        $this->assertEquals("SELECT * FROM table WHERE column BETWEEN 1 AND 2", $sql);
    }

    public function testSelectWhereIn()
    {
        $data = array('key' => '123', 'other_key' => '456');
        $sql = (string) $this->object->select('*')->from('table')->where('column')->in($data);
        $this->assertEquals("SELECT * FROM table WHERE column IN (?, ?)", $sql);
        $this->assertEquals(array_values($data), $this->object->getParams());
    }

    public function testSelectWhereArray()
    {
        $data = array('column' => '123', 'other_column' => '456');
        $sql = (string) $this->object->select('*')->from('table')->where($data);
        $this->assertEquals("SELECT * FROM table WHERE column = ? AND other_column = ?", $sql);
        $this->assertEquals(array_values($data), $this->object->getParams());
    }

    public function testSelectWhereArrayEmptyAnd()
    {
        $data = array('column' => '123', 'other_column' => '456');
        $sql = (string) $this->object->select('*')->from('table')->where($data)->and();
        $this->assertEquals("SELECT * FROM table WHERE column = ? AND other_column = ?", $sql);
        $this->assertEquals(array_values($data), $this->object->getParams());
    }

    public function testSelectWhereOr()
    {
        $data = array('column' => '123');
        $data2 = array('other_column' => '456');
        $sql = (string) $this->object->select('*')->from('table')->where($data)->or($data2);
        $this->assertEquals("SELECT * FROM table WHERE column = ? OR other_column = ?", $sql);
        $this->assertEquals(array_values(array_merge($data, $data2)), $this->object->getParams());
    }

    public function testSelectWhereArrayQualifiedNames()
    {
        $data = array('a.column' => '123', 'b.other_column' => '456');
        $sql = (string) $this->object->select('*')->from('table a', 'other_table b')->where($data);
        $this->assertEquals("SELECT * FROM table a, other_table b WHERE a.column = ? AND b.other_column = ?", $sql);
        $this->assertEquals(array_values($data), $this->object->getParams());
    }

    public function testSelectGroupBy()
    {
        $sql = (string) $this->object->select('*')->from('table')->groupBy('column', 'other_column');
        $this->assertEquals("SELECT * FROM table GROUP BY column, other_column", $sql);
    }

    public function testSelectGroupByHaving()
    {
        $condition = array('other_column' => 456, 'yet_another_column' => 567);
        $sql = (string) $this->object->select('*')->from('table')->groupBy('column', 'other_column')->having($condition);
        $this->assertEquals("SELECT * FROM table GROUP BY column, other_column HAVING other_column = ? AND yet_another_column = ?", $sql);
        $this->assertEquals(array_values($condition), $this->object->getParams());
    }

    public function testSimpleUpdate()
    {
        $data = array('column' => 123, 'column_2' => 234);
        $condition = array('other_column' => 456, 'yet_another_column' => 567);
        $sql = (string) $this->object->update('table')->set($data)->where($condition);
        $this->assertEquals("UPDATE table SET column = ?, column_2 = ? WHERE other_column = ? AND yet_another_column = ?", $sql);
        $this->assertEquals(array_values(array_merge($data, $condition)), $this->object->getParams());
    }

    public function testSimpleInsert()
    {
        $data = array('column' => 123, 'column_2' => 234);
        $sql = (string) $this->object->insertInto('table', $data)->values($data);
        $this->assertEquals("INSERT INTO table (column, column_2) VALUES (?, ?)", $sql);
        $this->assertEquals(array_values($data), $this->object->getParams());
    }

    public function testSimpleDelete()
    {
        $condition = array('other_column' => 456, 'yet_another_column' => 567);
        $sql = (string) $this->object->deleteFrom('table')->where($condition);
        $this->assertEquals("DELETE FROM table WHERE other_column = ? AND yet_another_column = ?", $sql);
        $this->assertEquals(array_values($condition), $this->object->getParams());
    }

    public function testCreateTable()
    {
        $columns = array(
            'column INT',
            'other_column VARCHAR(255)',
            'yet_another_column TEXT'
        );
        $sql = (string) $this->object->createTable('table', $columns);
        $this->assertEquals("CREATE TABLE table (column INT, other_column VARCHAR(255), yet_another_column TEXT)", $sql);
    }

    public function testAlterTable()
    {
        $columns = array(
            'ADD column INT',
            'ADD other_column VARCHAR(255)',
            'ADD yet_another_column TEXT'
        );
        $sql = (string) $this->object->alterTable('table', $columns);
        $this->assertEquals("ALTER TABLE table ADD column INT, ADD other_column VARCHAR(255), ADD yet_another_column TEXT", $sql);
    }

    public function testGrant()
    {
        $sql = (string) $this->object->grant('SELECT', 'UPDATE')->on('table')->to('user', 'other_user');
        $this->assertEquals("GRANT SELECT, UPDATE ON table TO user, other_user", $sql);
    }

    public function testRevoke()
    {
        $sql = (string) $this->object->revoke('SELECT', 'UPDATE')->on('table')->to('user', 'other_user');
        $this->assertEquals("REVOKE SELECT, UPDATE ON table TO user, other_user", $sql);
    }

    public function testComplexFunctions()
    {
        $condition = array("AES_DECRYPT('pass', 'salt')" => 123);
        $sql = (string) $this->object->select('column', 'COUNT(column)', 'other_column')->from('table')->where($condition);
        $this->assertEquals("SELECT column, COUNT(column), other_column FROM table WHERE AES_DECRYPT('pass', 'salt') = ?", $sql);
        $this->assertEquals(array_values($condition), $this->object->getParams());
    }

    /**
     * @ticket 13
     */
    public function testAggregateFunctions()
    {
        $where = array('abc' => 10);
        $having = array('SUM(abc) >=' => '10', 'AVG(def) =' => 15);
        $sql = (string) $this->object->select('column', 'MAX(def)')->from('table')->where($where)->groupBy('abc', 'def')->having($having);
        $this->assertEquals("SELECT column, MAX(def) FROM table WHERE abc = ? GROUP BY abc, def HAVING SUM(abc) >= ? AND AVG(def) = ?", $sql);
        $this->assertEquals(array_values(array_merge($where, $having)), $this->object->getParams());
    }

    public function testStaticBuilderCall()
    {
        $this->assertEquals(
            'ORDER BY updated_at DESC',
            (string) Sql::orderBy('updated_at')->desc()
        );
    }
    public function testLastParameterWithoutParts()
    {
        $this->assertEquals(
            'ORDER BY updated_at DESC',
            $this->object->orderBy('updated_at')->desc()
        );
    }

    public static function provider_sql_operators()
    {
        // $operator, $expectedWhere
        return array(
            array('='),
            array('=='),
            array('<>'),
            array('!='),
            array('>'),
            array('>='),
            array('<'),
            array('<='),
            array('LIKE'),
        );
    }

    /**
     * @ticket 13
     * @dataProvider provider_sql_operators
     */
    public function test_sql_operators($operator, $expected=null)
    {
        $expected = $expected ?: ' ?';
        $where    = array('id '.$operator => 10);
        $sql      = (string) $this->object->select('*')->from('table')->where($where);
        $this->assertEquals('SELECT * FROM table WHERE id '.$operator.$expected, $sql);
    }

    public function testSetQueryWithParams()
    {
        $query = 'SELECT * FROM table WHERE a > ? AND b = ?';
        $params = array(1, 'foo');

        $sql = (string) $this->object->setQuery($query, $params);
        $this->assertEquals($query, $sql);
        $this->assertEquals($params, $this->object->getParams());

        $sql = (string) $this->object->setQuery('', array());
        $this->assertEmpty($sql);
        $this->assertEmpty($this->object->getParams());
    }

    public function testSetQueryWithParamsViaConstructor()
    {
        $query = 'SELECT * FROM table WHERE a > ? AND b = ?';
        $params = array(1, 'foo');

        $sql = new Sql($query, $params);
        $this->assertEquals($query, (string) $sql);
        $this->assertEquals($params, $sql->getParams());
    }

    public function testAppendQueryWithParams()
    {
        $query = 'SELECT * FROM table WHERE a > ? AND b = ?';
        $this->object->setQuery('SELECT * FROM table WHERE a > ? AND b = ?', array(1, 'foo'));
        $sql = (string) $this->object->appendQuery('AND c = ?', array(2));

        $this->assertEquals($query.' AND c = ?', $sql);
        $this->assertEquals(array(1, 'foo', 2), $this->object->getParams());
    }

    public function testSelectWhereWithRepeatedReferences()
    {
        $data1 = array('a >' => 1, 'b' => 'foo');
        $data2 = array('a >' => 4);
        $data3 = array('b' => 'bar');

        $sql = (string) $this->object->select('*')->from('table')->where($data1)->or($data2)->and($data3);
        $this->assertEquals("SELECT * FROM table WHERE a > ? AND b = ? OR a > ? AND b = ?", $sql);
        $this->assertEquals(array(1, 'foo', 4, 'bar'), $this->object->getParams());
    }

    public function testSelectWhereWithConditionsGroupedByUnderscores()
    {
        $data = array(array('a' => 1), array('b' => 2), array('c' => 3), array('d' => 4));

        $sql = (string) $this->object->select('*')->from('table')->where($data[0])->and_($data[1])->or($data[2])->_();
        $this->assertEquals("SELECT * FROM table WHERE a = ? AND (b = ? OR c = ?)", $sql);
        $this->assertEquals(array(1, 2, 3), $this->object->getParams());
        $this->object->setQuery('', array());

        $sql = (string) $this->object->select('*')->from('table')->where_($data[0])->or($data[1])->_()->and_($data[2])->or($data[3])->_();
        $this->assertEquals("SELECT * FROM table WHERE (a = ? OR b = ?) AND (c = ? OR d = ?)", $sql);
        $this->assertEquals(array(1, 2, 3, 4), $this->object->getParams());
        $this->object->setQuery('', array());

        $sql = (string) $this->object->select('*')->from('table')->where($data[0])->and_($data[1])->or_($data[2])->and($data[3])->_()->_();
        $this->assertEquals("SELECT * FROM table WHERE a = ? AND (b = ? OR (c = ? AND d = ?))", $sql);
        $this->assertEquals(array(1, 2, 3, 4), $this->object->getParams());
    }

    public function testSelectWhereWithConditionsGroupedBySubqueries()
    {
        $data = array(array('a' => 1), array('b' => 2), array('c' => 3), array('d' => 4));

        $sql = (string) $this->object->select('*')->from('table')->where($data[0], Sql::cond($data[1])->or($data[2]));
        $this->assertEquals("SELECT * FROM table WHERE a = ? AND (b = ? OR c = ?)", $sql);
        $this->assertEquals(array(1, 2, 3), $this->object->getParams());
        $this->object->setQuery('', array());

        $sql = (string) $this->object->select('*')->from('table')->where(Sql::cond($data[0])->or($data[1]), Sql::cond($data[2])->or($data[3]));
        $this->assertEquals("SELECT * FROM table WHERE (a = ? OR b = ?) AND (c = ? OR d = ?)", $sql);
        $this->assertEquals(array(1, 2, 3, 4), $this->object->getParams());
        $this->object->setQuery('', array());

        $sql = (string) $this->object->select('*')->from('table')->where($data[0], Sql::cond($data[1])->or(Sql::cond($data[2], $data[3])));
        $this->assertEquals("SELECT * FROM table WHERE a = ? AND (b = ? OR (c = ? AND d = ?))", $sql);
        $this->assertEquals(array(1, 2, 3, 4), $this->object->getParams());
    }

    public function testSelectWhereWithSubquery()
    {
        $subquery = Sql::select('column1')->from('t2')->where(array('column2' => 2));
        $sql = (string) $this->object->select('column1')->from('t1')->where(array('column1' => $subquery, 'column2' => 'foo'));

        $this->assertEquals("SELECT column1 FROM t1 WHERE column1 = (SELECT column1 FROM t2 WHERE column2 = ?) AND column2 = ?", $sql);
        $this->assertEquals(array(2, 'foo'), $this->object->getParams());
    }

    public function testSelectWhereWithNestedSubqueries()
    {
        $subquery1 = Sql::select('column1')->from('t3')->where(array('column3' => 3));
        $subquery2 = Sql::select('column1')->from('t2')->where(array('column2' => $subquery1, 'column3' => 'foo'));
        $sql = (string) $this->object->select('column1')->from('t1')->where(array('column1' => $subquery2));

        $this->assertEquals("SELECT column1 FROM t1 WHERE column1 = (SELECT column1 FROM t2 WHERE column2 = (SELECT column1 FROM t3 WHERE column3 = ?) AND column3 = ?)", $sql);
        $this->assertEquals(array(3, 'foo'), $this->object->getParams());
    }

    public function testSelectUsingAliasedColumns()
    {
        $sql = (string) $this->object->select('f1', array('alias' => 'f2'), 'f3', array('another_alias' => 'f4'))->from('table');
        $this->assertEquals("SELECT f1, f2 AS alias, f3, f4 AS another_alias FROM table", $sql);
        $this->assertEmpty($this->object->getParams());
    }

    public function testSelectWithColumnAsSubquery()
    {
        $subquery = Sql::select('f1')->from('t2')->where(array('f2' => 2));
        $sql = (string) $this->object->select('f1', array('subalias' => $subquery))->from('t1')->where(array('f2' => 'foo'));

        $this->assertEquals("SELECT f1, (SELECT f1 FROM t2 WHERE f2 = ?) AS subalias FROM t1 WHERE f2 = ?", $sql);
        $this->assertEquals(array(2, 'foo'), $this->object->getParams());
    }

    public function testInsertWithValueFunctions()
    {
        $data = array('column' => 123, 'column_2' => 234);
        $sql = (string) $this->object->insertInto('table', $data, 'date')->values($data, 'NOW()');
        $this->assertEquals("INSERT INTO table (column, column_2, date) VALUES (?, ?, NOW())", $sql);
        $this->assertEquals(array_values($data), $this->object->getParams());
    }

    public function testInsertWithSelectSubquery()
    {
        $data = array('f3' => 3, 'f4' => 4);
        $subquery = Sql::select('f1', 'f2')->from('t2')->where($data);
        $sql = (string) $this->object->insertInto('t1', array('f1', 'f2'))->appendQuery($subquery);

        $this->assertEquals("INSERT INTO t1 (f1, f2) SELECT f1, f2 FROM t2 WHERE f3 = ? AND f4 = ?", $sql);
        $this->assertEquals(array_values($data), $this->object->getParams());
    }
}
