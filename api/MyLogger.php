<?php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class MyLogger
{
    static $log = null;

    static function debug(string $message, array $context = []) {
        if (!static::$log) {
            static::$log = new Logger('api');
            static::$log->pushHandler(new StreamHandler(__DIR__ . '/../app.log', Logger::DEBUG));
        }
        static::$log->debug($message, $context);
    }
}