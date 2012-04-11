<?php

namespace Respect\Relational;

class Sql
{

    protected $query = '';
    protected $params = array();
    protected $data = array();

    public static function __callStatic($operation, $parts)
    {
        $sql = new static;
        return call_user_func_array(array($sql, $operation), $parts);
    }

    public function __call($operation, $parts)
    {
        return $this->preBuild($operation, $parts);
    }

    protected function preBuild($operation, $parts)
    {
        $parts = $this->normalizeParts($parts, $operation === 'on' ? true : false);
        if (empty($parts))
            switch ($operation) {
                case 'asc':
                case 'desc':
                    break;
                default: 
                    return $this;   
            }
        $this->buildOperation($operation);
        return $this->build($operation, $parts);
    }

    protected function build($operation, $parts)
    {
        switch ($operation) { //just special cases
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
                $parts = array_map(array($this, 'buildName'), $parts);
                return $this->buildParts($parts, '(:%s) ', ', :');
            case 'createTable':
            case 'insertInto':
                $this->buildFirstPart($parts);
                return $this->buildParts($parts, '(%s) ');
            case 'values':
                $parts = array_map(array($this, 'buildName'), $parts);
                return $this->buildParts($parts, '(:%s) ', ', :');
            default: //defaults to any other SQL instruction
                return $this->buildParts($parts);
        }
    }

    public function __construct($rawSql = '')
    {
        $this->setQuery($rawSql);
    }

    public function __toString()
    {
        $q = rtrim($this->query);
        $this->query = '';
        return $q;
    }

    public function appendQuery($rawSql)
    {
        $this->query .= " $rawSql";
        return $this;
    }

    public function getParams()
    {
        $data = array();
        foreach ($this->data as $k => $v)
            $data[$this->params[$k]] = $v;
        return $data;
    }

    public function setQuery($rawSql)
    {
        $this->query = $rawSql;
        return $this;
    }

    protected function buildKeyValues($parts, $format = '%s ', $partSeparator = ', ')
    {
        foreach ($parts as $key => $part)
            if (is_numeric($key))
                $parts[$key] = "$part";
            else
                $parts[$key] = "$key=:" . $this->buildName($part);
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

    protected function buildOperation($operation)
    {
        $command = strtoupper(preg_replace('/[A-Z0-9]+/', ' $0', $operation));
        $this->query .= trim($command) . ' ';
    }

    protected function buildParts($parts, $format = '%s ', $partSeparator = ', ')
    {
        if (!empty($parts))
            $this->query .= sprintf($format, implode($partSeparator, $parts));

        return $this;
    }

    protected function buildName($identifier)
    {
        $translated = strtolower(preg_replace('/[^a-zA-Z0-9]/', ' ', $identifier));
        $translated = str_replace(' ', '', ucwords($translated));
        return $this->params[$identifier] = $translated;
    }

    protected function normalizeParts($parts, $raw=false)
    {
        $data = & $this->data;
        $newParts = array();
        array_walk_recursive($parts, function ($value, $key) use ( &$newParts, &$data, &$raw) {
                if ($raw) {
                    $newParts[$key] = $value;
                } elseif (is_int($key)) {
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

    protected function buildFirstPart(&$parts)
    {
        $this->query .= array_shift($parts) . ' ';
    }

}
