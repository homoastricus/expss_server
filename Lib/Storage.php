<?php

class Storage
{
    public static $client_code_lentgh = 24;

    public static $storage_dir, $server_dir, $clients_dir, $connections_dir, $events_dir;

    private static $file_name_symbols = [
        '-', '0', '1',
        '2', '3', '4', '5', '6', '7', '8', '9',
        'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K',
        'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W',
        'X', 'Y', 'Z', '_', 'a', 'b', 'c', 'd', 'e', 'f', 'g',
        'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's',
        't', 'u', 'v', 'w', 'x', 'y', 'z',
    ];

    /**
     * @param $server
     * @return void
     */
    public static function setup($server)
    {
        self::$storage_dir = APP . DS . "storage";
        if (!is_dir(self::$storage_dir)) {
            mkdir(self::$storage_dir);
        }

        $log_file = APP . DS . "server.log";
        if (!file_exists($log_file)) {
            file_put_contents($log_file, "");
        }

        $server_dir = self::$storage_dir . DS . $server;
        if (!is_dir($server_dir)) {
            mkdir($server_dir);
            mkdir($server_dir . DS . "clients");
            mkdir($server_dir . DS . "connections");
            mkdir($server_dir . DS . "events");
            mkdir($server_dir . DS . "inc");
            mkdir($server_dir . DS . "out");
            file_put_contents($server_dir . DS . "status", "stopped");
        }
        self::$server_dir = $server_dir;
        self::$clients_dir = $server_dir . DS . "clients";
        self::$connections_dir = $server_dir . DS . "connections";
        self::$events_dir = $server_dir . DS . "events";
    }

    /**
     * @param $server
     * @return string
     */
    public static function getClientDir($server)
    {
        self::setup($server);
        return self::$clients_dir;
    }

    /**
     * @param $server
     * @return mixed
     */
    public static function getEventDir($server)
    {
        self::setup($server);
        return self::$events_dir;
    }

    /**
     * @param $server
     * @param $client_ip
     * @param $client_name
     * @return false|int
     */
    public static function addClient($server, $client_ip, $client_name)
    {
        self::setup($server);
        $clients_file = self::$clients_dir . DS . $client_ip;
        return file_put_contents($clients_file, $client_name);
    }

    /**
     * @param $server
     * @param $event
     * @param $event_name
     * @return false|int
     */
    public static function addEvent($server, $event, $event_name)
    {
        self::setup($server);
        $events_dir_file = self::$events_dir . DS . $event_name . "__" . time();
        return file_put_contents($events_dir_file, $event);
    }

    /**
     * @param $server
     * @param $direction
     * @param $ip
     * @param $port
     * @param $data
     * @param $receive_time
     * @return false|int
     */
    public static function addConnection($server, $direction, $ip, $port, $data, $receive_time)
    {
        self::setup($server);
        $server_dir = self::$storage_dir . DS . $server;
        self::$connections_dir = $server_dir . DS . "connections";
        $hash = substr(md5($data), 0, 7);
        $length = mb_strlen($data);
        $dates = date("Y-m-d H:i:s");
        $dates = str_replace(" ", "_", $dates);
        $dates = str_replace(":", "-", $dates);
        $file_name = $direction . "_" . $ip . "_" . $port . "_" . $dates . "_" . $hash . "_" . $length . "_" . $receive_time;
        $connection_file = self::$connections_dir . DS . $file_name;
        return file_put_contents($connection_file, $data);
    }

    /**
     * @param $server
     * @param $client_ip
     * @param $client_name
     * @return void
     */
    public static function updateClient($server, $client_ip, $client_name)
    {
    }

    public static function clearServer($server, $max_connection_lifetime, $max_event_lifetime)
    {
        $server_dir = self::$storage_dir . DS . $server;
        //clear expired connections
        self::$connections_dir = $server_dir . DS . "connections";
        $expire_conn = strtotime("-$max_connection_lifetime DAYS");
        $con_files = glob(self::$connections_dir . '/*');
        foreach ($con_files as $con_file) {
            // Skip anything that is not a file
            if (!is_file($con_file)) {
                continue;
            }
            // Skip any files that have not expired
            if (filemtime($con_file) > $expire_conn) {
                continue;
            }
            unlink($con_file);
        }
        //clear expired events
        self::$events_dir = $server_dir . DS . "events";
        $expire_events = strtotime("-$max_event_lifetime DAYS");
        $ev_files = glob(self::$events_dir . '/*');
        foreach ($ev_files as $ev_file) {
            // Skip anything that is not a file
            if (!is_file($ev_file)) {
                continue;
            }
            // Skip any files that have not expired
            if (filemtime($ev_file) > $expire_events) {
                continue;
            }
            unlink($ev_file);
        }
    }

    /**
     * @param $dir
     * @return bool
     */
    private static function deleteRecursive($dir)
    {
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? self::deleteRecursive("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }

    /**
     * @param $server
     * @return void
     */
    public static function deleteServer($server)
    {
        self::setup($server);
        $server_dir = self::$storage_dir . DS . $server;
        if (is_dir($server_dir) && is_readable($server_dir)) {
            self::deleteRecursive($server_dir);
        }
    }

    /**
     * @param $old_server
     * @param $new_name
     * @return void
     */
    public static function renameServer($old_server, $new_name)
    {
        self::setup($old_server);
        $new_server_dir = self::$storage_dir . DS . $new_name;
        rename(self::$storage_dir . DS . $old_server, self::$storage_dir . DS . $new_name);
        self::$server_dir = $new_server_dir;
        self::$clients_dir = $new_server_dir . DS . "clients";
        self::$connections_dir = $new_server_dir . DS . "connections";
        self::$events_dir = $new_server_dir . DS . "events";
    }

    /**
     * @param $server
     * @return array|false
     */
    public static function getServerClients($server)
    {
        self::setup($server);
        return scandir(self::$clients_dir, SCANDIR_SORT_ASCENDING);
    }

    /**
     * @param $server
     * @return array|false
     */
    public static function getServerEvents($server)
    {
        self::setup($server);
        return scandir(self::$events_dir, SCANDIR_SORT_ASCENDING);
    }

    /**
     * @param $server
     * @return array|false
     */
    public static function getServerConnections($server)
    {
        self::setup($server);
        $dir = self::$connections_dir;
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

    /**
     * @param $server
     * @param $client_key
     * @return false
     */
    public static function checkClientKey($server, $client_key)
    {
        self::setup($server);
        $client_key_filename = self::$clients_dir . DS . $client_key;
        if (file_exists($client_key_filename)) {
            return true;
        }
        return false;
    }

    /**
     * @param $server
     * @return false|string
     */
    public static function getServerCommand($server)
    {
        self::setup($server);
        return file_get_contents(self::$server_dir . DS . "status");
    }

    /**
     * @param $server
     * @param $command
     * @return false|int
     */
    public static function commandServer($server, $command)
    {
        self::setup($server);
        return file_put_contents(self::$server_dir . DS . "status", $command);
    }

    /**
     * @param $server
     * @return false|int
     */
    public static function getServerUptime($server)
    {
        self::setup($server);
        return filemtime(self::$server_dir . DS . "status");
    }

    /**
     * @param $server
     * @return int
     */
    public static function storageSize($server)
    {
        self::setup($server);
        $size = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::$server_dir)) as $file) {
            $size += $file->getSize();
        }
        return self::fileSizeConvert($size);
    }

    /**
     * @param $bytes
     * @return string
     */
    private static function fileSizeConvert($bytes)
    {
        $bytes = floatval($bytes);
        $arBytes = array(
            0 => array(
                "UNIT" => "TB",
                "VALUE" => pow(1024, 4)
            ),
            1 => array(
                "UNIT" => "GB",
                "VALUE" => pow(1024, 3)
            ),
            2 => array(
                "UNIT" => "MB",
                "VALUE" => pow(1024, 2)
            ),
            3 => array(
                "UNIT" => "KB",
                "VALUE" => 1024
            ),
            4 => array(
                "UNIT" => "B",
                "VALUE" => 1
            ),
        );

        foreach ($arBytes as $arItem) {
            if ($bytes >= $arItem["VALUE"]) {
                $result = $bytes / $arItem["VALUE"];
                $result = str_replace(".", ",", strval(round($result, 2))) . " " . $arItem["UNIT"];
                break;
            }
        }
        return $result;
    }

    /**
     * @param $server
     * @param $client_key
     * @param $client_ip
     * @return false|int
     */
    public static function saveNewClient($server, $client_key, $client_ip)
    {
        self::setup($server);
        $clients_file = self::$clients_dir . DS . $client_key;
        file_put_contents($clients_file, $client_ip);
        $client_new_file = self::$clients_dir . DS . 'new';
        return file_put_contents($client_new_file, $client_key, FILE_APPEND);
    }

    /**
     * @param $server
     * @return false|int
     */
    public static function requiresAddServerClient($server)
    {
        self::setup($server);
        $clients_file = self::$clients_dir . DS . 'new';
        return file_put_contents($clients_file, "", FILE_APPEND);
    }

    public static function requiredAddServerClient($server)
    {
        self::setup($server);
        $clients_file = self::$clients_dir . DS . 'new';
        if (file_exists($clients_file) && file_get_contents($clients_file) == "") {
            return true;
        }
        return false;
    }

    /**
     * @param $server
     * @return false|string
     */
    public static function hasNewClient($server)
    {
        self::setup($server);
        $clients_file = self::$clients_dir . DS . 'new';
        if (!file_exists($clients_file) or file_get_contents($clients_file) == "") {
            return false;
        }
        return file_get_contents($clients_file);
    }

    /**
     * @param $server
     * @return bool
     */
    public static function clearNewClient($server)
    {
        self::setup($server);
        $clients_file = self::$clients_dir . DS . 'new';
        if (file_exists($clients_file)) {
            unlink($clients_file);
        }
        return true;
    }

    public static function generateClientKey()
    {
        $key = "";
        $client_key_length = self::$client_code_lentgh;
        $k_count = count(self::$file_name_symbols) - 1;
        for ($x = 0; $x < $client_key_length; $x++) {
            $key .= self::$file_name_symbols[mt_rand(0, $k_count)];
        }
        return $key;
    }

    /**
     * @param $server
     * @return bool
     */
    public static function clearClients($server)
    {
        self::setup($server);
        return self::clearDir(self::$clients_dir);
    }

    /**
     * @param $server
     * @return bool
     */
    public static function clearEvents($server)
    {
        self::setup($server);
        return self::clearDir(self::$events_dir);
    }

    /**
     * @param $server
     * @return bool
     */
    public static function clearConnections($server)
    {
        self::setup($server);
        return self::clearDir(self::$connections_dir);

    }

    /**
     * @param $dir
     * @return bool
     */
    private static function clearDir($dir)
    {
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? self::deleteRecursive("$dir/$file") : unlink("$dir/$file");
        }
        return true;
    }

    /**
     * @param $server
     * @param $direction
     * @param $message_hash
     * @return bool
     */
    public static function createPartial($server, $direction, $message_hash)
    {
        self::setup($server);
        $dir = self::$server_dir . DS . $direction . DS . $message_hash;
        if (!is_dir($dir)) {
            mkdir($dir);
        }
        return true;
    }

    /**
     * @param $server
     * @param $direction
     * @param $part_number
     * @param $message_hash
     * @return bool
     */
    public static function fillPartialElements($server, $direction, $part_number, $message_hash)
    {
        self::setup($server);
        self::createPartial($server, $direction, $message_hash);
        $part_file = self::$server_dir . DS . $direction . DS . $message_hash . DS . $part_number;
        if (!file_exists($part_file)) {
            file_put_contents($part_file, null);
        }
        return true;
    }

    /**
     * @param $server
     * @param $direction
     * @param $part_number
     * @param $message_hash_prefix
     * @param $content
     * @return void
     */
    public static function savePartialElement($server, $direction, $part_number, $message_hash_prefix, $content)
    {
        self::setup($server);
        $dir = self::$server_dir . DS . $direction . DS . $message_hash_prefix;
        if (!is_dir($dir)) {
            mkdir($dir);
        }
        $partial_element = $dir . DS . $part_number;
        file_put_contents($partial_element, $content);
    }

    /**
     * @param $server
     * @param $message_hash
     * @param $direction
     * @return array|false
     */
    public static function getPartialList($server, $message_hash, $direction)
    {
        self::setup($server);
        $partial_dir = self::$server_dir . DS . $direction . DS . $message_hash;
        if (!is_dir($partial_dir)) {
            return false;
        }
        return scandir($partial_dir, SCANDIR_SORT_ASCENDING);
    }

    /**
     * @param $server
     * @param $message_hash
     * @param $part_count
     * @return bool
     */
    public static function checkPartialSendingIsComplete($server, $message_hash, $part_count)
    {
        self::setup($server);
        $partial_complete = true;
        $partial_list = self::getPartialList($server, $message_hash, "out");
        if (!$partial_list) return false;
        if (count($partial_list) < $part_count + 2) {
            return false;
        }
        foreach ($partial_list as $partial_item) {
            if ($partial_item == "." or $partial_item == "..") continue;
            $partial_item_file = self::$server_dir . DS . "inc" . DS . $message_hash . DS;
            if (file_exists($partial_item_file . $partial_item) and
                filesize($partial_item_file . $partial_item) == 0) {
                $partial_complete = false;
                break;
            }
        }
        return $partial_complete;
    }

    /**
     * @param $server
     * @param $message_hash
     * @param $part_count
     * @return bool
     */
    public static function checkPartialReceivingIsComplete($server, $message_hash, $part_count)
    {
        self::setup($server);
        echo " WE TEST checkPartialReceivingIsComplete";
        $partial_complete = true;
        $partial_list = self::getPartialList($server, $message_hash, "inc");
        if (!$partial_list) return false;

        foreach ($partial_list as $partial_item) {
            if ($partial_item == "." or $partial_item == "..") continue;
            $partial_item_file = self::$server_dir . DS . "inc" . DS . $message_hash . DS;
            if (file_exists($partial_item_file . $partial_item) and
                filesize($partial_item_file . $partial_item) == 0) {
                $partial_complete = false;
                break;
            }
        }
        echo "checkPartialReceivingIsComplete " . $partial_complete;
        return $partial_complete;
    }

    /**
     * @param $server
     * @param $direction
     * @param $message_hash
     * @return void
     */
    public static function clearPartial($server, $direction, $message_hash)
    {
        self::setup($server);
        $dir = self::$server_dir . DS . $direction . DS . $message_hash;
        if (is_dir($dir) && is_readable($dir)) {
            self::deleteRecursive($dir);
        }

    }

    /**
     * @param $server
     * @param $message_hash
     * @param $direction
     * @return false|string
     */
    public static function getFullMessageContent($server, $message_hash, $direction)
    {
        $partial_list = self::getPartialList($server, $message_hash, $direction);
        if (!$partial_list) return false;
        $content = "";
        self::setup($server);
        $message_dir = self::$server_dir . DS . $direction . DS . $message_hash;
        foreach ($partial_list as $partial_item) {
            if ($partial_item == "." or $partial_item == "..") continue;
            if (file_exists($message_dir . DS . $partial_item) and filesize($message_dir . DS . $partial_item) > 0) {
                $partial_content = file_get_contents($message_dir . DS . $partial_item);
                $content .= $partial_content;
            }
        }
        return $content;
    }

    /**
     * @param $server
     * @param $message_hash
     * @return array
     */
    public static function requiresMessageBlocks($server, $message_hash)
    {
        $partial_list = self::getPartialList($server, $message_hash, "inc");
        if (!$partial_list) return [];
        $required_blocks = [];
        self::setup($server);
        $message_dir = self::$server_dir . DS . "inc" . DS . $message_hash;
        foreach ($partial_list as $partial_item) {
            if ($partial_item == "." or $partial_item == "..") continue;
            if (file_exists($message_dir . DS . $partial_item) and filesize($message_dir . DS . $partial_item) == 0) {
                $required_blocks[] = $partial_item;
            }
        }
        return $required_blocks;
    }

    /**
     * @param $file_config
     * @return string
     */
    public static function createServer($file_config){
        if(!file_exists($file_config)){
            die("Fatal error! Server configuration file '" . APP . DS . $file_config . "' is not exists!");
        }
        $config = file_get_contents(APP . DS . $file_config);
        $server_config = json_decode($config, true);
        if(!key_exists("name", $server_config)){
            die("Fatal error! Field 'name' for server name is not found in configuration file");
        }
        $server_name = $server_config["name"];
        self::setup($server_name);
        return $server_name;
    }

}