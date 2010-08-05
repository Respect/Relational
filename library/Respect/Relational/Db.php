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
    /**
     *
     * @var PDOStatement
     */
    protected $statement;
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
        $this->statement = null;
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
     * Set options to the connection and statement before fetching
     *
     * @param mixed  $object          Target for pre fetching
     * @param string $constructorArgs Constructor arguments for classname pre
     *                                fetching
     *
     * @return void
     */
    protected function _preFetch($object, $constructorArgs = null)
    {
        $this->statement = $this->connection->prepare($this->_getSqlString());
        if (is_object($object)) {
            $this->statement->setFetchMode(PDO::FETCH_INTO, $object);
            $mode = PDO::FETCH_INTO;
        } elseif (is_string($object)) {
            if (is_null($constructorArgs)) {
                $this->statement->setFetchMode(PDO::FETCH_CLASS, $object);
            } else {
                $this->statement->setFetchMode(PDO::FETCH_CLASS, $object, $constructorArgs);
            }
        } else {
            $this->statement->setFetchMode(PDO::FETCH_NAMED);
        }
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
        $this->_preFetch($object, $extra);
        $this->statement->execute($this->_getSqlData());
        $r = $this->statement->fetch();
        $this->_cleanUp();
        return $r;
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
        $this->_preFetch($object, $extra);
        $this->statement->execute($this->_getSqlData());
        $r = $this->statement->fetchAll();
        $this->_cleanUp();
        return $r;
    }
    
    /**
     * Returns the current statement or null if non existent
     *
     * @return PDOStatement
     */
    public function getStatement()
    {
        return $this->statement;
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
