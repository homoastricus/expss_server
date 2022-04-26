#!/usr/bin/env php
<?php
require_once ('config.php');
error_reporting(E_ALL);
set_time_limit(0);
ob_implicit_flush();

$web_server = new Web("server_local.json");
if (!$web_server->createSocket()) {
    die($web_server->getErrors());
}

echo "starting expss web server...";
$web_server->worker();
echo "stopped expss web server";

die("server stopped!");