<?php

namespace Respect\Relational\Styles;

class CakePHP extends Standard
{

    public function entityToTable($name)
    {
        $name       = $this->camelCaseToSeparator($name, '_');
        $name       = strtolower($name);
        $pieces     = explode('_', $name);
        $pieces[]   = $this->singularToPlural(array_pop($pieces));
        return implode('_', $pieces);
    }
    
    public function foreignFromTable($name)
    {
        return $this->pluralToSingular($name) . '_id';
    }

    public function tableFromForeignColumn($name)
    {
        if ($this->isForeignColumn($name)) {
            return $this->singularToPlural(substr($name, 0, -3));
        }
    }

    public function tableToEntity($name)
    {
        $pieces     = explode('_', $name);
        $pieces[]   = $this->pluralToSingular(array_pop($pieces));
        $name       = $this->separatorToCamelCase(implode('_', $pieces), '_');
        return ucfirst($name);
    }

    public function manyFromLeftRight($left, $right)
    {
        $pieces     = explode('_', $right);
        $pieces[]   = $this->singularToPlural(array_pop($pieces));
        $right      = implode('_', $pieces);
        return "{$left}_{$right}";
    }

}

