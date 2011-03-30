<?php

namespace Respect\Relational;

use RecursiveArrayIterator;
use RecursiveIteratorIterator;

class FinderIterator extends RecursiveArrayIterator
{

    protected $entityReferenceCount = array();
    protected $parent = null;

    public static function recursive($target)
    {
        return new RecursiveIteratorIterator(new static($target), 1);
    }

    public function __construct($target, $parent=null)
    {
        $this->parent = $parent;
        parent::__construct(is_array($target) ? $target : array($target));
    }

    protected function getAlias(Finder $finder)
    {
        $name = $finder->getEntityReference();
        if (!isset($this->entityReferenceCount[$name]))
            $this->entityReferenceCount[$name] = 1;
        else
            $this->entityReferenceCount[$name]++;

        return $name . $this->entityReferenceCount[$name];
    }

    public function current()
    {
        $current = parent::current();

        $finders = array(
            $this->getAlias($current) => $current
        );

        if ($this->parent)
            $finders[$this->getAlias($this->parent)] = $this->parent;

        return $finders;
    }

    public function hasChildren()
    {
        $c = parent::current();
        return (boolean) $c->hasChildren() || $c->hasNextSibling();
    }

    public function getChildren()
    {
        $c = parent::current();
        $pool = array();
        if ($c->hasChildren())
            $pool = $c->getChildren();
        if ($c->hasNextSibling())
            $pool[] = $c->getNextSibling();
        return new static($pool, $c);
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