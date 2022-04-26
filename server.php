#!/usr/bin/env php
<?php
require_once('config.php');
error_reporting(E_ALL);
set_time_limit(0);
ob_implicit_flush();

if (php_sapi_name() === 'cli-server') {
    if ($argv[1] == "init") {
        $server_name = Storage::createServer("server.json");
        echo "expss $server_name socket server successfully created!";
    }
}

$server = new Server("server.json");
echo "start expss socket server";

$server->launchServer();

$web_server = new Web("server.json");
if (!$web_server->createSocket()) {
    die($web_server->getErrors());
}
$web_server->worker();

die("server stopped!");