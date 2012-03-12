<?php
namespace Respect\Relational\Styles;
interface Stylable
{

    function tableToEntity($tableName);

    function entityToTable($entityName);

    function columnToProperty($columnName);

    function propertyToColumn($propertyName);

    function primaryFromTable($tableName);

    function manyFromLeftRight($left, $right);

    function manyFromRightLeft($right, $left);


}

