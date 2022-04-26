<?php

class Server
{
    public $errors = [];

    public $socket;

    public $address, $port, $real_ip, $config;

    /**
     * @param $config
     * @return void
     */
    public function launchServer()
    {
        if (!$this->createSocket()) {
            die($this->getErrors());
        }
        Logger::log("start expss socket server");
        Storage::commandServer($this->server, 'active');
        $this->worker();
    }

    public function stopServer()
    {
        $command = "stopped";
        Storage::commandServer($this->server, $command);
    }

    public function updateServerStatus()
    {
        return Storage::getServerCommand($this->server);
    }

    public function __construct($config_file)
    {
        $config_file = APP . DS . $config_file;
        if (!file_exists($config_file)) {
            $save_log = date("Y-m-d H:i:s") . " errors: file $config_file is not exists";
            Logger::log($save_log);
            exit;
        }
        $config_file_data = file_get_contents($config_file);
        $config = json_decode($config_file_data, true);
        $this->config = $config;
        $this->setServer($config['address']);
        $this->setPort($config['port']);
        $this->real_ip = $config['address'];
        $this->log_file = $config['log_file_source'];
        $this->server = $config["name"];
        $this->max_connection_lifetime = $config["max_connection_lifetime"];
        $this->max_event_lifetime = $config["max_event_lifetime"];
    }

    private function setServer($address)
    {
        $this->address = $address;
    }

    private function setPort($port)
    {
        $this->port = $port;
    }

    public function createSocket()
    {
        $address = $this->address;
        $port = $this->port;
        if (($new_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
            $this->errors[] = "socket_create() error: " . socket_strerror(socket_last_error()) . "\n";
            return false;
        }

        if (socket_bind($new_socket, $address, $port) === false) {
            $this->errors[] = "socket_bind() error: " . socket_strerror(socket_last_error($new_socket)) . "\n";
            return false;
        }

        if (socket_listen($new_socket, 10) === false) {
            $this->errors[] = "socket_listen() error: " . socket_strerror(socket_last_error($new_socket)) . "\n";
            return false;
        }
        $this->socket = $new_socket;
        $this->address = $address;
        $this->port = $port;
        if (count($this->errors) > 0) {
            $save_log = date("Y-m-d H:i:s") . " errors: " . join(" ", $this->errors);
            Logger::log($save_log);
        }
        return true;
    }

    public function stopSocket()
    {
        // Close the master sockets
        socket_close($this->socket);
    }

    /**
     * @param $client_socket
     * @param $address
     * @param $message
     * @param $client_key
     * @return bool
     */
    public function socketSendPartial($client_socket, $address, $message, $client_key)
    {
        $parts = ceil(mb_strlen($message) / SOCKET_BUFFER_PARTIAL);
        $message_hash_prefix = Format::getMessageHash($message);
        Storage::createPartial($this->server, "out", $message_hash_prefix);
        for ($msc = 0; $msc < $parts; $msc++) {
            $message_partial = mb_substr($message, $msc * SOCKET_BUFFER_PARTIAL, SOCKET_BUFFER_PARTIAL);
            $part_number = Format::partBlockName($msc);
            $message_partial_with_tags = Format::partialMessageFormat($message_hash_prefix, $part_number, $message_partial, $parts);

            // saving partial message data
            Storage::savePartialElement($this->server, "out", $msc, $message_hash_prefix, $message_partial);

            $final_data = $client_key . $message_partial_with_tags;
            $result = socket_write($client_socket, $final_data, mb_strlen($final_data));
            if ($result === false) {
                Logger::log("error: " . socket_strerror(socket_last_error($client_socket)));
            }
        }

        $save_log = date("Y-m-d H:i:s") . ", response: address: $address:$this->port, big data, source md5: md5($message), real_ip: $this->server";
        Logger::log($save_log);
        return true;
    }

    /**
     * @param $message
     * @param $client_key
     * @param $address
     * @param $client_socket
     * @return void
     */
    public function sendMessage($message, $client_key, $address, $client_socket)
    {
        // Display output  back to client
        if (mb_strlen($message) > SOCKET_BUFFER_PARTIAL) {
            $this->socketSendPartial($client_socket, $address, $message, $client_key);
        } else {
            $response = EXPSS_CODE . $message;
            $coded_response = $client_key . $response;
            $save_log = date("Y-m-d H:i:s") . ", response: address: $address:$this->port, small data, real_ip: $this->real_ip";
            Logger::log($save_log);
            socket_write($client_socket, $coded_response, mb_strlen($coded_response));
        }
    }

    /**
     * @param $hash
     * @return void
     */
    private function setPartialStart($hash){
        if(!in_array($hash, array_keys($this->partial_start))){
            $this->partial_start[$hash] = time();
        }
    }

    /**
     * @param $input
     * @return bool
     */
    private function detectReceivingPartialMessage($input)
    {
        if (mb_substr_count($input, "message_hash:") and mb_substr_count($input, ":part:")) {
            //partial message has view like this
            //"message_hash:" . $message_hash_prefix . ":partial_hash:" . $hash_prefix . ":__PARTIAL__" . $message_partial
            $partial__arr = explode("__PARTIAL__", $input);
            $partial__string = $partial__arr[0];
            $partial_params = explode(":", $partial__string);
            $this->message_hash = $partial_params[1];
            $this->partial_hash = $partial_params[3];
            $this->real_message = $partial__arr[1];
            $this->part_count = $partial_params[4];

            // fill partial blocks
            for ($t = 0; $t < $this->part_count; $t++) {
                $part_number = sprintf("%06s", $t);
                Storage::fillPartialElements($this->server, "inc", $part_number, $this->message_hash);
            }
            Storage::checkPartialReceivingIsComplete($this->server, $this->message_hash, $this->part_count);
            return true;
        }
        return false;
    }

    /*
     * Main Socket Server Method
     * */
    public function worker()
    {
        $this->time_counter = microtime(true);
        /* Accept incoming  requests */

        while (true) {
            socket_set_block($this->socket);
            $client = socket_accept($this->socket);
            //loop and listen
            while ($input = @socket_read($client, SOCKET_BUFFER_SIZE)) {

                if ($this->updateServerStatus() !== "active") {
                    break;
                }

                // Read the input  from the client
                if ($input !== false) {
                    $address = $this->address;
                    $port = $this->port;
                    if (@socket_getpeername($client, $address, $port)) {
                        Logger::log("client: $address:$port");
                    }
                    $save_log = date("Y-m-d H:i:s") . ", incoming message. address: $address:$this->port, source: $input, real_ip: $this->real_ip";
                    Logger::log($save_log);

                    // check for client key exists
                    $client_key = mb_substr($input, 0, Storage::$client_code_lentgh);
                    if (!Storage::checkClientKey($this->server, $client_key)) {
                        $mess = "error: client with this key not found";
                        $save_log = date("Y-m-d H:i:s") . ", error response message. address: $address:$this->port, $mess, real_ip: $this->real_ip";
                        Logger::log($save_log);
                        $this->sendMessage($mess, $client_key, $address, $client);
                        continue;
                    }
                    $input = mb_substr($input, Storage::$client_code_lentgh);
                    if (!Format::isCorrectDecoding($input)) {
                        $mess = "error: incorrect decoding message";
                        $save_log = date("Y-m-d H:i:s") . ", error response: address: $address:$this->port, $mess, real_ip: $this->real_ip";
                        Logger::log($save_log);
                        $this->sendMessage($mess, $client_key, $address, $client);
                        continue;
                    }

                    $real_message = Format::realMessage($input);

                    if (Format::partialComplete($real_message)) {
                        $this->message_hash = Format::partialMessageHash($real_message);
                        Logger::log("receiving is complete");
                        $total_message = Storage::getFullMessageContent($this->server, $this->message_hash, "inc");
                        Storage::clearPartial($this->server, "inc", $this->message_hash);
                        $rec_time = time() - $this->partial_start[$this->message_hash];
                        Storage::addConnection($this->server, "inc", $address, $port, $total_message, $rec_time);

                        // from here you can handle correct client request and use app logic
                        $response = "request from server " . substr(md5($total_message), 0, 7) . PHP_EOL;
                        Logger::log($response);

                        Storage::addConnection($this->server, "out", $address, $port, $response, 0);
                        $this->sendMessage($response, $client_key, $address, $client);
                        continue;
                    }
                    if ($this->detectReceivingPartialMessage($real_message)) {

                        //still receiving big data
                        $this->message_hash = Format::partialMessageHash($real_message);
                        $this->setPartialStart($this->message_hash);

                        Storage::savePartialElement($this->server, "inc",
                            $this->partial_hash, $this->message_hash, $this->real_message);

                        Logger::log("source message: " . $this->real_message);
                        $success_response = Format::partialSuccessFormat($this->message_hash, $this->partial_hash);
                        $this->sendMessage($success_response, $client_key, $address, $client);
                    } else {
                        //receive small single message
                        Storage::addConnection($this->server, "inc", $address, $port, $real_message, 0);

                        // from here you can handle correct client request and use app logic
                        App::messageIncomingHandler($address, $port, $real_message);

                        //response for socket request with your application logic
                        $response = App::messageSendingHandler($real_message) ?? " message received ";
                        Logger::log("input is $real_message");
                        Storage::addConnection($this->server, "out", $address, $port, $real_message, 0);
                        $this->sendMessage($response, $client_key, $address, $client);
                    }

                }
            }
            Storage::clearServer($this->server, $this->max_connection_lifetime, $this->max_event_lifetime);
            @socket_close($client);
        }

    }

    public function getErrors()
    {
        return join(" ", $this->errors);
    }

}