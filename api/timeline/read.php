<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/Database.php';
require_once '../handlers/TimelineApiHandler.php';
require_once '../../api/config/secrets.php';

// required headers
$secrets = getSecrets();
$origin = ($_SERVER['REMOTE_ADDR'] === $secrets['my_ip']) ? "http://localhost:3000" : 'https://shortsrecipes.com';
header("Access-Control-Allow-Origin: {$origin}");
header("Access-Control-Allow-Headers: Authorization");
header("Content-Type: application/json; charset=UTF-8");

if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $authorization = $_SERVER['HTTP_AUTHORIZATION'];
}

// initialize our handler
$handler = new TimelineApiHandler();

// prepare and run query
$id = isset($_GET['id']) ? $_GET['id'] : null;
list($query, $params) = $handler->get_read_query($id);
$results = $handler->handle_request($query, $params);

http_response_code($results["status"]);
echo json_encode($results["data"]);