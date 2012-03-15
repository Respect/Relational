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

    public function manyFromRightLeft($right, $left)
    {
        $pieces     = explode('_', $left);
        $pieces[]   = $this->singularToPlural(array_pop($pieces));
        $left       = implode('_', $pieces);
        return "{$right}_{$left}";
    }




}

