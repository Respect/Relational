<?php

namespace Respect\Relational;

use RecursiveArrayIterator;
use RecursiveIteratorIterator;

class CollectionIterator extends RecursiveArrayIterator
{

    protected $nameCount = array();

    public static function recursive($target)
    {
        return new RecursiveIteratorIterator(new static($target), 1);
    }

    public function __construct($target, &$nameCount=array())
    {
        $this->nameCount = &$nameCount;
        parent::__construct(is_array($target) ? $target : array($target));
    }

    public function key()
    {
        $name = $this->current()->getName();

        if (isset($this->nameCount[$name]))
            return $name . ++$this->nameCount[$name];

        $this->nameCount[$name] = 1;
        return $name;
    }

    public function hasChildren()
    {
        $c = $this->current();
        return (boolean) $c->hasChildren() || $c->hasNext();
    }

    public function getChildren()
    {
        $c = $this->current();
        $pool = array();

        if ($c->hasChildren())
            $pool = $c->getChildren();

        if ($c->hasNext())
            $pool[] = $c->getNext();

        return new static($pool, $this->nameCount);
    }

}

