#!/usr/bin/env php
<?php
require_once('config.php');
error_reporting(E_ALL);
set_time_limit(0);
ob_implicit_flush();

$socket_list = [
    'address' => '0.0.0.0', 'port' => '22444'
];

if (php_sapi_name() === 'cli-server') {
    $socket_list['address'] = $argv[1];
    $socket_list['port'] = $argv[2];
}

//$cypher = new Cypher("1234567890");
//$gen_params = $cypher->init();
$server = new Server("server_local.json");
//$socket = new Server($socket_list['address'], $socket_list['port'], $cypher, $client_pass, $code_key, $server_ip_address);
echo "start expss socket server";

$server->launchServer();

echo "stopped expss socket server";
exit();