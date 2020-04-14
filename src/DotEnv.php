<?php

namespace Mpdo;

class DotEnv
{
    /**
     * Get the contents of the .env file
     * @return string
     */
    public static function getDotEnvData()
    {
        $file = __DIR__ . '/../../../../.env';
        if (file_exists($file)) {
            return json_decode(json_encode(parse_ini_file($file)));
        }
        throw new \Exception("No Database settings (.env).");
    }
}
