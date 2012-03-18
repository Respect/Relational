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

    public function isForeignColumn($name)
    {
        return (strlen($name) - 2 === strripos($name, 'ID'));
    }

    public function tableFromForeignColumn($name)
    {
        if ($this->isForeignColumn($name)) {
            return $this->singularToPlural(substr($name, 0, -2));
        }
    }

}

