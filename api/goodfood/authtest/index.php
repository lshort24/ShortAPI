<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../../vendor/autoload.php';
require_once '../../config/secrets.php';
require_once '../../auth/GoogleAuth.php';
require_once '../../auth/Auth.php';

// required headers
$secrets = getSecrets();
$origin = ($_SERVER['REMOTE_ADDR'] === $secrets['my_ip']) ? "http://localhost:3000" : 'https://shortsrecipes.com';
header("Access-Control-Allow-Origin: {$origin}");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Authorization, Content-Type");
header("Content-Type: application/json; charset=UTF-8");

$token = '';
if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
    if (preg_match('/^Bearer (.*)$/', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
        $token = $matches[1];
    };
}

$googleAuth = new GoogleAuth();
$auth = $googleAuth->authenticate($token);

http_response_code(200);
echo json_encode([
    'authenticated' => $auth->isAuthorized,
    'reason' => $auth->reason
]);
