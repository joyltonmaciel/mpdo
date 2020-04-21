<?php

namespace Mpdo;

class Strings
{
    /**
     * Remove all non numeric characters from string
     * @author     Roberto L. Machado <linux dot rlm at gmail dot com>
     * @param string $string
     * @return string
     */
    public static function onlyNumbers($string)
    {
        return preg_replace("/[^0-9]/", "", $string);
    }

}
