<?php

namespace Respect\Relational\Styles;

class Sakila extends AbstractStyle
{

    public function columnToProperty($name)
    {
        return $name;
    }

    public function entityToTable($name)
    {
        $name = $this->camelCaseToSeparator($name, '_');
        return strtolower($name);
    }

    public function foreignFromTable($name)
    {
        return "{$name}_id";
    }

    public function manyFromLeftRight($left, $right)
    {
        return "{$left}_{$right}";
    }

    public function manyFromRightLeft($right, $left)
    {
        return "{$right}_{$left}";
    }

    public function primaryFromTable($name)
    {
        return $this->foreignFromTable($name);
    }

    public function propertyToColumn($name)
    {
        return $name;
    }

    public function tableToEntity($name)
    {
        $name = $this->separatorToCamelCase($name, '_');
        return ucfirst($name);
    }


}

