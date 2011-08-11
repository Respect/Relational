<?php

namespace Respect\Relational\Schemas;

use PDOStatement;
use Respect\Relational\Schemable;
use SplObjectStorage;
use Respect\Relational\Finder;
use Respect\Relational\FinderIterator;

class Inflected implements Schemable
{

    protected $decorated;
    
    public function setColumnValue(&$entity, $column, $value) 
    {
        return $this->decorated->setColumnValue($entity, static::camelize($column), $value);
    }
    
    public function getColumnValue(&$entity, $column) 
    {
        return $this->decorated->getColumnValue($entity, static::decamelize($column));
    }

    public static function camelize($string)
    {
        return lcfirst(str_replace(' ', '',
                ucwords(str_replace('_', ' ', strtolower($string)))));
    }

    public static function decamelize($string)
    {
        return strtolower(implode('_',
                preg_split('/(?<=\\w)(?=[A-Z])/', $string)));
    }

    public function __construct(Schemable $decorated)
    {
        $this->decorated = $decorated;
    }

    public function extractColumns($entity, $name)
    {
        $columns = $this->decorated->extractColumns($entity, $name);
        $newColumns = array();

        foreach ($columns as $name => $value)
            $newColumns[static::decamelize($name)] = $value;

        return $newColumns;
    }

    public function fetchHydrated(Finder $finder, PDOStatement $statement)
    {
        $uninflected = $this->decorated->fetchHydrated($finder, $statement);

        if (!$uninflected)
            return $uninflected;

        $inflected = new SplObjectStorage();
        foreach ($uninflected as $e) {
            $className = get_class($e);
            $newEntity = new $className;
            $inflectedData = $uninflected[$e];
            foreach ($inflectedData['cols'] as $name => $value)
                $newEntity->{static::camelize($name)} = $value;
            $inflectedData['cols'] = array_combine(
                array_map(
                    array(__CLASS__, 'camelize'),
                    array_keys($inflectedData['cols'])
                ), $inflectedData['cols']
            );
            $inflectedData['name'] = static::camelize($inflectedData['name']);
            $inflected[$newEntity] = $inflectedData;
        }
        return $inflected;
    }

    public function findTableName($entity)
    {
        return $this->decorated->findTableName($entity);
    }

    public function findPrimaryKey($entityName)
    {
        return $this->decorated->findPrimaryKey($entityName);
    }

    public function generateQuery(Finder $finder)
    {
        foreach (FinderIterator::recursive($finder) as $f)
            $f->setName(static::decamelize($f->getName()));

        return $this->decorated->generateQuery($finder);
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