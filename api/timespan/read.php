<?php
require_once '../config/Database.php';
require_once '../handlers/TimeSpanApiHandler.php';
require_once '../../api/config/secrets.php';
require __DIR__ . '/../../vendor/autoload.php';

$log = new Monolog\Logger('api');
$log->pushHandler(new Monolog\Handler\StreamHandler(__DIR__ . '/../../app.log', Monolog\Logger::DEBUG));

// required headers
$secrets = getSecrets();
$origin = ($_SERVER['REMOTE_ADDR'] === $secrets['my_ip']) ? "http://localhost:3000" : 'https://shortsrecipes.com';
header("Access-Control-Allow-Origin: {$origin}");
header("Content-Type: application/json; charset=UTF-8");


// initialize our handler
$handler = new TimeSpanApiHandler();

// prepare and run query
$id = isset($_GET['id']) ? $_GET['id'] : null;
list($query, $params) = $handler->get_read_query($id);
$results = $handler->handle_request($query, $params);

http_response_code($results["status"]);
echo json_encode($results["data"]);