<?php

namespace Respect\Relational\Styles;

class Standard extends AbstractStyle
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

    public function propertyToColumn($name)
    {
        return $name;
    }

    public function tableToEntity($name)
    {
        $name = $this->separatorToCamelCase($name, '_');
        return ucfirst($name);
    }

    public function primaryFromTable($name)
    {
        return 'id';
    }
    
    public function foreignFromTable($name)
    {
        return $name . '_id';
    }

    public function manyFromLeftRight($left, $right)
    {
        return "{$left}_{$right}";
    }

    public function isForeignColumn($name)
    {
        return (strlen($name) - 3 === strripos($name, '_id'));
    }

    public function tableFromForeignColumn($name)
    {
        if ($this->isForeignColumn($name)) {
            return substr($name, 0, -3);
        }
    }

}
