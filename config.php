<?php
//app constants
define('APP_NAME', "EXPSS Server");
define('APP_VERSION', "0.1");
define('DEVELOPER_NAME', 'Artur Mataryan');
define('DEVELOPER_ORIGIN_LINK', 'https://github.com/homoastricus/expss');
define('APP', dirname(__FILE__));
define('DEBUG_MODE', true);
define('SERVER_LOG', 'server.log');
define('DS', DIRECTORY_SEPARATOR);

//socket server constants
define('EXPSS_CODE', "MY_EXPSS_CODE");
define('HTTP_HEADER_END', "\r\n");
define('SOCKET_BUFFER_SIZE', 65500);
define('SOCKET_BUFFER_PARTIAL', 65000);

if(!in_array("sockets",get_loaded_extensions())){
    die("Oops... EXPSS is not available without php socket extension!");
}

function my_autoload ($class) {
    include(APP . DS . "Lib" . DS . "$class.php");
}
spl_autoload_register("my_autoload");