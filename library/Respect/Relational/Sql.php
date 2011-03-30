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
        $this->buildOperation($operation);
        $method = 'parse' . ucfirst($operation);
        if (!method_exists($this, $method)) {
            $parts = $this->normalizeParts($parts);
            $method = 'buildParts';
        }
        $this->{$method}($parts);
        return $this;
    }

    public function __construct($rawSql = '')
    {
        $this->setQuery($rawSql);
    }

    public function __toString()
    {
        $q = trim($this->query);
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
        $this->buildParts($parts, $format, $partSeparator);
    }

    protected function buildComparators($parts, $format = '%s ', $partSeparator = ', ')
    {
        foreach ($parts as $key => $part)
            if (is_numeric($key))
                $parts[$key] = "$part";
            else
                $parts[$key] = "$key = $part";
        $this->buildParts($parts, $format, $partSeparator);
    }

    protected function buildOperation($operation)
    {
        $command = strtoupper(preg_replace('#[A-Z0-9]+#', ' $0', $operation));
        $this->query .= trim($command) . ' ';
    }

    protected function buildParts($parts, $format = '%s ', $partSeparator = ', ')
    {
        if (empty($parts))
            return;
        $this->query .= sprintf($format, implode($partSeparator, $parts));
    }

    protected function buildName($identifier)
    {
        $translated = strtolower(preg_replace('#[^a-zA-Z0-9]#', ' ', $identifier));
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

    protected function parseCreateTable($parts)
    {
        $parts = $this->normalizeParts($parts);
        $this->parseFirstPart($parts);
        $this->buildParts($parts, '(%s) ');
    }

    protected function parseAlterTable($parts)
    {
        $parts = $this->normalizeParts($parts);
        $this->parseFirstPart($parts);
        $this->buildParts($parts, '%s ');
    }

    protected function parseHaving($parts)
    {
        $parts = $this->normalizeParts($parts);
        $this->buildKeyValues($parts, '%s ', ' AND ');
    }

    protected function parseIn($parts)
    {
        $parts = $this->normalizeParts($parts);
        $parts = array_map(array($this, 'buildName'), $parts);
        $this->buildParts($parts, '(:%s) ', ', :');
    }

    protected function parseInsertInto($parts)
    {
        $parts = $this->normalizeParts($parts);
        $this->parseFirstPart($parts);
        $this->buildParts($parts, '(%s) ');
    }

    protected function parseFirstPart(& $parts)
    {
        $this->query .= array_shift($parts) . ' ';
    }

    protected function parseSet($parts)
    {
        $parts = $this->normalizeParts($parts);
        $this->buildKeyValues($parts);
    }

    protected function parseValues($parts)
    {
        $parts = $this->normalizeParts($parts);
        $parts = array_map(array($this, 'buildName'), $parts);
        $this->buildParts($parts, '(:%s) ', ', :');
    }

    protected function parseWhere($parts)
    {
        $parts = $this->normalizeParts($parts);
        $this->buildKeyValues($parts, '%s ', ' AND ');
    }

    protected function parseOn($parts)
    {
        $parts = $this->normalizeParts($parts, true);
        $this->buildComparators($parts, '%s ', ' AND ');
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