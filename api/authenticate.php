<?php
require __DIR__ . '/../vendor/autoload.php';
require_once '../api/config/secrets.php';

$log = new Monolog\Logger('api');
$log->pushHandler(new Monolog\Handler\StreamHandler(__DIR__ . '/../app.log', Monolog\Logger::DEBUG));

// required headers
$secrets = getSecrets();
$origin = ($_SERVER['REMOTE_ADDR'] === $secrets['my_ip']) ? "http://localhost:3000" : 'https://shortsrecipes.com';
header("Access-Control-Allow-Origin: {$origin}");
header("Access-Control-Allow-Methods: POST");
//header("Access-Control-Max-Age: 3600");
//header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
//header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

$authenticated = false;

// get posted data
$data = json_decode(file_get_contents("php://input"));
if (!empty($data->idToken)) {
    $client = new Google_Client(['client_id' => $secrets['googleClientId']]);
    $payload = $client->verifyIdToken($data->idToken);
    if ($payload) {
        $userId = $payload['sub'];
        $log->debug("ID token has been validated.");
        $authenticated = true;
    }
    else {
        $log->debug("Invalid ID Token");
    }
}
else {
    $log->debug("No ID token was specified.");
}

$httpStatus = $authenticated ? 200 : 403;
http_response_code($httpStatus);