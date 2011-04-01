<?php

namespace Respect\Relational;

use PDO;

class Mapper
{

    protected $db;
    protected $schema;

    public function __construct(Db $db, Schemable $schema)
    {
        $this->db = $db;
        $this->schema = $schema;
    }

    public function __get($finder)
    {
        $newFinder = new Finder($finder);
        $newFinder->setMapper($this);

        return $newFinder;
    }

    public function __call($finder, $children)
    {
        $newFinder = new Finder($finder);
        $newFinder->setMapper($this);

        foreach ($children as $child)
            $newFinder->addChild($child);

        return $newFinder;
    }

    public function fetch(Finder $finder)
    {
        $statement = $this->createStatement($finder);
        return $this->assimilate(
            $this->schema->fetchHydrated($finder, $statement)
        );
    }

    public function fetchAll(Finder $finder)
    {
        $statement = $this->createStatement($finder);
        $rows = array();

        while ($row = $this->schema->fetchHydrated($finder, $statement))
            $rows[] = $this->assimilate($row);

        return $rows;
    }

    protected function assimilate(array $resultSet)
    {
        if (empty($resultSet))
            return false;
        else
            return reset(reset($resultSet));
    }

    protected function createStatement(Finder $finder)
    {
        $finderQuery = $this->schema->generateQuery($finder);
        $statement = $this->db->prepare((string) $finderQuery, PDO::FETCH_NUM);
        $statement->execute($finderQuery->getParams());
        return $statement;
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