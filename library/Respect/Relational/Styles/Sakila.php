<?php

namespace Respect\Relational\Styles;

class Sakila extends Standard
{

    public function identifier($name)
    {
        return $this->remoteIdentifier($name);
    }

}

