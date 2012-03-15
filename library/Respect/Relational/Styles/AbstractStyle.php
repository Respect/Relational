<?php

namespace Respect\Relational\Styles;

abstract class AbstractStyle implements Stylable
{

    protected function camelCaseToSeparator($name, $separator = '_')
    {
        return preg_replace('/(?<=[a-z])([A-Z])/', $separator . '$1', $name);
    }

    protected function separatorToCamelCase($name, $separator = '_')
    {
        $separator = preg_quote($separator, '/');
        return preg_replace(
            "/({$separator})([a-zA-Z])/e", 
            'strtoupper("$2")', 
            $name
        );
    }


}

