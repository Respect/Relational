<?php

namespace Respect\Relational\Schemas;

use stdClass;
use Respect\Relational\Relationship;
use Respect\Relational\Schemable;

class Infered implements Schemable
{

    public function findRelationships($entityName, $relatedNameOrColumn)
    {
        $from = $this->removeAffixes($entityName);
        $to = $this->removeAffixes($relatedNameOrColumn);
        $keys = array("{$to}_id" => "id");
        return array(new Relationship($from, $to, $keys));
    }

    public function findPrimaryKey($entityName)
    {
        return $this->removeAffixes($entityName) . '_id';
    }

    public function hydrate(array $entitiesNames, array $row, $full=false)
    {
        $entities = array();

        foreach ($entitiesNames as &$name) {
            $name = $this->removeAffixes($name);
            $entities[] = new stdClass;
        }

        foreach ($row as $columnName => $value)
            if (is_array($value))
                foreach ($value as $entityId => $subValue)
                    $entities[$entityId]->{$columnName} = $subValue;
            else
                foreach ($entities as $entity)
                    $entity->{$columnName} = $value;



        foreach ($entities as $entity) {
            foreach ($entities as &$entity2)
                if ($entity->id === $entity2->id)
                    $entity2 = $entity;
            foreach ($entity as $fieldName => $field)
                foreach ($entitiesNames as $entityId => $entityName)
                    if ($entityName == $this->removeAffixes($fieldName))
                        $entity->{$fieldName} = $entities[$entityId];
        }

        return $full ? $entities : reset($entities);
    }

    protected function removeAffixes($name)
    {
        $lastIdPos = strripos($name, '_id');
        if (false === $lastIdPos || $lastIdPos + 3 !== strlen($name))
            return $name;
        else
            return substr($name, 0, $lastIdPos);
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