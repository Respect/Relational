<?php

namespace Respect\Relational\Styles;

class Sakila extends Standard
{

    public function primaryFromTable($name)
    {
        return $this->foreignFromTable($name);
    }

}

