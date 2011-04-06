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
            return $this;
        $this->buildOperation($operation);
        return $this->build($operation, $parts);
    }

    protected function build($operation, $parts)
    {
        switch ($operation) { //just special cases
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
            case 'and':
            case 'having':
            case 'where':
                return $this->buildKeyValues($parts, '%s ', ' AND ');
            case 'on':
                return $this->buildComparators($parts, '%s ', ' AND ');
            case 'or':
                return $this->buildKeyValues($parts, '%s ', ' OR ');
            case 'set':
                return $this->buildKeyValues($parts);
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

/**
 * LICENSE
 *
 * Copyright (c) 2009-2011, Alexandre Gomes Gaigalas.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 *     * Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *     * Redistributions in binary form must reproduce the above copyright notice,
 *       this list of conditions and the following disclaimer in the documentation
 *       and/or other materials provided with the distribution.
 *
 *     * Neither the name of Alexandre Gomes Gaigalas nor the names of its
 *       contributors may be used to endorse or promote products derived from this
 *       software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 */