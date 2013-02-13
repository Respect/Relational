<?php

namespace Respect\Relational\Styles;

class NorthWind extends Standard
{

    public function realName($name)
    {
        return $name;
    }

    public function styledName($name)
    {
        return $name;
    }

    public function composed($left, $right)
    {
        $left = $this->pluralToSingular($left);
        return "{$left}{$right}";
    }

    public function identifier($name)
    {
        return $this->pluralToSingular($name) . 'ID';
    }

    public function remoteIdentifier($name)
    {
        return $this->pluralToSingular($name) . 'ID';
    }

    public function isRemoteIdentifier($name)
    {
        return (strlen($name) - 2 === strripos($name, 'ID'));
    }

    public function remoteFromIdentifier($name)
    {
        if ($this->isRemoteIdentifier($name)) {
            return $this->singularToPlural(substr($name, 0, -2));
        }
    }

}

