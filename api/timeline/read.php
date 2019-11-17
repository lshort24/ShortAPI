<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/Database.php';
require_once '../handlers/TimelineApiHandler.php';

// required headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// initialize our handler
$handler = new TimelineApiHandler();

// prepare and run query
$id = isset($_GET['id']) ? $_GET['id'] : null;
list($query, $params) = $handler->get_read_query($id);
$results = $handler->handle_request($query, $params);

http_response_code($results["status"]);
echo json_encode($results["data"]);