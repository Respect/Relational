<?php

namespace Respect\Relational\Styles;

class NorthWind extends Standard
{

    public function entityToTable($name)
    {
        return $name;
    }

    public function tableToEntity($name)
    {
        return $name;
    }

    public function manyFromLeftRight($left, $right)
    {
        $left = $this->pluralToSingular($left);
        return "{$left}{$right}";
    }

    public function primaryFromTable($name)
    {
        return $this->pluralToSingular($name) . 'ID';
    }

    public function foreignFromTable($name)
    {
        return $this->pluralToSingular($name) . 'ID';
    }

}

