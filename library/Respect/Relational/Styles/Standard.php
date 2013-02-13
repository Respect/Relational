<?php

namespace Respect\Relational\Styles;

class Standard extends AbstractStyle
{

    public function styledProperty($name)
    {
        return $name;
    }

    public function realName($name)
    {
        $name = $this->camelCaseToSeparator($name, '_');
        return strtolower($name);
    }

    public function realProperty($name)
    {
        return $name;
    }

    public function styledName($name)
    {
        $name = $this->separatorToCamelCase($name, '_');
        return ucfirst($name);
    }

    public function identifier($name)
    {
        return 'id';
    }
    
    public function remoteIdentifier($name)
    {
        return $name . '_id';
    }

    public function composed($left, $right)
    {
        return "{$left}_{$right}";
    }

    public function isRemoteIdentifier($name)
    {
        return (strlen($name) - 3 === strripos($name, '_id'));
    }

    public function remoteFromIdentifier($name)
    {
        if ($this->isRemoteIdentifier($name)) {
            return substr($name, 0, -3);
        }
    }

}
