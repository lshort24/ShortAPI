<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

require __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../config/secrets.php';

use Firebase\JWT\JWT;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use ShortAPI\services\DatabaseException;
use ShortAPI\SugarCurves\services\UserService;

header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Headers: Accept, Origin, Content-Type");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$log = new Logger('sugarCurvesAPI');
$log->pushHandler(new StreamHandler(__DIR__ . '/../../../sugar_curves_api.log', Logger::DEBUG));

function exitWithToken(string $accessToken, Logger $log): void {
    http_response_code(200);
    $log->debug("Generated token");
    echo json_encode([
        'status' => 'success',
        'message' => null,
        'accessToken' => $accessToken
    ]);
    exit;
}

function exitWithError(string $message, Logger $log) : void {
    http_response_code(200);
    $log->error($message);
    echo json_encode([
        'status' => 'error',
        'message' => $message,
        'accessToken' => null
    ]);
    exit;
}

$googleId = $_POST['googleId'];
$name = $_POST['name'];

// Look up the user in the database by google id
$user = [];
try {
    $user = UserService::instance()->find_user_by_google_id($googleId);
}
catch (DatabaseException $ex) {
    $log->error("Database exception", ['ex' => $ex->getMessage(), 'googleId' => $googleId]);
    exitWithError("There was a database error while looking up the SugarCurves user.", $log);
}

if (empty($user['googleId'])) {
    $message = "Sorry, your Google user does not have a SugarCurves account.";
    $log->error($message, ['googleId' => $googleId]);
    exitWithError($message, $log);
}

$jwt = new JWT();
$payload = [
    'googleId' => $user['googleId'],
    'iat' => strtotime('1 hour')
];
$secrets = getSecrets();
$token = $jwt->encode($payload, $secrets['authorizationSecret']);

// Add the token to the database
try {
    UserService::instance()->updateAccessToken($user['id'], $token);
}
catch (DatabaseException $ex) {
    $message = "Could not update user's access token.";
    $log->error($message, ['userId' => $user['id'], 'googleId' => $googleId]);
    exitWithError($message, $log);
}
exitWithToken($token, $log);