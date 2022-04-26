<?php

class Logger
{
    /**
     * @param $string
     * @return void
     */
    public static function log($string)
    {
        if (DEBUG_MODE) {
            echo $string . PHP_EOL;
        }
        file_put_contents(SERVER_LOG, $string . PHP_EOL, FILE_APPEND);
    }

}
