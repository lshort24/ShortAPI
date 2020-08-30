<?php
require_once '../config/Database.php';
require_once '../handlers/TimeSpanApiHandler.php';
require_once '../../api/config/secrets.php';
require __DIR__ . '/../../vendor/autoload.php';
session_start();

$log = new Monolog\Logger('api');
$log->pushHandler(new Monolog\Handler\StreamHandler(__DIR__ . '/../../app.log', Monolog\Logger::DEBUG));

// required headers
$secrets = getSecrets();
$origin = ($_SERVER['REMOTE_ADDR'] === $secrets['my_ip']) ? "http://localhost:3000" : 'https://shortsrecipes.com';
header("Access-Control-Allow-Origin: {$origin}");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$log->debug("Authentication status: " . checkAuthentication());

// initialize our handler
$handler = new TimeSpanApiHandler();

// prepare and run query
$id = isset($_GET['id']) ? $_GET['id'] : null;
list($query, $params) = $handler->get_read_query($id);
$results = $handler->handle_request($query, $params);

http_response_code($results["status"]);
echo json_encode($results["data"]);


function checkAuthentication() {
    if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
        return 'Authorization header was not set.';
    }

    if (!isset($_SESSION['idToken'])) {
        return 'ID token was not set in the session.';
    }

    preg_match('/^Bearer (\S+)$/', $_SERVER['HTTP_AUTHORIZATION'], $matches);
    if (!$matches) {
        return 'Could not parse ID token from authorization header.';
    }

    if ($matches[1] !== $_SESSION['idToken']) {
        return 'ID Token in Authorization header did not match the session.';
    }

    return 'ok';
}