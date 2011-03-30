<?php

namespace Respect\Relational\Schemas;

use PDOStatement;
use Respect\Relational\Sql;
use Respect\Relational\Schemable;
use Respect\Relational\Finder;
use Respect\Relational\FinderIterator;

class Infered implements Schemable
{

    public function generateQuery(Finder $finder)
    {
        $sql = new Sql;
        foreach (FinderIterator::recursive($finder) as $entities)
            $sql = $this->appendEntities($sql, $entities);

        return $sql;
    }

    protected function appendEntities(Sql $sql, array $entities)
    {
        if (1 === count($entities))
            $sql->select('*')
                ->from(current($entities)->getEntityReference())
                ->as(key($entities));
        else
            $sql = $this->appendJoin($sql, $entities);

        return $sql;
    }

    protected function appendJoin(Sql $sql, $entities)
    {
        list($fromAlias, $fromEntity) = each($entities);
        $toAlias = key($entities);
        $fromEntity = $fromEntity->getEntityReference();

        return $sql->innerJoin($fromEntity)
            ->as($fromAlias)
            ->on(array(
                "$toAlias.{$fromEntity}_id" => "$fromAlias.id"
            ));
    }

    public function fetchHydrated(Finder $finder, PDOStatement $statement)
    {

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