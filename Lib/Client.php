<?php
require_once('Config.php');
require_once('Cypher.php');

class Client
{
    public $errors = [];

    public $server, $port, $code_key, $config, $message_stack;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function useServer($server_name)
    {
        $client_init = false;
        if (!key_exists("servers", $this->config)) {
            die("requested server is not found in config file");
        }
        foreach ($this->config["servers"] as $val) {
            if ($val['name'] == $server_name) {
                $this->setServer($val['address']);
                $this->setPort($val['port']);
                $this->setCodeKey($val['server_pass']);
                $this->client_pass = $this->config['client_token'];
                $this->server_name = $val['name'];
                $client_init = true;
            }
        }
        if (!$client_init) {
            die("requested server is not found in config file");
        }
        return $this;

    }

    private function setCodeKey($code_key)
    {
        $this->code_key = $code_key;
    }

    private function setServer($address)
    {
        $this->server = $address;
    }

    private function setPort($port)
    {
        $this->port = $port;
    }

    /**
     * @param $message
     * @return void
     */
    private function addLog($message)
    {
        $message = $message . "\n";
        file_put_contents(APP . DS . "client.log", $message, FILE_APPEND);
    }

    public function connectSocket()
    {
        /* Создаём сокет TCP/IP. */
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->socket === false) {
            echo "Не удалось выполнить socket_create(): причина: " . socket_strerror(socket_last_error()) . "\n";
        } else {
            echo "OK.\n";
        }

        echo "Пытаемся соединиться с '$this->server' на порту '$this->port'...";
        $result = socket_connect($this->socket, $this->server, $this->port);
        if ($result === false) {
            echo "Не удалось выполнить socket_connect().\nПричина: ($result) " . socket_strerror(socket_last_error($this->socket)) . "\n";
        } else {
            echo "OK.\n";
        }
    }

    /**
     * @param $message
     * @param $connector
     * @return string
     */
    public function codeMessage($message, $connector)
    {
        $receiver_hashcode = $this->cypher->receiver_hashcode($this->server_name);
        echo "client code cypher: " . $receiver_hashcode . " > " . $this->code_key . " / " . $this->server_name;
        return $this->cypher->codeMessage($message, $this->code_key, $connector, $receiver_hashcode);
    }

    /**
     * @param $coded_message
     * @param $connector
     * @return string
     */
    public function decodeMessage($coded_message, $connector)
    {
        $sender_hashcode = $this->cypher->sender_hashcode($connector);
        echo "client decode cypher: " . $sender_hashcode . " > " . $this->code_key . " / " . $this->server_name;
        return $this->cypher->decodeMessage($coded_message, $this->code_key, $sender_hashcode, $this->server_name);
    }

    public function socketSendConnector()
    {
        echo "Отправляем сообщение connect:" . $this->config['client_token'] . "....\n";
        $request_mess = "connect:" . $this->config['client_token'];
        $client_token = $this->config['client_token'];
        $save_log = date("Y-m-d H:i:s") . ", address: $this->server, source: $request_mess, code_key: $this->code_key, real_ip: $this->server,
            client_token: $client_token";

        $this->addLog($save_log);
        $result = socket_write($this->socket, $request_mess, mb_strlen($request_mess));
        if ($result === false) {
            echo "error: " . socket_strerror(socket_last_error($this->socket)) . "\n";
        }
    }

    /**
     * @param $message
     * @return void
     */
    public function socketSend($message)
    {
        echo "Отправляем сообщение....\n";
        echo "отправляем исходное: $message.\n";
        $coded_message = $this->codeMessage($message, $this->config['client_token']);
        echo "после шифра: $coded_message";
        $result = socket_write($this->socket, $coded_message, mb_strlen($coded_message));
        if ($result === false) {
            echo "error: " . socket_strerror(socket_last_error($this->socket)) . "\n";
        }
        $client_token = $this->config['client_token'];
        $save_log = date("Y-m-d H:i:s") . ", address: $this->server:$this->port, source: $coded_message, decoded message, code_key: $this->code_key, real_ip: $this->server,
            client_token: $client_token";

        $this->addLog($save_log);
        echo "OK.\n";
    }

    /**
     * @return string
     */
    public function socketRead()
    {
        echo "Читаем ответ!:\n";
        $resp = socket_read($this->socket, 65536);
        if ($resp === false) {
            echo "error: " . socket_strerror(socket_last_error($this->socket)) . "\n";
        }
        echo "исходное: $resp.\n";
        echo "после шифра: \n";
        $resp = $this->decodeMessage($resp, $this->config['client_token']);
        echo $resp . "\n";
        return $resp;
    }

    /**
     * @return void
     */
    public function socketStop()
    {
        echo "Закрываем сокет...";
        socket_close($this->socket);
        echo "OK.\n\n";
    }

}