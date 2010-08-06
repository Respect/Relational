<?php

namespace Respect\Relational;

use \PDO as PDO;

/**
 * Db class file
 *
 * PHP version 5.3
 *
 * @author    Alexandre Gomes Gaigalas <alexandre@gaigalas.net>
 * @category  Respect
 * @package   Relational
 * @copyright © Alexandre Gomes Gaigalas
 * @license   http://gaigalas.net/license/newbsd/ New BSD License
 * @version   1
 */

/**
 * Database abstraction
 *
 * Handles database operations
 *
 * @author    Alexandre Gomes Gaigalas <alexandre@gaigalas.net>
 * @category  Respect
 * @package   Relational
 * @copyright © Alexandre Gomes Gaigalas
 * @license   http://gaigalas.net/license/newbsd/ New BSD License
 * @version   1
 * @method Respect\Relational\Db select()
 * @method Respect\Relational\Db insertInto()
 * @method Respect\Relational\Db update()
 * @method Respect\Relational\Db delete()
 * @method Respect\Relational\Db where()
 * @method Respect\Relational\Db set()
 * @method Respect\Relational\Db in()
 * @method Respect\Relational\Db values()
 * @method Respect\Relational\Db createTable()
 * @method Respect\Relational\Db having()
 * @method Respect\Relational\Db groupBy()
 */
class Db
{

    /**
     * @var PDO
     */
    protected $connection;
    protected $sql;

    /**
     * Constructor
     *
     * @param PDO $connection PDO Connection for the database
     */
    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_CASE, PDO::CASE_NATURAL);
        $this->connection->setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_EMPTY_STRING);
        $this->connection->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
        $this->_cleanUp();
    }

    /**
     * Cleans up the object properties for new statements
     */
    protected function _cleanUp()
    {
        $this->sql = new Sql();
    }

    /**
     * Returns the SQL String for the current statement
     *
     * @return string
     */
    protected function _getSqlString()
    {
        return $this->sql->__toString();
    }

    /**
     * Returns the current parameters for the statement
     *
     * @return array
     */
    protected function _getSqlData()
    {
        return $this->sql->getData();
    }

    /**
     * Forwards all the calls to the SQL object
     *
     * @param string $methodName Method name
     * @param array  $arguments  Method arguments
     *
     * @return Respect\Relational\Db
     */
    public function __call($methodName, $arguments)
    {
        $this->sql->__call($methodName, $arguments);
        return $this;
    }

    /**
     * Prepares a query and configures the fetch mode
     *
     * @param mixed  $queryString     SQL to be prepared
     * @param mixed  $object          Target for pre fetching
     * @param string $constructorArgs Constructor arguments for classname pre
     *                                 fetching
     *
     * @return void
     */
    public function prepare($queryString, $object = '\stdClass', $constructorArgs = null)
    {
        $statement = $this->connection->prepare($queryString);
        if (is_object($object)) {
            $statement->setFetchMode(PDO::FETCH_INTO, $object);
            $mode = PDO::FETCH_INTO;
        } elseif (is_string($object)) {
            if (is_null($constructorArgs)) {
                $statement->setFetchMode(PDO::FETCH_CLASS, $object);
            } else {
                $statement->setFetchMode(PDO::FETCH_CLASS, $object, $constructorArgs);
            }
        } else {
            $statement->setFetchMode(PDO::FETCH_NAMED);
        }
        return $statement;
    }

    /**
     * Fetches a single row from the database
     *
     * @param $object Class name or object as fetch target
     * @param $extra  Extra arguments for pre fetching
     *
     * @return stdClass
     */
    public function fetch($object = '\stdClass', $extra = null)
    {
        $statement = $this->prepare($this->_getSqlString(), $object, $extra);
        $statement->execute($this->_getSqlData());
        $result = $statement->fetch();
        $this->_cleanUp();
        return $result;
    }

    /**
     * Fetches all the rows from the database
     *
     * @param $object Class name or object as fetch target
     * @param $extra  Extra arguments for pre fetching
     *
     * @return stdClass
     */
    public function fetchAll($object = '\stdClass', $extra = null)
    {
        $statement = $this->prepare($this->_getSqlString(), $object, $extra);
        $statement->execute($this->_getSqlData());
        $result = $statement->fetchAll();
        $this->_cleanUp();
        return $result;
    }

    /**
     * Queries the database using raw sql
     *
     * @param string $rawSql
     *
     * @return Respect\Relational
     */
    public function query($rawSql)
    {
        $this->sql = new Sql($rawSql);
        return $this;
    }

}
