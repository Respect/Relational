<?php

namespace Respect\Relational\Schemas;

use PDO;
use PDOStatement;
use Respect\Relational\Sql;
use Respect\Relational\Schemable;
use Respect\Relational\Finder;
use Respect\Relational\FinderIterator;

class Infered implements Schemable
{

    public function generateQuery(Finder $finder)
    {
        $finders = iterator_to_array(FinderIterator::recursive($finder), true);
        $sql = new Sql;

        $this->buildSelectStatement($sql, $finders);
        $this->buildTables($sql, $finders);

        return $sql;
    }

    protected function buildSelectStatement(Sql $sql, $finders)
    {
        $selectTable = array_keys($finders);
        foreach ($selectTable as &$ts)
            $ts = "$ts.*";

        return $sql->select($selectTable);
    }

    protected function buildTables(Sql $sql, $finders)
    {
        $conditions = $aliases = array();

        foreach ($finders as $alias => $finder)
            $this->parseFinder($sql, $finder, $alias, $aliases, $conditions);

        if (!empty($conditions))
            $sql->where($conditions);

        return $sql;
    }

    protected function parseConditions(&$conditions, $finder, $alias)
    {
        $entity = $finder->getEntityReference();
        $originalConditions = $finder->getCondition();
        $parsedConditions = array();

        if (is_scalar($originalConditions))
            $parsedConditions = array("$alias.id" => $originalConditions);
        elseif (is_array($originalConditions))
            foreach ($originalConditions as $column => $value)
                if (is_numeric($column))
                    $parsedConditions[$column] = preg_replace(
                            "/{$entity}[.](\w+)/", "$alias.$1", $value
                    );
                else
                    $parsedConditions["$alias.$column"] = $value;

        return $parsedConditions;
    }

    protected function parseFinder(Sql $sql, Finder $finder, $alias, &$aliases, &$conditions)
    {
        $entity = $finder->getEntityReference();
        $parent = $finder->getParentEntityReference();
        $sibling = $finder->getNextSiblingEntityReference();

        $parentAlias = $parent ? $aliases[$parent] : null;
        $aliases[$entity] = $alias;
        $parsedConditions = $this->parseConditions($conditions, $finder, $alias);

        if (!empty($parsedConditions))
            $conditions[] = $parsedConditions;

        if (is_null($parentAlias))
            return $sql->from($entity);
        elseif ($finder->isRequired())
            $sql->innerJoin($entity);
        else
            $sql->leftJoin($entity);

        if ($alias !== $entity)
            $sql->as($alias);

        if ($entity === "{$parent}_{$sibling}")
            return $sql->on(array("{$alias}.{$parent}_id" => "{$parentAlias}.id"));
        else
            return $sql->on(array("{$parentAlias}.{$entity}_id" => "{$alias}.id"));
    }

    public function fetchHydrated(Finder $finder, PDOStatement $statement)
    {
        if (!$finder->hasMore())
            return $this->fetchSingle($finder, $statement);
        else
            return $this->fetchMulti($finder, $statement);
    }

    protected function fetchSingle(Finder $finder, PDOStatement $statement)
    {
        $row = $statement->fetch(PDO::FETCH_OBJ);
        return array(
            $finder->getEntityReference() => array(
                $row->id => $row
            )
        );
    }

    protected function fetchMulti(Finder $finder, PDOStatement $statement)
    {
        $entities = array();
        $entityInstance = null;
        $finders = FinderIterator::recursive($finder);

        foreach ($statement->fetch() as $n => $value) {

            $meta = $statement->getColumnMeta($n);

            if ('id' === $meta['name']) {
                if (0 !== $n)
                    $entities[$entityName][$entityInstance->id] = $entityInstance;

                $finders->next();
                $entityName = $finders->current()->getEntityReference();
                $entityInstance = new \stdClass;
            }
            $entityInstance->{$meta['name']} = $value;
        }

        if (!empty($entities))
            $entities[$entityName][$entityInstance->id] = $entityInstance;

        foreach ($entities as $table => $instances)
            foreach ($instances as $pk => $i)
                foreach ($i as $field => &$v)
                    if (strlen($field) - 3 === strripos($field, '_id'))
                        if (isset($entities[$eName = substr($field, 0, -3)][$v]))
                            $v = $entities[$eName][$v];

        return $entities;
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