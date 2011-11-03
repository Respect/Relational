<?php

namespace Respect\Relational;

use ArrayAccess;

class Collection implements ArrayAccess
{

    protected $required = true;
    protected $mapper;
    protected $name;
    protected $condition;
    protected $parent;
    protected $next;
    protected $last;
    protected $children = array();

    public static function __callStatic($name, $children)
    {
        $collection = new static($name);

        foreach ($children as $child)
            if ($child instanceof Collection)
                $collection->addChild($child);
            else
                $collection->setCondition($child);
        return $collection;
    }

    public function __construct($name, $condition = array())
    {
        if (!is_scalar($condition) && !is_array($condition))
            throw new \InvalidArgumentException('Unexpected');

        $this->name = $name;
        $this->condition = $condition;
        $this->last = $this;
    }

    public function __get($name)
    {
        return $this->stack(new static($name));
    }

    public function __call($name, $children)
    {
        $collection = static::__callStatic($name, $children);

        return $this->stack($collection);
    }

    public function addChild(Collection $child)
    {
        $clone = clone $child;
        $clone->setRequired(false);
        $clone->setMapper($this->mapper);
        $clone->setParent($this);
        $this->children[] = $clone;
    }
    
    public function persist($entity) 
    {
        return $this->mapper->persist($entity, $this->name);
    }

    public function fetch(Sql $sqlExtra=null)
    {
        if (!$this->mapper)
            throw new \RuntimeException;
        return $this->mapper->fetch($this, $sqlExtra);
    }

    public function fetchAll(Sql $sqlExtra=null)
    {
        if (!$this->mapper)
            throw new \RuntimeException;
        return $this->mapper->fetchAll($this, $sqlExtra);
    }

    public function getChildren()
    {
        return $this->children;
    }

    public function getCondition()
    {
        return $this->condition;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getNext()
    {
        return $this->next;
    }

    public function getParentName()
    {
        return $this->parent ? $this->parent->getName() : null;
    }

    public function getNextName()
    {
        return $this->next ? $this->next->getName() : null;
    }

    public function hasChildren()
    {
        return!empty($this->children);
    }

    public function hasMore()
    {
        return $this->hasChildren() || $this->hasNext();
    }

    public function hasNext()
    {
        return!is_null($this->next);
    }

    public function isRequired()
    {
        return $this->required;
    }

    public function offsetExists($offset)
    {
        throw new \InvalidArgumentException('Unexpected'); //FIXME
    }

    public function offsetGet($condition)
    {
        $this->last->condition = $condition;
        return $this;
    }

    public function offsetSet($offset, $value)
    {
        throw new \InvalidArgumentException('Unexpected'); //FIXME
    }

    public function offsetUnset($offset)
    {
        throw new \InvalidArgumentException('Unexpected'); //FIXME
    }

    public function setCondition($condition)
    {
        $this->condition = $condition;
    }

    public function setMapper(Mapper $mapper=null)
    {
        foreach ($this->children as $child)
            $child->setMapper($mapper);
        $this->mapper = $mapper;
    }

    public function setParent(Collection $parent)
    {
        $this->parent = $parent;
    }

    public function setNext(Collection $collection)
    {
        $collection->setParent($this);
        $this->next = $collection;
    }

    public function setRequired($required)
    {
        $this->required = $required;
    }

    protected function stack(Collection $collection)
    {
        $this->last->setNext($collection);
        $this->last = $collection;
        return $this;
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