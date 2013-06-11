<?php

namespace Respect\Relational;

class Sql
{
    const SQL_OPERATORS = '/\s?(NOT)?\s?(=|==|<>|!=|>|>=|<|<=|LIKE)\s?$/';
    const PLACEHOLDER   = '?';

    protected $query = '';
    protected $params = array();

    public static function __callStatic($operation, $parts)
    {
        $sql = new static;
        return call_user_func_array(array($sql, $operation), $parts);
    }

    public static function enclose($sql)
    {
        if ($sql instanceof self)
            $sql->query = '(' . trim($sql->query) . ') ';
        elseif ($sql != '')
            $sql = '(' . trim($sql) . ') ';

        return $sql;
    }

    public function __call($operation, $parts)
    {
        return $this->preBuild($operation, $parts);
    }

    public function __construct($rawSql = '', array $params = null)
    {
        $this->setQuery($rawSql, $params);
    }

    public function __toString()
    {
        return rtrim($this->query);
    }

    public function getParams()
    {
        return $this->params;
    }

    public function setQuery($rawSql, array $params = null)
    {
        $this->query = $rawSql;
        if ($params !== null)
            $this->params = $params;
        return $this;
    }

    public function appendQuery($sql, array $params = null)
    {
        $this->query = trim($this->query) . " $sql";
        if ($sql instanceof self)
            $this->params = array_merge($this->params, $sql->getParams());
        if ($params !== null)
            $this->params = array_merge($this->params, $params);
        return $this;
    }

    protected function preBuild($operation, $parts)
    {
        $raw   = ($operation == 'select' || $operation == 'on');
        $parts = $this->normalizeParts($parts, $raw);
        if (empty($parts))
            switch ($operation) {
                case 'asc':
                case 'desc':
                case '_':
                    break;
                default:
                    return $this;
            }
        if ($operation == 'cond') // condition list
            return $this->build('and', $parts);

        $this->buildOperation($operation);
        $operation = trim($operation, '_');
        return $this->build($operation, $parts);
    }

    protected function build($operation, $parts)
    {
        switch ($operation) { //just special cases
            case 'select':
                return $this->buildAliases($parts);
            case 'and':
            case 'having':
            case 'where':
            case 'between':
                return $this->buildKeyValues($parts, '%s ', ' AND ');
            case 'or':
                return $this->buildKeyValues($parts, '%s ', ' OR ');
            case 'set':
                return $this->buildKeyValues($parts);
            case 'on':
                return $this->buildComparators($parts, '%s ', ' AND ');
            case 'alterTable':
                $this->buildFirstPart($parts);
                return $this->buildParts($parts, '%s ');
            case 'in':
            case 'values':
                return $this->buildValuesList($parts);
            case 'createTable':
            case 'insertInto':
            case 'replaceInto':
                $this->params = array();
                $this->buildFirstPart($parts);
                return $this->buildParts($parts, '(%s) ');
            default: //defaults to any other SQL instruction
                return $this->buildParts($parts);
        }
    }

    protected function buildKeyValues($parts, $format = '%s ', $partSeparator = ', ')
    {
        foreach ($parts as $key => $part)
            if (is_numeric($key)) {
                $parts[$key] = "$part";
            } else {
                $value = ($part instanceof self) ? "$part" : static::PLACEHOLDER;
                if (preg_match(static::SQL_OPERATORS, $key) > 0)
                    $parts[$key] = "$key $value";
                else
                    $parts[$key] = "$key = $value";
            }
        return $this->buildParts($parts, $format, $partSeparator);
    }

    protected function buildComparators($parts, $format = '%s ', $partSeparator = ', ')
    {
        foreach ($parts as $key => $part)
            if (is_numeric($key))
                $parts[$key] = "$part";
            else
                $parts[$key] = "$key = $part";
        return $this->buildParts($parts, $format, $partSeparator);
    }

     protected function buildAliases($parts, $format = '%s ', $partSeparator = ', ')
    {
        foreach ($parts as $key => $part)
            if (is_numeric($key))
                $parts[$key] = "$part";
            else
                $parts[$key] = "$part AS $key";
        return $this->buildParts($parts, $format, $partSeparator);
    }

    protected function buildValuesList($parts)
    {
        foreach ($parts as $key => $part)
            if (is_numeric($key) || $part instanceof self)
                $parts[$key] = "$part";
            else
                $parts[$key] = static::PLACEHOLDER;
        return $this->buildParts($parts, '(%s) ', ', ');
    }

    protected function buildOperation($operation)
    {
        $command = strtoupper(preg_replace('/[A-Z0-9]+/', ' $0', $operation));
        if ($command == '_')
            $this->query = rtrim($this->query) . ') ';
        elseif ($command[0] == '_')
            $this->query .= '(' . trim($command, '_ ') . ' ';
        elseif (substr($command, -1) == '_')
            $this->query .= trim($command, '_ ') . ' (';
        else
            $this->query .= trim($command) . ' ';
    }

    protected function buildFirstPart(&$parts)
    {
        $this->query .= array_shift($parts) . ' ';
    }

    protected function buildParts($parts, $format = '%s ', $partSeparator = ', ')
    {
        if (!empty($parts))
            $this->query .= sprintf($format, implode($partSeparator, $parts));

        return $this;
    }

    protected function normalizeParts($parts, $raw=false)
    {
        $params = & $this->params;
        $newParts = array();
        
        array_walk_recursive($parts, function ($value, $key) use (&$newParts, &$params, &$raw) {
                if ($value instanceof Sql) {
                    $params = array_merge($params, $value->getParams());
                    if ($value->query[0] != '(')
                        $value = static::enclose($value);
                    $newParts[$key] = $value;
                } elseif ($raw) {
                    $newParts[$key] = $value;
                } elseif (is_int($key)) {
                    $newParts[] = $value;
                } else {
                    $newParts[$key] = $key;
                    $params[] = $value;
                }
            }
        );
        return $newParts;
    }
}
