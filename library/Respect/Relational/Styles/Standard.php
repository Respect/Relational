<?php
namespace Respect\Relational\Styles;

class Standard implements Stylable
{

    private function camelCaseToUnderscore($name)
    {
        return preg_replace('/(?<=[a-z])([A-Z])/', '_$1', $name);
    }

    private function underscoreToCamelCase($name)
    {
        return preg_replace("/(_)([a-zA-Z])/e", 'strtoupper("$2")', $name);
    }

    public function columnToProperty($name)
    {
        return $name;
    }

    public function entityToTable($name)
    {
        $name = $this->camelCaseToUnderscore($name);
        return strtolower($name);
    }

    public function propertyToColumn($name)
    {
        return $name;
    }

    public function tableToEntity($name)
    {
        $name = $this->underscoreToCamelCase($name);
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

    public function manyFromRightLeft($right, $left)
    {
        return "{$right}_{$left}";
    }


}

