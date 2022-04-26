<?php

class Event
{
    /*

    public static function eventRequestHandler($ip, $request, $datetime){
        $event_name = "connection_request";
        self::saveEvent($event_name, $ip, $request, $datetime);
    }

    public static function eventResponseHandler($ip, $response, $datetime){
        $event_name = "connection_response";
        self::saveEvent($event_name, $ip, $response, $datetime);
    }
    */

    /**
     * @param $server
     * @param $event_name
     * @param $data
     * @return false|int
     */
    public static function saveEvent($server, $event_name, $data)
    {
        $event_dir = Storage::getEventDir($server);
        if (!is_dir($event_dir)) {
            mkdir($event_dir);
        }
        $data = empty($data) ? null : json_encode($data);
        $event_file = $event_dir . DS . $event_name . "_" . uniqid();
        return file_put_contents($event_file, $data,);

    }

    /**
     * @param $server
     * @return array|false
     */
    public static function getServerEvents($server)
    {
        $dir = Storage::getEventDir($server);
        $ignored = array('.', '..');
        $files = array();
        foreach (scandir($dir) as $file_x) {
            if (in_array($file_x, $ignored)) continue;
            $files[$file_x] = filemtime($dir . '/' . $file_x);
        }
        arsort($files);
        $files = array_keys($files);
        return ($files) ? $files : false;
    }
}