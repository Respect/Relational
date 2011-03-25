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

    public function hydrate(array $entitiesNames, array $row, $full=false)
    {
        $totalEntities = count($entitiesNames);

        if (1 === $totalEntities)
            return (object) $row;

        $this->checkInferenceIsPossible($row, $entitiesNames);

        foreach ($entitiesNames as &$name)
            $name = $this->removeAffixes($name);

        $instances = $this->hydrateInstances($row);
        $instances = $this->hydrateRelationships($row, $entitiesNames, $instances);
        $instances = $this->hydrateColumns($row, $entitiesNames, $instances);

        return $instances;
    }

    protected function hydrateColumns(&$row, $entitiesNames, $instances)
    {
        foreach ($row as $columnName => $value)
            if (is_scalar($value))
                foreach ($instances as $i)
                    $i->{$columnName} = $value;
            else
                foreach ($value as $subValueId => $subValue)
                    foreach ($this->calculateRepeatedInstances(count($value),
                        $entitiesNames, $instances) as $repeated)
                        $repeated[$subValueId]->{$columnName} = $subValue;

        return $instances;
    }

    protected function hydrateRelationships(&$row, $entitiesNames, $instances)
    {
        $totalEntities = count($entitiesNames);

        for ($i = 0, $j = 1; $i < $totalEntities - 1; $i++, $j = $i + 1)
            if ($this->unstackMatchedReference($row, $entitiesNames[$j], $instances[$j]))
                $instances[$i]->{$entitiesNames[$j] . '_id'} = $instances[$j];
            elseif ($this->unstackMatchedReference($row, $entitiesNames[$i], $instances[$i]))
                $instances[$j]->{$entitiesNames[$i] . '_id'} = $instances[$i];

        return $instances;
    }

    protected function hydrateInstances(&$row)
    {
        $instances = array();

        foreach ($row['id'] as $k => $entityId)
            $instances[] = (object) array('id' => $entityId);

        unset($row['id']);

        return $instances;
    }

    protected function checkInferenceIsPossible($row, $entitiesNames)
    {
        $totalEntities = count($entitiesNames);

        if (!$totalEntities)
            throw new \InvalidArgumentException();

        if (!isset($row['id']) || count($row['id']) !== $totalEntities)
            throw new \InvalidArgumentException();
    }

    protected function calculateRepeatedInstances($count, $entitiesNames, $instances)
    {
        if (count($entitiesNames) === count(array_unique($entitiesNames)))
            return array();

        $instancesByName = array();
        $repeatedInstancesByCount = array(1 => array());

        foreach ($entitiesNames as $k => $name)
            $instancesByName[$name][] = $instances[$k];

        foreach ($instancesByName as $instanceName => $is)
            $repeatedInstancesByCount[count($is)][$instanceName] = $is;

        return $repeatedInstancesByCount[$count];
    }

    protected function unstackMatchedReference(&$row, $entity, $instance)
    {
        $entity .= '_id';

        if (!array_key_exists($entity, $row))
            return false;
        elseif (is_array($row[$entity]))
            array_shift($row[$entity]);
        else
            unset($row[$entity]);

        return true;
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