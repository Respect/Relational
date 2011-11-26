<?php

namespace Respect\Relational;


class AbstractMapper
{

    protected $collections;

    public function __get($name)
    {
        if (isset($this->collections[$name]))
            return $this->collections[$name];
                
        $this->collections[$name] = new Collection($name);
        $this->collections[$name]->setMapper($this);

        return $this->collections[$name];
    }
    
    public function __set($name, $collection) 
    {
        return $this->registerCollection($name, $collection);
    }
    
    public function __call($name, $children)
    {
        $collection = Collection::__callstatic($name, $children);
        $collection->setMapper($this);
        return $collection;
    }

    public function registerCollection($name, Collection $collection)
    {
        $this->collections[$name] = $collection;
    }

}
