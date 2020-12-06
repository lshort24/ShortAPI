<?php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$log = new Logger('api');
$log->pushHandler(new StreamHandler(__DIR__ . '/../app.log', Logger::DEBUG));