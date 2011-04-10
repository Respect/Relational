<?php

namespace Respect\Relational;

use Exception;
use PDO;
use SplObjectStorage;

class Mapper
{

    protected $db;
    protected $schema;
    protected $tracked;
    protected $changed;

    public function __construct(Db $db, Schemable $schema)
    {
        $this->db = $db;
        $this->schema = $schema;
        $this->tracked = new SplObjectStorage;
        $this->changed = new SplObjectStorage;
        $this->new = new SplObjectStorage;
    }

    public function __get($name)
    {
        $finder = new Finder($name);
        $finder->setMapper($this);

        return $finder;
    }

    public function __call($name, $children)
    {
        $finder = new Finder($name);
        $finder->setMapper($this);

        foreach ($children as $child)
            $finder->addChild($child);

        return $finder;
    }

    public function fetch(Finder $finder, Sql $sqlExtra=null)
    {
        $statement = $this->createStatement($finder);
        $hydrated = $this->schema->fetchHydrated($finder, $statement);
        if (!$hydrated)
            return false;

        return $this->parseHydrated($hydrated);
    }

    public function fetchAll(Finder $finder, Sql $sqlExtra=null)
    {
        $statement = $this->createStatement($finder, $sqlExtra);
        $entities = array();

        while ($hydrated = $this->schema->fetchHydrated($finder, $statement))
            $entities[] = $this->parseHydrated($hydrated);

        return $entities;
    }

    protected function guessName($entity)
    {
        if ($this->isTracked($entity))
            return $this->tracked[$entity]['name'];
        else
            return $this->schema->findName($entity);
    }

    public function persist($entity, $name=null)
    {
        $this->changed[$entity] = true;

        if ($this->isTracked($entity))
            return true;

        $this->new[$entity] = true;
        $this->markTracked($entity, $name);
        return true;
    }

    public function flush()
    {
        $conn = $this->db->getConnection();
        $conn->beginTransaction();
        try {
            foreach ($this->changed as $entity)
                $this->flushSingle($entity);
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        $this->changed = new SplObjectStorage;
        $this->new = new SplObjectStorage;
        $conn->commit();
    }

    protected function flushSingle($entity)
    {
        $name = $this->tracked[$entity]['name'] ? : $this->guessName($entity);
        $cols = $this->schema->extractColumns($entity, $name);

        if ($this->new->contains($entity))
            $this->rawInsert($cols, $name, $entity);
        else
            $this->rawUpdate($cols, $name);
    }

    public function remove($entity, $name=null)
    {

        $name = $name ? : $this->guessName($entity);
        $columns = $this->schema->extractColumns($entity, $name);
        $condition = $this->guessCondition($columns, $name);

        return $this->db
            ->deleteFrom($name)
            ->where($condition)
            ->exec();
    }

    protected function guessCondition(&$columns, $name)
    {
        $primaryKey = $this->schema->findPrimaryKey($name);
        $pkValue = $columns[$primaryKey];
        $condition = array($primaryKey => $pkValue);
        unset($columns[$primaryKey]);
        return $condition;
    }

    protected function rawUpdate(array $columns, $name)
    {
        $condition = $this->guessCondition($columns, $name);

        return $this->db
            ->update($name)
            ->set($columns)
            ->where($condition)
            ->exec();
    }

    protected function rawInsert(array $columns, $name, $entity=null)
    {
        $isInserted = $this->db
                ->insertInto($name, $columns)
                ->values($columns)
                ->exec();

        if (!is_null($entity))
            $this->checkNewIdentity($entity);

        return $isInserted;
    }

    protected function checkNewIdentity($entity, $name=null)
    {
        $name = $name ? : $this->guessName($entity);
        $identity = null;
        try {
            $identity = $this->db->getConnection()->lastInsertId();
        } catch (PDOException $e) {
            //some drivers may throw an exception here, it is just irrelevant
            return false;
        }
        if (!$identity)
            return false;

        $entity->{$this->schema->findPrimaryKey($name)} = $identity;
        return true;
    }

    public function markTracked($entity, $name=null, $id=null)
    {
        $name = $name ? : $this->guessName($entity);
        $id = $id ? : $entity->{$this->schema->findPrimaryKey($name)};
        $this->tracked[$entity] = array(
            'name' => $name,
            'id' => $id,
            'cols' => $this->schema->extractColumns($entity, $name)
        );
        return true;
    }

    public function isTracked($entity)
    {
        return $this->tracked->contains($entity);
    }

    public function getTracked($name, $id)
    {
        foreach ($this->tracked as $entity)
            if ($this->tracked[$entity]['id'] == $id
                && $this->tracked[$entity]['name'] === $name)
                return $entity;

        return false;
    }

    protected function createStatement(Finder $finder, Sql $sqlExtra=null)
    {
        $finderQuery = $this->schema->generateQuery($finder);
        if ($sqlExtra)
            $finderQuery->appendQuery($sqlExtra);
        $statement = $this->db->prepare((string) $finderQuery, PDO::FETCH_NUM);
        $statement->execute($finderQuery->getParams());
        return $statement;
    }

    protected function parseHydrated(SplObjectStorage $hydrated)
    {
        $this->tracked->addAll($hydrated);
        $hydrated->rewind();
        return $hydrated->current();
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