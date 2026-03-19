<?php

declare(strict_types=1);

namespace Respect\Relational;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Sql::class)]
#[CoversClass(SqlException::class)]
class SqlTest extends TestCase
{
    protected Sql $object;

    protected function setUp(): void
    {
        $this->object = new Sql();
    }

    public function testCastingObjectToStringReturnsQuery(): void
    {
        $sql   = $this->object->select('*')->from('table');
        $query = 'SELECT * FROM table';
        $this->assertNotSame($query, $sql);
        $this->assertSame($query, (string) $sql);
    }

    public function testSimpleSelect(): void
    {
        $sql = (string) $this->object->select('*')->from('table');
        $this->assertEquals('SELECT * FROM table', $sql);
    }

    public function testSelectMagicGetDistinctFromHell(): void
    {
        $sql = (string) $this->object->selectDistinct('*')->from('table');
        $this->assertEquals('SELECT DISTINCT * FROM table', $sql);
    }

    public function testSelectColumns(): void
    {
        $sql = (string) $this->object->select('column', 'other_column')->from('table');
        $this->assertEquals('SELECT column, other_column FROM table', $sql);
    }

    public function testSelectTables(): void
    {
        $sql = (string) $this->object->select('*')->from('table', 'other_table');
        $this->assertEquals('SELECT * FROM table, other_table', $sql);
    }

    public function testSelectUsingAliasedColumns(): void
    {
        $sql = (string) $this->object->select(
            'f1',
            ['alias' => 'f2'],
            'f3',
            ['another_alias' => 'f4'],
        )->from('table');
        $this->assertEquals(
            'SELECT f1, f2 AS alias, f3, f4 AS another_alias FROM table',
            $sql,
        );
        $this->assertEmpty($this->object->params);
    }

    public function testSelectWithAggregateFunctions(): void
    {
        $sql = (string) $this->object->select('column', 'COUNT(column)', 'SUM(amount)')
            ->from('table');
        $this->assertEquals('SELECT column, COUNT(column), SUM(amount) FROM table', $sql);
    }

    public function testSelectInnerJoin(): void
    {
        $sql = (string) $this->object->select('*')->from('table')
            ->innerJoin('other_table')
            ->on(['table.column' => 'other_table.other_column']);
        $this->assertEquals(
            'SELECT * FROM table INNER JOIN other_table ON table.column = other_table.other_column',
            $sql,
        );
    }

    public function testWhereWithAndConditions(): void
    {
        $sql = (string) $this->object->select('*')->from('table')->where([
            ['column', '=', '123'],
            'AND',
            ['other_column', '=', '456'],
        ]);
        $this->assertEquals(
            'SELECT * FROM table WHERE column = ? AND other_column = ?',
            $sql,
        );
        $this->assertEquals(['123', '456'], $this->object->params);
    }

    public function testWhereWithOrConditions(): void
    {
        $sql = (string) $this->object->select('*')->from('table')->where([
            ['column', '=', '123'],
            'OR',
            ['other_column', '=', '456'],
        ]);
        $this->assertEquals(
            'SELECT * FROM table WHERE column = ? OR other_column = ?',
            $sql,
        );
        $this->assertEquals(['123', '456'], $this->object->params);
    }

    public function testWhereWithNestedGroupedConditions(): void
    {
        $sql = (string) $this->object->select('*')->from('table')->where([
            ['foo', '=', 'baz'],
            'OR',
            [
                ['xoo', '=', 'qux'],
                'AND',
                ['xoo', '=', 'zap'],
            ],
        ]);
        $this->assertEquals(
            'SELECT * FROM table WHERE foo = ? OR (xoo = ? AND xoo = ?)',
            $sql,
        );
        $this->assertEquals(['baz', 'qux', 'zap'], $this->object->params);
    }

    public function testSelectWhereArrayQualifiedNames(): void
    {
        $sql = (string) $this->object->select('*')
            ->from('table a', 'other_table b')
            ->where([
                ['a.column', '=', '123'],
                'AND',
                ['b.other_column', '=', '456'],
            ]);
        $this->assertEquals(
            'SELECT * FROM table a, other_table b WHERE a.column = ? AND b.other_column = ?',
            $sql,
        );
        $this->assertEquals(['123', '456'], $this->object->params);
    }

    public function testWhereIn(): void
    {
        $data = ['123', '456'];
        $sql = (string) $this->object->select('*')->from('table')
            ->where([['column', 'IN', $data]]);
        $this->assertEquals('SELECT * FROM table WHERE column IN (?, ?)', $sql);
        $this->assertEquals($data, $this->object->params);
    }

    public function testWhereBetween(): void
    {
        $sql = (string) $this->object->select('*')->from('table')
            ->where([['column', 'BETWEEN', [1, 100]]]);
        $this->assertEquals('SELECT * FROM table WHERE column BETWEEN ? AND ?', $sql);
        $this->assertEquals([1, 100], $this->object->params);
    }

    public function testWhereInWithCompoundConditions(): void
    {
        $sql = (string) $this->object->select('*')->from('table')->where([
            ['column', 'IN', ['a', 'b', 'c']],
            'AND',
            ['other', '=', 'foo'],
        ]);
        $this->assertEquals(
            'SELECT * FROM table WHERE column IN (?, ?, ?) AND other = ?',
            $sql,
        );
        $this->assertEquals(['a', 'b', 'c', 'foo'], $this->object->params);
    }

    /** @return array<int, array<int, string>> */
    public static function providerSqlOperators(): array
    {
        return [
            ['='],
            ['=='],
            ['<>'],
            ['!='],
            ['>'],
            ['>='],
            ['<'],
            ['<='],
            ['LIKE'],
            ['NOT LIKE'],
        ];
    }

    #[DataProvider('providerSqlOperators')]
    public function testAllComparisonOperators(string $operator): void
    {
        $sql = (string) $this->object->select('*')->from('table')
            ->where([['id', $operator, '10']]);
        $this->assertEquals('SELECT * FROM table WHERE id ' . $operator . ' ?', $sql);
        $this->assertEquals(['10'], $this->object->params);
    }

    public function testMixedOperatorsInCompoundConditions(): void
    {
        $sql = (string) $this->object->select('*')->from('table')->where([
            ['age', '>=', '18'],
            'AND',
            ['name', 'LIKE', '%foo%'],
            'AND',
            ['status', '!=', 'banned'],
        ]);
        $this->assertEquals(
            'SELECT * FROM table WHERE age >= ? AND name LIKE ? AND status != ?',
            $sql,
        );
        $this->assertEquals(['18', '%foo%', 'banned'], $this->object->params);
    }

    public function testSelectGroupBy(): void
    {
        $sql = (string) $this->object->select('*')->from('table')->groupBy('column', 'other_column');
        $this->assertEquals('SELECT * FROM table GROUP BY column, other_column', $sql);
    }

    public function testGroupByWithHaving(): void
    {
        $sql = (string) $this->object->select('*')->from('table')
            ->groupBy('column', 'other_column')
            ->having([
                ['other_column', '=', 456],
                'AND',
                ['yet_another_column', '=', 567],
            ]);
        $this->assertEquals(
            'SELECT * FROM table GROUP BY column, other_column'
            . ' HAVING other_column = ? AND yet_another_column = ?',
            $sql,
        );
        $this->assertEquals([456, 567], $this->object->params);
    }

    public function testHavingWithAggregateOperators(): void
    {
        $sql = (string) $this->object->select('column', 'MAX(def)')->from('table')
            ->where([['abc', '=', '10']])
            ->groupBy('abc', 'def')
            ->having([
                ['SUM(abc)', '>=', '10'],
                'AND',
                ['AVG(def)', '=', 15],
            ]);
        $this->assertEquals(
            'SELECT column, MAX(def) FROM table WHERE abc = ?'
            . ' GROUP BY abc, def HAVING SUM(abc) >= ? AND AVG(def) = ?',
            $sql,
        );
        $this->assertEquals(['10', '10', 15], $this->object->params);
    }

    public function testSimpleUpdate(): void
    {
        $data = ['column' => 123, 'column_2' => 234];
        $sql = (string) $this->object->update('table')->set($data)->where([
            ['other_column', '=', 456],
            'AND',
            ['yet_another_column', '=', 567],
        ]);
        $this->assertEquals(
            'UPDATE table SET column = ?, column_2 = ?'
            . ' WHERE other_column = ? AND yet_another_column = ?',
            $sql,
        );
        $this->assertEquals([123, 234, 456, 567], $this->object->params);
    }

    public function testSetWithRawExpression(): void
    {
        $sql = (string) $this->object->update('table')
            ->set(['counter' => Sql::raw('counter + 1'), 'updated_at' => Sql::raw('NOW()')])
            ->where([['id', '=', '5']]);
        $this->assertEquals(
            'UPDATE table SET counter = counter + 1, updated_at = NOW() WHERE id = ?',
            $sql,
        );
        $this->assertEquals(['5'], $this->object->params);
    }

    public function testSetWithSubquery(): void
    {
        $sub = Sql::select('MAX(score)')->from('scores')->where([['active', '=', '1']]);
        $sql = (string) $this->object->update('table')
            ->set(['high_score' => $sub])
            ->where([['id', '=', '5']]);
        $this->assertEquals(
            'UPDATE table SET high_score = (SELECT MAX(score) FROM scores WHERE active = ?) WHERE id = ?',
            $sql,
        );
        $this->assertEquals(['1', '5'], $this->object->params);
    }

    public function testSimpleInsert(): void
    {
        $sql = (string) $this->object->insertInto('table', ['column', 'column_2'])
            ->values([123, 234]);
        $this->assertEquals('INSERT INTO table (column, column_2) VALUES (?, ?)', $sql);
        $this->assertEquals([123, 234], $this->object->params);
    }

    public function testInsertWithRawValues(): void
    {
        $sql = (string) $this->object->insertInto('table', ['column', 'column_2', 'date'])
            ->values([123, 234, Sql::raw('NOW()')]);
        $this->assertEquals(
            'INSERT INTO table (column, column_2, date) VALUES (?, ?, NOW())',
            $sql,
        );
        $this->assertEquals([123, 234], $this->object->params);
    }

    public function testInsertWithSelectSubquery(): void
    {
        $subquery = Sql::select('f1', 'f2')->from('t2')->where([
            ['f3', '=', 3],
            'AND',
            ['f4', '=', 4],
        ]);
        $sql = (string) $this->object->insertInto('t1', ['f1', 'f2'])->concat($subquery);

        $this->assertEquals(
            'INSERT INTO t1 (f1, f2) SELECT f1, f2 FROM t2 WHERE f3 = ? AND f4 = ?',
            $sql,
        );
        $this->assertEquals([3, 4], $this->object->params);
    }

    public function testSimpleDelete(): void
    {
        $sql = (string) $this->object->deleteFrom('table')->where([
            ['other_column', '=', 456],
            'AND',
            ['yet_another_column', '=', 567],
        ]);
        $this->assertEquals(
            'DELETE FROM table WHERE other_column = ? AND yet_another_column = ?',
            $sql,
        );
        $this->assertEquals([456, 567], $this->object->params);
    }

    public function testWhereWithSubquery(): void
    {
        $subquery = Sql::select('column1')->from('t2')->where([['column2', '=', 2]]);
        $sql = (string) $this->object->select('column1')->from('t1')
            ->where([['column1', '=', $subquery], 'AND', ['column2', '=', 'foo']]);

        $this->assertEquals(
            'SELECT column1 FROM t1 WHERE column1 = (SELECT column1 FROM t2 WHERE column2 = ?)'
            . ' AND column2 = ?',
            $sql,
        );
        $this->assertEquals([2, 'foo'], $this->object->params);
    }

    public function testNestedSubqueries(): void
    {
        $subquery1 = Sql::select('column1')->from('t3')->where([['column3', '=', 3]]);
        $subquery2 = Sql::select('column1')->from('t2')
            ->where([['column2', '=', $subquery1], 'AND', ['column3', '=', 'foo']]);
        $sql = (string) $this->object->select('column1')->from('t1')
            ->where([['column1', '=', $subquery2]]);

        $this->assertEquals(
            'SELECT column1 FROM t1 WHERE column1 = (SELECT column1 FROM t2'
            . ' WHERE column2 = (SELECT column1 FROM t3 WHERE column3 = ?) AND column3 = ?)',
            $sql,
        );
        $this->assertEquals([3, 'foo'], $this->object->params);
    }

    public function testSelectColumnAsSubquery(): void
    {
        $subquery = Sql::select('f1')->from('t2')->where([['f2', '=', 2]]);
        $sql = (string) $this->object->select('f1', ['subalias' => $subquery])
            ->from('t1')->where([['f2', '=', 'foo']]);

        $this->assertEquals(
            'SELECT f1, (SELECT f1 FROM t2 WHERE f2 = ?) AS subalias FROM t1 WHERE f2 = ?',
            $sql,
        );
        $this->assertEquals([2, 'foo'], $this->object->params);
    }

    public function testWhereWithFunctionColumn(): void
    {
        $sql = (string) $this->object->select('column', 'COUNT(column)', 'other_column')
            ->from('table')->where([["AES_DECRYPT('pass', 'salt')", '=', 123]]);
        $this->assertEquals(
            "SELECT column, COUNT(column), other_column FROM table WHERE AES_DECRYPT('pass', 'salt') = ?",
            $sql,
        );
        $this->assertEquals([123], $this->object->params);
    }

    public function testCreateTable(): void
    {
        $sql = (string) $this->object->createTable('users', [
            ['id', 'INT', 'PRIMARY KEY'],
            ['name', 'VARCHAR(255)'],
            ['email', 'VARCHAR(255)'],
        ]);
        $this->assertEquals(
            'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))',
            $sql,
        );
    }

    public function testCreateTableIfNotExists(): void
    {
        $sql = (string) Sql::createTableIfNotExists('users', [['id', 'INT', 'PRIMARY KEY']]);
        $this->assertEquals('CREATE TABLE IF NOT EXISTS users (id INT PRIMARY KEY)', $sql);
    }

    public function testCreateTableWithConstraints(): void
    {
        $sql = (string) Sql::createTable('posts', [
            ['id', 'INT', 'PRIMARY KEY'],
            ['author_id', 'INT', 'NOT NULL'],
            'FOREIGN KEY (author_id) REFERENCES authors(id)',
        ]);
        $this->assertEquals(
            'CREATE TABLE posts (id INT PRIMARY KEY, author_id INT NOT NULL,'
            . ' FOREIGN KEY (author_id) REFERENCES authors(id))',
            $sql,
        );
    }

    public function testCreateTableWithEngineViaConcat(): void
    {
        $sql = (string) Sql::createTable('users', [
            ['id', 'INT', 'PRIMARY KEY', 'AUTO_INCREMENT'],
            ['name', 'VARCHAR(255)', 'NOT NULL'],
        ])->concat(Sql::raw('ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'));
        $this->assertEquals(
            'CREATE TABLE users (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255) NOT NULL)'
            . ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            $sql,
        );
    }

    public function testDropTable(): void
    {
        $sql = (string) Sql::dropTable('users');
        $this->assertEquals('DROP TABLE users', $sql);
    }

    public function testDropTableIfExists(): void
    {
        $sql = (string) Sql::dropTableIfExists('users');
        $this->assertEquals('DROP TABLE IF EXISTS users', $sql);
    }

    public function testTruncateTable(): void
    {
        $sql = (string) Sql::truncateTable('users');
        $this->assertEquals('TRUNCATE TABLE users', $sql);
    }

    public function testAlterTableChained(): void
    {
        $sql = (string) Sql::alterTable('users')
            ->addColumn('email VARCHAR(255)')
            ->addColumn('age INT')
            ->dropColumn('old_col');
        $this->assertEquals(
            'ALTER TABLE users ADD COLUMN email VARCHAR(255) ADD COLUMN age INT DROP COLUMN old_col',
            $sql,
        );
    }

    public function testCreateIndex(): void
    {
        $sql = (string) Sql::createIndex('idx_email')->on('users', ['email']);
        $this->assertEquals('CREATE INDEX idx_email ON users (email)', $sql);
    }

    public function testCreateUniqueIndex(): void
    {
        $sql = (string) Sql::createUniqueIndex('idx_email')->on('users', ['email', 'tenant_id']);
        $this->assertEquals(
            'CREATE UNIQUE INDEX idx_email ON users (email, tenant_id)',
            $sql,
        );
    }

    public function testGrant(): void
    {
        $sql = (string) $this->object->grant('SELECT', 'UPDATE')->on('table')->to('user', 'other_user');
        $this->assertEquals('GRANT SELECT, UPDATE ON table TO user, other_user', $sql);
    }

    public function testRevoke(): void
    {
        $sql = (string) $this->object->revoke('SELECT', 'UPDATE')->on('table')->to('user', 'other_user');
        $this->assertEquals('REVOKE SELECT, UPDATE ON table TO user, other_user', $sql);
    }

    public function testStaticBuilderCall(): void
    {
        $this->assertEquals(
            'ORDER BY updated_at DESC',
            (string) Sql::orderBy('updated_at')->desc(),
        );
    }

    public function testLastParameterWithoutParts(): void
    {
        $this->assertEquals(
            'ORDER BY updated_at DESC',
            (string) $this->object->orderBy('updated_at')->desc(),
        );
    }

    public function testConcat(): void
    {
        $base = Sql::select('*')->from('table')->where([['a', '=', 1]]);
        $extra = Sql::orderBy('b')->desc();
        $base->concat($extra);

        $this->assertEquals(
            'SELECT * FROM table WHERE a = ? ORDER BY b DESC',
            (string) $base,
        );
        $this->assertEquals([1], $base->params);
    }

    public function testConcatMergesParams(): void
    {
        $base = Sql::select('*')->from('t1')->where([['a', '=', 1]]);
        $extra = Sql::select('*')->from('t2')->where([['b', '=', 2]]);
        $base->concat($extra);

        $this->assertEquals([1, 2], $base->params);
    }

    public function testWhereWithNullValue(): void
    {
        $sql = (string) $this->object->select('*')->from('table')
            ->where([['col', '=', null]]);
        $this->assertEquals('SELECT * FROM table WHERE col IS NULL', $sql);
        $this->assertEmpty($this->object->params);
    }

    public function testWhereWithNotEqualNull(): void
    {
        $sql = (string) $this->object->select('*')->from('table')
            ->where([['col', '!=', null]]);
        $this->assertEquals('SELECT * FROM table WHERE col IS NOT NULL', $sql);
        $this->assertEmpty($this->object->params);
    }

    public function testWhereWithNotEqualNullDiamondOperator(): void
    {
        $sql = (string) $this->object->select('*')->from('table')
            ->where([['col', '<>', null]]);
        $this->assertEquals('SELECT * FROM table WHERE col IS NOT NULL', $sql);
    }

    public function testWhereWithNullInCompoundCondition(): void
    {
        $sql = (string) $this->object->select('*')->from('table')->where([
            ['name', '=', 'foo'],
            'AND',
            ['deleted_at', '=', null],
        ]);
        $this->assertEquals(
            'SELECT * FROM table WHERE name = ? AND deleted_at IS NULL',
            $sql,
        );
        $this->assertEquals(['foo'], $this->object->params);
    }

    public function testInsertWithNullValue(): void
    {
        $sql = (string) $this->object->insertInto('table', ['a', 'b', 'c'])
            ->values([1, null, 'foo']);
        $this->assertEquals('INSERT INTO table (a, b, c) VALUES (?, ?, ?)', $sql);
        $this->assertEquals([1, null, 'foo'], $this->object->params);
    }

    public function testSetWithNullValue(): void
    {
        $sql = (string) $this->object->update('table')
            ->set(['col' => null, 'other' => 123])
            ->where([['id', '=', 1]]);
        $this->assertEquals(
            'UPDATE table SET col = ?, other = ? WHERE id = ?',
            $sql,
        );
        $this->assertEquals([null, 123, 1], $this->object->params);
    }

    public function testNullWithUnsupportedOperatorThrows(): void
    {
        $this->expectException(SqlException::class);
        $this->expectExceptionMessage('does not support null');
        (string) Sql::select('*')->from('table')->where([['col', '>', null]]);
    }

    public function testExpandWithUnsupportedOperatorThrows(): void
    {
        $this->expectException(SqlException::class);
        $this->expectExceptionMessage('Unsupported expand operator');
        (string) Sql::select('*')->from('table')->where([['col', 'LIKE', [1, 2]]]);
    }

    public function testBetweenWithWrongArityThrows(): void
    {
        $this->expectException(SqlException::class);
        $this->expectExceptionMessage('BETWEEN requires 2 values');
        (string) Sql::select('*')->from('table')->where([['col', 'BETWEEN', [1]]]);
    }

    public function testInWithEmptyListThrows(): void
    {
        $this->expectException(SqlException::class);
        $this->expectExceptionMessage('requires 1+ values');
        (string) Sql::select('*')->from('table')->where([['col', 'IN', []]]);
    }

    public function testNotInWithEmptyListThrows(): void
    {
        $this->expectException(SqlException::class);
        (string) Sql::select('*')->from('table')->where([['col', 'NOT IN', []]]);
    }
}
