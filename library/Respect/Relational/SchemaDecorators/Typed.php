<?php

namespace Respect\Relational\SchemaDecorators;

use PDOStatement;
use Respect\Relational\Schemable;
use SplObjectStorage;
use Respect\Relational\Finder;

class Typed implements Schemable
{

    protected $decorated;
    protected $namespace = '\\';

    public static function normalize($string)
    {
        return str_replace(' ', '_',
            ucwords(str_replace('_', ' ', strtolower($string))));
    }

    public function __construct(Schemable $decorated, $namespace='\\')
    {
        $this->decorated = $decorated;
        $this->namespace = $namespace;
    }

    public function extractColumns($entity, $name)
    {
        return $this->decorated->extractColumns($entity, $name);
    }

    public function fetchHydrated(Finder $finder, PDOStatement $statement)
    {
        $untyped = $this->decorated->fetchHydrated($finder, $statement);
        if (!$untyped)
            return $untyped;

        $typed = new SplObjectStorage();
        foreach ($untyped as $e) {
            $className = $this->namespace . '\\' . static::normalize($untyped[$e]['name']);
            $newEntity = new $className;
            foreach ($untyped[$e]['cols'] as $name => $value)
                $newEntity->{$name} = $value;
            $typed[$newEntity] = $untyped[$e];
        }
        return $typed;
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