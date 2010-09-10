<?php

namespace Respect\Relational;

/**
 * Sql class file
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
 * SQL Builder
 *
 * Builds SQL queries using native PHP code
 *
 * @author    Alexandre Gomes Gaigalas <alexandre@gaigalas.net>
 * @category  Respect
 * @package   Relational
 * @copyright © Alexandre Gomes Gaigalas
 * @license   http://gaigalas.net/license/newbsd/ New BSD License
 * @version   1
 * @method Respect\Relational\Sql select()
 * @method Respect\Relational\Sql insertInto()
 * @method Respect\Relational\Sql update()
 * @method Respect\Relational\Sql delete()
 * @method Respect\Relational\Sql where()
 * @method Respect\Relational\Sql set()
 * @method Respect\Relational\Sql in()
 * @method Respect\Relational\Sql values()
 * @method Respect\Relational\Sql createTable()
 * @method Respect\Relational\Sql having()
 * @method Respect\Relational\Sql groupBy()
 */
class Sql
{

    /**
     * @var string Current query
     */
    protected $_query = '';
    /**
     * @var array Translated identifier names
     */
    protected $_translation = array();
    /**
     *
     * @var array Column data through the whole query
     */
    protected $_data = array();

    /**
     * Generic method for calling operations
     *
     * @param string $operation Method name for the operation
     * @param array  $parts     Parameters for query parts
     *
     * @return Respect\Relational\Sql
     */
    public function __call($operation, $parts)
    {
        $this->_buildOperation($operation);
        $parts = $this->_normalizeParts($parts);
        $method = '_' . $operation;
        if (!method_exists($this, $method))
            $method = '_buildParts';
        $this->{$method}($parts);
        return $this;
    }

    /**
     * Constructor
     *
     * @param string $rawSql Raw SQL Statement
     */
    public function __construct($rawSql = '')
    {
        $this->_query = $rawSql;
    }

    /**
     * Generic getter for calling operations
     *
     * @param string $operation Method name for the operation
     *
     * @return Respect\Relational\Sql
     */
    public function __get($operation)
    {
        return $this->__call($operation, array());
    }

    /**
     * Wrapper for WHERE operator
     *
     * @param array $parts Query Parts
     */
    protected function _where($parts)
    {
        $this->_buildKeyValues($parts, '%s ', ' AND ');
    }

    /**
     * Wrapper for HAVING operator
     *
     * @param array $parts Query Parts
     */
    protected function _having($parts)
    {
        $this->_buildKeyValues($parts, '%s ', ' AND ');
    }

    /**
     * Wrapper for IN operator
     *
     * @param array $parts Query Parts
     */
    protected function _in($parts)
    {
        $parts = array_map(array($this, '_namefy'), $parts);
        $this->_buildParts($parts, '(:%s) ', ', :');
    }

    /**
     * Wrapper for SET operator
     *
     * @param array $parts Query Parts
     */
    protected function _set($parts)
    {
        $this->_buildKeyValues($parts);
    }

    /**
     * Wrapper for INSERT INTO operator
     *
     * @param array $parts Query Parts
     */
    protected function _insertInto($parts)
    {
        $this->_parseFirstPart($parts);
        $this->_buildParts($parts, '(%s) ');
    }

    /**
     * Wrapper for VALUES operator
     *
     * @param array $parts Query Parts
     */
    protected function _values($parts)
    {
        $parts = array_map(array($this, '_namefy'), $parts);
        $this->_buildParts($parts, '(:%s) ', ', :');
    }

    /**
     * Creates names from column/table/value identifiers
     *
     * @param string $identifier identifier to be namefied
     * @return string
     */
    protected function _namefy($identifier)
    {
        $translated = strtolower(preg_replace('#[^a-zA-Z0-9]#', ' ', $identifier));
        $translated = str_replace(' ', '', ucwords($translated));
        return $this->_translation[$identifier] = $translated;
    }

    /**
     * Wrapper for CREATE TABLE operator
     *
     * @param array $parts Query Parts
     */
    protected function _createTable($parts)
    {
        $this->_parseFirstPart($parts);
        $this->_buildParts($parts, '(%s) ');
    }

    /**
     * Plain-parse the first query
     *
     * @param array $parts Query Parts
     * @return void
     */
    protected function _parseFirstPart(& $parts)
    {
        $this->_query .= array_shift($parts) . ' ';
    }

    /**
     * Builds key/values representation for the query parts
     *
     * @param array  $parts         Query Parts
     * @param string $format        General format (sprintf style) for the
     *                              key/values set
     * @param string $partSeparator Separator for each key/value item
     *
     * @return void
     */
    protected function _buildKeyValues($parts, $format = '%s ', $partSeparator = ', ')
    {
        foreach ($parts as $key => $part) {
            if (is_numeric($key)) {
                $parts[$key] = "$part";
            } else {
                $namifiedPart = $this->_namefy($part);
                $parts[$key] = "$key=:" . $namifiedPart;
            }
        }
        $this->_buildParts($parts, $format, $partSeparator);
    }

    /**
     * Builds the query parts
     *
     * @param array  $parts         Query Parts
     * @param string $format        General format (sprintf style) for the
     *                              parts
     * @param string $partSeparator Separator for each part
     * @return void
     */
    protected function _buildParts($parts, $format = '%s ', $partSeparator = ', ')
    {
        if (empty($parts))
            return;
        $this->_query .= sprintf($format, implode($partSeparator, $parts));
    }

    /**
     * Builds the main SQL operator
     *
     * @param string $operation Operator name
     * @return void
     */
    protected function _buildOperation($operation)
    {
        $command = strtoupper(preg_replace('#[A-Z0-9]+#', ' $0', $operation));
        $this->_query .= trim($command) . ' ';
    }

    /**
     * Normalize the parts arrays into one dimension
     *
     * @param array $parts Query parts
     * @return array Normalized parts
     */
    protected function _normalizeParts($parts)
    {
        $data = & $this->_data;
        $newParts = array();
        array_walk_recursive($parts, function ($value, $key) use ( & $newParts, & $data) {
                if (is_int($key)) {
                    $name = $value;
                    $newParts[] = $name;
                } else {
                    $name = $key;
                    $newParts[$key] = $name;
                    $data[$key] = $value;
                }
            }
        );
        return $newParts;
    }

    /**
     * Returns the query string representation
     *
     * @return string
     */
    public function __toString()
    {
        $q = trim($this->_query);
        $this->_query = '';
        return $q;
    }

    /**
     * Returns the data for query columns
     *
     * @return array
     */
    public function getData()
    {
        $data = array();
        foreach ($this->_data as $k => $v) {
            $data[$this->_translation[$k]] = $v;
        }
        return $data;
    }

}