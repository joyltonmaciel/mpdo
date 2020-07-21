<?php

namespace Mpdo;

use stdClass;

class TypeManipulations
{
    /**
     * Converte um array em object
     *
     * @param array $content
     * @return stdClass
     */
    public static function array2object($content)
    {
        if (!is_array($content)) {
            return $content;
        }

        $data = new stdClass();
        foreach ($content as $index => $line) {
            if (!is_array($line)) {
                $data->{$index} = $line;
            } else {
                $data->{$index} = TypeManipulations::array2object($line);
            }
        }
        return $data;
    }
}
