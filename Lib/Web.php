<?php

class Web
{
    public $errors = [];

    public $socket;

    public $address, $port, $real_ip, $config, $http_url, $headers, $start_generate_time;

    public function __construct($config_file)
    {
        $config_file = APP . DS . $config_file;
        if(!file_exists($config_file)){
            $save_log = date("Y-m-d H:i:s") . " errors: file $config_file is not exists";
            $this->addLog($save_log);
            exit;
        }
        $config_file_data = file_get_contents($config_file);
        $config = json_decode($config_file_data, true);
        $this->config = $config;
        $this->css_path = APP . "web" . DS . "css";
        $this->js_path = APP . "web" . DS . "javascript";
        $this->html_path = APP . "web" . DS . "html";
        $this->setServer("0.0.0.0");
        $this->setPort($config['web_port']);
        //$this->real_ip = $config['address'];
        $this->log_file = $config['log_file_source'];
        $this->max_connection_lifetime = $config['max_connection_lifetime'];
        $this->max_event_lifetime = $config['max_event_lifetime'];
        $this->start_time = microtime(true);
        $this->server_name = $config['name'];
        $this->socket_port = $config['port'];
    }

    private function setServer($address)
    {
        $this->address = $address;
    }

    private function setPort($port)
    {
        $this->port = $port;
    }

    private function addLog($message)
    {
        $message = $message . "\n";
        file_put_contents(APP . DS . $this->log_file, $message, FILE_APPEND);
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

        if (socket_listen($new_socket, 2) === false) {
            $this->errors[] = "socket_listen() error: " . socket_strerror(socket_last_error($new_socket)) . "\n";
            return false;
        }
        $this->socket = $new_socket;
        $this->address = $address;
        $this->port = $port;
        if (count($this->errors) > 0) {
            $save_log = date("Y-m-d H:i:s") . " errors: " . join(" ", $this->errors);
            $this->addLog($save_log);
            echo join(" ", $this->errors);
        }
        return true;
    }

    public function worker()
    {
        /* Accept incoming  requests and handle them as child processes */
        while (true) {
            //loop and listen
            $client = socket_accept($this->socket);
            while (($input = socket_read($client, 65536)) !== false) {
                // чтение данных
                if ($input !== false) {
                    $this->start_generate_time = microtime(true);
                    $address = $this->address;
                    $port = $this->port;
                    if (@socket_getpeername($client, $address, $port)) {
                        echo "client: $address:$port\n";
                    }

                    $this->headers = "";

                    $this->parseHeaders($input);

                    $content_detector = [
                        'html' => 'htmlGET',
                        'php' => 'htmlGET',
                        'css' => 'cssGET',
                        'js' => 'javascriptGET',
                        'jpg' => 'htmlImageGET',
                        'gif' => 'htmlImageGET',
                        'png' => 'htmlImageGET',
                        'jpeg' => 'htmlImageGET',
                    ];
                    $this->detectContent($client, $content_detector);
                    $save_log = date("Y-m-d H:i:s") . ", incoming: address: $address, source: $input";
                    $this->addLog($save_log);
                }
                socket_close($client);
            }

        }

    }

    /**
     * @param $client
     * @param $content_detector
     * @return void
     */
    private function detectContent($client, $content_detector)
    {
        if ($this->http_url == "/") {
            $this->http_url = "/html/index.php";
        }
        if ($this->http_url == "/about") {
            $this->http_url = "/html/about.php";
        }
        if ($this->http_url == "/contact") {
            $this->http_url = "/html/contact.php";
        }
        $path_parts = pathinfo($this->http_url);
        if (empty($path_parts['extension'])) {
            // вызван action а не файл
            $path_parts['dirname'] = str_replace("/", "", $path_parts['dirname']);

            if ($path_parts['dirname'] == "socket" and $path_parts['basename'] == "stop") {
                $this->actionSocketStop($this->server_name);
                Event::saveEvent($this->server_name, "stop_socket_server", null);
                echo " socket server stop init...";
                $this->redirectHTTP($client, "/");
            }
            if ($path_parts['dirname'] == "socket" and $path_parts['basename'] == "start") {
                $this->actionSocketStart();
                echo " socket server start init...";
                Event::saveEvent($this->server_name, "start_socket_server", null);
                $this->redirectHTTP($client, "/");
            }
            if ($path_parts['dirname'] == "socket" and $path_parts['basename'] == "add_new_client") {
                $this->actionAddNewCLient();
                echo " socket server add new client init...";
                Event::saveEvent($this->server_name, "add_client_socket_server", null);
                $this->redirectHTTP($client, "/");
            }
            if ($path_parts['dirname'] == "socket" and $path_parts['basename'] == "saved_client") {
                $this->actionConfirmSaveClient();
                echo " socket server confirm saving new client ...";
                $this->redirectHTTP($client, "/");
            }
            if ($path_parts['dirname'] == "socket" and $path_parts['basename'] == "clear_data") {
                $this->actionClearAllData();
                echo " socket server clear all data ...";
                $this->redirectHTTP($client, "/");
            }

            return;
        }

        $file_type_found = false;
        foreach ($content_detector as $ck => $cv) {
            if ($path_parts['extension'] == $ck) {
                if (($ck == "html") and substr_count($this->http_url, "/html/") == 0) {
                    $this->http_url = "/html" . $this->http_url;
                }
                $file_test = APP . DS . "web" . $this->http_url;
                if (file_exists($file_test)) {
                    echo $file_test;
                    $file_type_found = true;
                    $this->{$cv}($client, $file_test, $path_parts['extension']);
                }
            }
        }
        if (!$file_type_found) {
            $this->response404($client);
        }

    }

    /**
     * @param $input
     * @return void
     */
    private function parseHeaders($input)
    {
        if ($input == "") {
            return;
        }
        $first_header = explode(PHP_EOL, $input);
        $first_str = explode(" ", $first_header[0]);
        $this->http_method = $first_str[0];
        $this->http_url = $first_str[1];
        $this->http_version = $first_str[2];
    }

    /**
     * @param $html
     * @return string
     */
    private function formatHTML($html)
    {
        $replaced = [
            '__APP_NAME__' => APP_NAME,
            '__DEVELOPER_ORIGIN_LINK__' => DEVELOPER_ORIGIN_LINK,
            '__DEVELOPER_NAME__' => DEVELOPER_NAME,
            '__GEN_TIME__' => $this->generatedTime(),
            '__SERVER_NAME__' => $this->server_name,
        ];
        foreach ($replaced as $rk => $rv) {
            $html = str_replace($rk, $rv, $html);
        }
        return $html;
    }

    /**
     * @param $prepared_html
     * @return array|string|string[]
     */
    private function actionIndex($prepared_html)
    {
        //есть ли только что созданный клиентский файл
        $has_client = Storage::hasNewClient($this->server_name);
        $has_client_data = '';
        if ($has_client) {
            $client_json = '{
   "client_token": "' . $has_client . '",
  "servers": [
    {
        "name": "' . $this->server_name . '",
      "address": "127.0.0.1",
      "port": "' . $this->socket_port . '",
    }]
    }';

            $has_client_data = '
<div class="alert alert-success mb-3 mt-3" role="alert">Action was succesfully! you just added a new socket client to your XPSS server!</div>
<p class="info">Save correctly this data and create/update configuration file "client.json" for your XPSS socket client (in directory app root):</p>
            <textarea cols="52" rows="12">' . $client_json . '</textarea>
            after you have saved socket client configuration press the button below to confirm your action
            <a class="btn btn-warning" href="/socket/saved_client">Yes, I saved it!</a>';
        }


        $con_table = "";
        $srv_connections = $this->getConnections();
        $sc = 0;
        if (is_array($srv_connections) AND count($srv_connections) > 2) {
            $con_table .= "<table class='content'>" .
                "<tr>
                        <td>direction</td>
                        <td>ip address</td>
                        <td>port</td>
                        <td>connection time</td>
                        <td>data hash</td>
                        <td>data length (bytes)</td>
                        </tr>";
            $srv_connections = $this->getConnections();
            $max_conn_view = 1000;

            foreach ($srv_connections as $srv_connection) {
                if ($srv_connection == "." or $srv_connection == "..") continue;
                $sc++;
                // Check the end
                if ($sc > $max_conn_view) break;
                $conn_data = explode("_", $srv_connection);
                if ($conn_data[0] == "inc") {
                    $con_dir = "incoming";
                } else {
                    $con_dir = "outcoming";
                }
                $dates = $conn_data[3] . " " . str_replace("-", ":", $conn_data[4]);
                $con_table .= "<tr>
                        <td>$con_dir</td>
                        <td>$conn_data[1]</td>
                        <td>$conn_data[2]</td>
                        <td>$dates</td>
                        <td>$conn_data[5]</td>
                        <td>$conn_data[6]</td>
                        </tr>";
            }
            $con_table .= "</table>";
        }else {
            $con_table .= "<span class='text-error'>Socket clients not found</span>";
        }

        $server_uptime = floor(time() - Storage::getServerUptime($this->server_name));

        $web_server_uptime = floor(microtime(true) - $this->start_time);

        $socket_clients = Storage::getServerClients($this->server_name);

        $cl_table = "";
        $cc = 0;
        if (is_array($socket_clients) AND count($socket_clients) > 2) {
            $cl_table .= "<table class='content w-100'>" .
                "<tr>
                        <td>created</td>
                        <td>client key</td>
                        </tr>";

            foreach ($socket_clients as $socket_client) {
                if ($socket_client == "." or $socket_client == ".."
                or $socket_client == "new") continue;
                $cc++;
                $client_datetime = date("Y-m-d H:i:s", filemtime(Storage::getClientDir($this->server_name) . DS . $socket_client));
                $cl_table .= "<tr>
                        <td>$client_datetime</td>
                        <td>$socket_client</td>
                        </tr>";
            }
            $cl_table .= "</table>";
        }
        else {
            $cl_table .= "<span class='text-error'>Socket clients not found</span>";
        }

        // events list
        $socket_events = Event::getServerEvents($this->server_name);

        $ev_table = "";
        $sv = 0;
        if (is_array($socket_events) AND count($socket_events) > 2) {
            $ev_table .= "<table class='content w-100'>" .
                "<tr>
                        <td>Event</td>
                        <td>datetime</td>
                        <td>data</td>
                        </tr>";

            foreach ($socket_events as $socket_event) {
                if ($socket_event == "." or $socket_event == "..") continue;
                $sv++;
                $client_datetime = date("Y-m-d H:i:s",
                    filemtime(Storage::getEventDir($this->server_name) . DS . $socket_event));
                $event_data = file_get_contents(Storage::getEventDir($this->server_name) . DS . $socket_event);
                $ev_table .= "<tr>
                        <td> $socket_event</td>
                        <td>$client_datetime</td>
                        <td>$event_data</td>
                        </tr>";
            }
            $ev_table .= "</table>";
        } else {
            $ev_table .= "<span class='text-error'>Socket events not found</span>";
        }

        if ($this->isServerActive($this->server_name)) {
            $server_buttons = '
Status: <span class="text-success font-weight-bold">Active</span>
<a class="btn btn-danger" href="/socket/stop">Stop socket server</a>';
        } else {
            $server_buttons = '
Status: <span class="text-danger font-weight-bold">Offline</span>
<a class="btn btn-success" href="/socket/start">Start socket server</a>';
        }

        $server_info = [];
        $server_info["Server Time"] = date("Y-m-d H:i:s");
        $server_info["Php Version"] = phpversion();
        $server_info["Server Operation System"] = PHP_OS_FAMILY;
        $server_info["XPSS App version"] = APP_VERSION;
        $server_info["Web Server Start Time"] = DateAndTime::day_separator($web_server_uptime);


        $server_data_table = "<table class='content w-100'>";

        foreach ($server_info as $k => $v) {
            $server_data_table .= "<tr>
                        <td>$k</td>
                        <td>$v</td>
                        </tr>";
        }
        $server_data_table .= "</table>";

        //socket server variables
        $ss_info = [];
        $ss_info["XPSS App version"] = APP_VERSION;
        $ss_info["Socket Server Start Time"] = DateAndTime::day_separator($server_uptime);
        $ss_info["Socket Server Storage Filesize"] = Storage::storageSize($this->server_name);

        $ss_data_table = "<table class='content w-100'>";

        foreach ($ss_info as $k => $v) {
            $ss_data_table .= "<tr>
                        <td>$k</td>
                        <td>$v</td>
                        </tr>";
        }
        $ss_data_table .= "</table>";

        $prepared_values = [
            '__CONTROL_SERVER_BUTTON__' => $server_buttons,
            '__HAS_NEW_CLIENT__' => $has_client_data,
            "__CONN_COUNT_VIEW__" => $sc,
            "__CONNECTIONS__" => $con_table,
            // "__EVENTS__" => $ev_table,

            "__SERVER_NAME__" => $this->server_name,
            "__SERVER_MAX_DAY_CONNECTIONS__" => $this->max_connection_lifetime,
            "__SERVER_MAX_DAY_EVENTS__" => $this->max_event_lifetime,
            "__SERVER_SOCKET_CLIENTS__" => $cl_table,
            "__SERVER_SOCKET_EVENTS__" => $ev_table,
            "__WS_INFO__" => $server_data_table,
            "__SS_INFO__" => $ss_data_table,
        ];

        foreach ($prepared_values as $pk => $pv) {
            $prepared_html = str_replace($pk, $pv, $prepared_html);
        }
        return $prepared_html;
    }

    public function createNewClient()
    {
        echo " starting create new client!";
        $client_key = Storage::generateClientKey();
        Storage::saveNewClient($this->server_name, $client_key, null);
        echo " new client key: " . $client_key;
        return $client_key;
    }

    private function actionAddNewCLient()
    {
        $this->createNewClient($this->server_name);
    }

    private function actionSocketStart()
    {
        return $this->startSocketServer($this->server_name);
    }

    private function actionSocketStop($server)
    {
        return Storage::commandServer($server, "stop");
    }

    private function startSocketServer($server)
    {
        return Storage::commandServer($server, "active");
    }

    private function actionConfirmSaveClient()
    {
        return Storage::clearNewClient($this->server_name);
    }

    private function actionClearAllData()
    {
        Storage::clearClients($this->server_name);
        Storage::clearConnections($this->server_name);
        Storage::clearEvents($this->server_name);
    }

    /**
     * @param $server
     * @return bool
     */
    public function isServerActive($server)
    {
        if (Storage::getServerCommand($server) == "active") {
            return true;
        }
        return false;
    }

    /**
     * @param $client
     * @param $page
     * @param $ext
     * @return void
     */
    public function htmlGET($client, $page, $ext = null)
    {
        // HTML-контент, возвращаемый сборкой
        if (!file_exists($page)) {
            $this->response404($client);
            return;
        }
        $prepared_html = file_get_contents($page);

        $prepared_html = $this->formatHTML($prepared_html);
        if ($this->http_url == "/html/index.php") {
            $prepared_html = $this->actionIndex($prepared_html);
        }

        // Собираем информацию заголовка
        $this->addHttpHeader("200");
        $this->addDefaultHeaders();
        $this->addHttpHeader("Date");
        $this->addHttpHeader("Server");
        $this->addHttpHeader("Content-Type", "text/html");
        $this->addHttpHeader("Content-Length", mb_strlen($prepared_html));
        $this->addHttpContent($prepared_html);
        socket_write($client, $this->headers, mb_strlen($this->headers));
    }

    /**
     * @param $client
     * @param $css_file
     * @param $ext
     * @return void
     */
    public function cssGET($client, $css_file, $ext = null)
    {
        if (!file_exists($css_file)) {
            $this->response404($client);
            return;
        }
        $content = file_get_contents($css_file);
        // Собираем информацию заголовка
        $this->addHttpHeader("200");
        $this->addDefaultHeaders();
        $this->addHttpHeader("Date");
        $this->addHttpHeader("Server");
        $this->addHttpHeader("Content-Type", "text/css");
        $this->addHttpHeader("Content-Length", mb_strlen($content));
        $this->addHttpHeader("Cache-Control", 4);
        $this->addHttpContent($content);
        socket_write($client, $this->headers, mb_strlen($this->headers));
    }

    /**
     * @param $client
     * @param $js_file
     * @param $ext
     * @return void
     */
    public function javascriptGET($client, $js_file, $ext = null)
    {
        if (!file_exists($js_file)) {
            $this->response404($client);
            return;
        }
        $content = file_get_contents($js_file);

        // Собираем информацию заголовка
        $this->addHttpHeader("200");
        $this->addDefaultHeaders();
        $this->addHttpHeader("Date");
        $this->addHttpHeader("Server");
        $this->addHttpHeader("Content-Type", "text/javascript");
        $this->addHttpHeader("Content-Length", mb_strlen($content));
        $this->addHttpHeader("Cache-Control", 36000);
        $this->addHttpContent($content);
        socket_write($client, $this->headers, mb_strlen($this->headers));
    }

    /**
     * @param $client
     * @param $image_file
     * @param $ext
     * @return void
     */
    public function htmlImageGET($client, $image_file, $ext = null)
    {
        if (!file_exists($image_file)) {
            $this->response404($client);
            return;
        }
        $handle = fopen($image_file, "rb");
        $contents = fread($handle, filesize($image_file));
        fclose($handle);

        // Собираем информацию заголовка
        $this->addHttpHeader("200");
        $this->addDefaultHeaders();
        $this->addHttpHeader("Date");
        $this->addHttpHeader("Server");
        $this->addHttpHeader("Content-Type", "image/$ext");
        $this->addHttpHeader("Content-Length", filesize($image_file));
        $this->addHttpHeader("Cache-Control", 36000);
        $this->addHttpHeader("Close");
        $this->addHttpContent($contents);
        socket_write($client, $this->headers, filesize($image_file));
    }

    /**
     * @param $client
     * @return void
     */
    public function response404($client)
    {
        // Собираем информацию заголовка
        $content = '';

        $this->addHttpHeader("404");
        $this->addDefaultHeaders();
        $this->addHttpHeader("Date");
        $this->addHttpHeader("Server");
        $this->addHttpHeader("Content-Type", "text/html");
        $this->addHttpHeader("Content-Length", mb_strlen($content));
        $this->addHttpContent($content);
        socket_write($client, $this->headers, mb_strlen($this->headers));
    }

    /**
     * @param $client
     * @param $redirect
     * @return void
     */
    public function redirectHTTP($client, $redirect)
    {
        // Собираем информацию заголовка
        $content = "";//" . $redirect ."
        $this->addHttpHeader("302");
        $this->addDefaultHeaders();
        $this->addHttpHeader("Date");
        $this->addHttpHeader("Server");
        $this->addHttpHeader("Location", $redirect);
        $this->addHttpHeader("Close");
        $this->addHttpContent($content);
        socket_write($client, $this->headers, mb_strlen($this->headers));
    }

    private function addDefaultHeaders()
    {
        //$this->headers .= "Connection: keep-alive" . HTTP_HEADER_END;
    }

    /**
     * @param $header
     * @param $value
     * @return void
     */
    private function addHttpHeader($header, $value = "")
    {
        if ($header == "200") {
            $this->headers .= "HTTP/1.1 200 OK" . HTTP_HEADER_END;
        }
        if ($header == "302") {
            $this->headers .= "HTTP/1.1 302 File found" . HTTP_HEADER_END;
        }
        if ($header == "Location") {
            $this->headers .= "Location: $value" . HTTP_HEADER_END;
        }
        if ($header == "404") {
            $this->headers .= "HTTP/1.1 404 File Not Found" . HTTP_HEADER_END;
        }
        if ($header == "Close") {
            $this->headers .= "Connection: Close" . HTTP_HEADER_END;
        }
        if ($header == "Date") {
            $this->headers .= gmdate('D, d M Y H:i:s T') . HTTP_HEADER_END;
        }
        if ($header == "Server") {
            $this->headers .= "Server: " . APP_NAME . HTTP_HEADER_END;
        }
        if ($header == "Content-Type") {
            $this->headers .= "Content-Type: " . $value . ";charset=utf-8" . HTTP_HEADER_END;
        }
        if ($header == "Content-Length") {
            $this->headers .= "Content-Length: " . $value . HTTP_HEADER_END;
        }
        if ($header == "Cache-Control") {
            $this->headers .= "Cache-Control: max-age=" . $value . HTTP_HEADER_END;
        }
    }

    private function addHttpContent($content)
    {
        $this->headers .=  HTTP_HEADER_END . $content;
    }

    public function getErrors()
    {
        return join(" ", $this->errors);
    }

    private function generatedTime()
    {
        return number_format(microtime(true) - $this->start_generate_time, 3);
    }

    private function getConnections()
    {
        $connections = Storage::getServerConnections($this->server_name);
        return $connections;
    }


}