<?php

namespace Respect\Relational;

class SqlTest extends \PHPUnit_Framework_TestCase
{
    
    protected $object;
    
    protected function setUp()
    {
        $this->object = new Sql;

    }

    public function testSimpleSelect()
    {
        $sql = (string) $this->object->select('*')->from('table');
        $this->assertEquals("SELECT * FROM table", $sql);

    }

    public function testSelectMagicGetDistinctFromHell()
    {
        $sql = (string) $this->object->select->distinct('*')->from('table');
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
    
    public function testSelectWhere()
    {
        $sql = (string) $this->object->select('*')->from('table')->where('column=123');
        $this->assertEquals("SELECT * FROM table WHERE column=123", $sql);

    }
    
    public function testSelectWhereIn()
    {
        $data = array('key' => '123', 'other_key' => '456');
        $sql = (string) $this->object->select('*')->from('table')->where('column')->in($data);
        $this->assertEquals("SELECT * FROM table WHERE column IN (:Key, :OtherKey)", $sql);

    }
    
    public function testSelectWhereArray()
    {
        $data = array('column' => '123', 'other_column' => '456');
        $sql = (string) $this->object->select('*')->from('table')->where($data);
        $this->assertEquals("SELECT * FROM table WHERE column=:Column AND other_column=:OtherColumn", $sql);

    }
    
    public function testSelectWhereArrayQualifiedNames()
    {
        $data = array('a.column' => '123', 'b.other_column' => '456');
        $sql = (string) $this->object->select('*')->from('table a', 'other_table b')->where($data);
        $this->assertEquals("SELECT * FROM table a, other_table b WHERE a.column=:AColumn AND b.other_column=:BOtherColumn", $sql);

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
        $this->assertEquals("SELECT * FROM table GROUP BY column, other_column HAVING other_column=:OtherColumn AND yet_another_column=:YetAnotherColumn", $sql);

    }
    
    public function testSimpleUpdate()
    {
        $data = array('column' => 123, 'column_2' => 234);
        $condition = array('other_column' => 456, 'yet_another_column' => 567);
        $sql = (string) $this->object->update('table')->set($data)->where($condition);
        $this->assertEquals("UPDATE table SET column=:Column, column_2=:Column2 WHERE other_column=:OtherColumn AND yet_another_column=:YetAnotherColumn", $sql);

    }
    
    public function testSimpleInsert()
    {
        $data = array('column' => 123, 'column_2' => 234);
        $sql = (string) $this->object->insertInto('table', $data)->values($data);
        $this->assertEquals("INSERT INTO table (column, column_2) VALUES (:Column, :Column2)", $sql);

    }
    
    public function testSimpleDelete()
    {
        $condition = array('other_column' => 456, 'yet_another_column' => 567);
        $sql = (string) $this->object->deleteFrom('table')->where($condition);
        $this->assertEquals("DELETE FROM table WHERE other_column=:OtherColumn AND yet_another_column=:YetAnotherColumn", $sql);

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
        $this->assertEquals("SELECT column, COUNT(column), other_column FROM table WHERE AES_DECRYPT('pass', 'salt')=:AesDecryptPassSalt", $sql);

    }
    
    public function testAggregateFunctions()
    {
        $where = array('abc' => 10);
        $having = array('SUM(abc)>' =>'10', 'AVG(def)' => 15);
        $sql = (string) $this->object->select('column', 'MAX(def)')->from('table')->where($where)->groupBy('abc', 'def')->having($having);
        $this->assertEquals("SELECT column, MAX(def) FROM table WHERE abc=:Abc GROUP BY abc, def HAVING SUM(abc)>=:SumAbc AND AVG(def)=:AvgDef", $sql);

    }
    
    public function testColumnTranslation()
    {
        $where = array('abc' => 10);
        $having = array('SUM(abc)>10', 'AVG(def)' => 15);
        $sql = (string) $this->object->select('column', 'MAX(def)')->from('table')->where($where)->groupBy('abc', 'def')->having($having);
        $this->assertEquals(array('Abc' => 10, 'AvgDef' => 15), $this->object->getData());
        
    }

}

