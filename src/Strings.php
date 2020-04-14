<?php

namespace Mpdo;

class Strings
{
    /**
     * Remove all non numeric characters from string
     * @param string $string
     * @return string
     */
    public static function onlyNumbers($string)
    {
        return preg_replace("/[^0-9]/", "", $string);
    }

}