<?php

namespace Respect\Relational\Schemas;

use stdClass;
use PDO;
use PDOStatement;
use SplObjectStorage;
use Respect\Relational\Sql;
use Respect\Relational\Schemable;
use Respect\Relational\Finder;
use Respect\Relational\FinderIterator;

class Infered implements Schemable
{

    protected $typed = false;
    protected $typePrefix = '\\';

    public function generateQuery(Finder $finder)
    {
        $finders = iterator_to_array(FinderIterator::recursive($finder), true);
        $sql = new Sql;

        $this->buildSelectStatement($sql, $finders);
        $this->buildTables($sql, $finders);

        return $sql;
    }

    public function findPrimaryKey($name)
    {
        return 'id';
    }

    public function findClass($name)
    {
        $name = str_replace(' ', '_',
            ucwords(str_replace('_', ' ', strtolower($name))));
        if (!$this->typed)
            return '\stdClass';
        else
            return $this->typePrefix . $name;
    }

    public function findName($entity)
    {
        throw new \InvalidArgumentException();
    }

    public function extractColumns($entity, $name=null)
    {
        $name = $name ? : $this->findName($entity);
        $cols = get_object_vars($entity);

        foreach ($cols as &$c)
            if (is_object($c))
                $c = $c->id;

        return $cols;
    }

    public function setTyped($typed)
    {
        $this->typed = $typed;
    }

    public function setTypePrefix($typePrefix)
    {
        $this->typePrefix = $typePrefix;
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

        return $sql->where($conditions);
    }

    protected function parseConditions(&$conditions, $finder, $alias)
    {
        $entity = $finder->getName();
        $originalConditions = $finder->getCondition();
        $parsedConditions = array();
        $aliasedPk = "$alias." . $this->findPrimaryKey($entity);

        if (is_scalar($originalConditions))
            $parsedConditions = array($aliasedPk => $originalConditions);
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
        $entity = $finder->getName();
        $parent = $finder->getParentName();
        $next = $finder->getNextName();

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

        $aliasedPk = "$alias." . $this->findPrimaryKey($entity);
        $aliasedParentPk = "$parentAlias." . $this->findPrimaryKey($parent);

        if ($entity === "{$parent}_{$next}")
            return $sql->on(array("{$alias}.{$parent}_id" => $aliasedParentPk));
        else
            return $sql->on(array("{$parentAlias}.{$entity}_id" => $aliasedPk));
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
        $name = $finder->getName();
        $statement->setFetchMode(
            PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, $this->findClass($name)
        );

        $row = $statement->fetch();

        if (!$row)
            return false;

        $entities = new SplObjectStorage();
        $entities[$row] = array(
            'name' => $name,
            'id' => $row->id,
            'cols' => $this->extractColumns($row, $name)
        );

        return $entities;
    }

    protected function fetchMulti(Finder $finder, PDOStatement $statement)
    {
        $entityInstance = null;
        $finders = FinderIterator::recursive($finder);
        $row = $statement->fetch(PDO::FETCH_NUM);

        if (!$row)
            return false;

        $entities = new SplObjectStorage();

        foreach ($row as $n => $value) {
            $meta = $statement->getColumnMeta($n);

            if ('id' === $meta['name']) {
                if (0 !== $n)
                    $entities[$entityInstance] = array(
                        'name' => $entityName,
                        'id' => $entityInstance->id,
                        'cols' => $this->extractColumns(
                            $entityInstance,
                            $entityName
                        )
                    );

                $finders->next();
                $entityName = $finders->current()->getName();
                $entityInstance = $this->findClass($entityName);
            }
            $entityInstance->{$meta['name']} = $value;
        }

        if (!empty($entities))
            $entities[$entityInstance] = array(
                'name' => $entityName,
                'id' => $entityInstance->id,
                'cols' => $this->extractColumns($entityInstance, $entityName)
            );

        foreach ($entities as $instance)
            foreach ($instance as $field => &$v)
                if (strlen($field) - 3 === strripos($field, '_id'))
                    foreach ($entities as $sub)
                        if ($entities[$sub]['name'] === substr($field, 0, -3)
                            && $sub->id === $v)
                            $v = $sub;

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