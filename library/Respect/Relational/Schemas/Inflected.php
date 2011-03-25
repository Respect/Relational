<?php

namespace Respect\Relational\Schemas;

use Respect\Relational\Relationship;
use Respect\Relational\Schemable;

class Inflected implements Schemable
{

    protected $schema;

    public function __construct(Schemable $schema)
    {
        $this->schema = $schema;
    }

    public function findRelationships($entityName, $relatedNameOrColumn)
    {
        return $this->schema->findRelationships(
            $this->decamelize($entityName),
            $this->decamelize($relatedNameOrColumn)
        );
    }

    public function hydrate(array $entitiesNames, array $row)
    {
        $objects = $this->schema->hydrate($entitiesNames, $row);
        foreach ($objects as &$hydrated)
            $hydrated = $this->camelizeKeys($hydrated);
        return $objects;
    }

    protected function camelizeKeys($object, array &$walkedTrough=array())
    {
        if (is_scalar($object) || in_array($object, $walkedTrough))
            return $object;
        else
            $walkedTrough[] = $object;

        foreach ($object as $key => $value) {
            $camelizedKey = $this->camelize($key);
            $object->{$camelizedKey} = $this->camelizeKeys($value, $walkedTrough);
            if ($camelizedKey != $key)
                unset($object->{$key});
        }

        return $object;
    }

    protected function decamelize($name)
    {
        return strtolower(preg_replace('/([A-Z0-9])/', '_$1', $name));
    }

    protected function camelize($name)
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $name))));
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