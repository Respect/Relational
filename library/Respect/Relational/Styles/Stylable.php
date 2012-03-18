<?php

namespace Respect\Relational\Styles;

interface Stylable
{

    function tableToEntity($name);

    function entityToTable($name);

    function columnToProperty($name);

    function propertyToColumn($name);

    function primaryFromTable($name);

    function foreignFromTable($name);

    function tableFromForeignColumn($name);
    
    function isForeignColumn($name);

    function manyFromLeftRight($left, $right);

}

