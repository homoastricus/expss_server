<?php

class App
{
    //your application logic which interacts with sockets
    public static function messageIncomingHandler($ip, $port, $message){
        //echo EXPSS has new incoming message, from address " . $ip . ", port is " . $port . " and message content was: " . $message;
        // your code
    }

    //your application logic with sending message to sockets
    //for example, you can send reversed incoming string
    public static function messageSendingHandler($message){
        return strrev($message);
    }

}