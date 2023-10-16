<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../vendor/autoload.php';
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

function exitWithStatus(string $message, Logger $log) : void {
    http_response_code(200);
    $log->debug($message);
    echo json_encode([
        'status' => $message
    ]);
    exit;
}

function exitWithError(string $message, Logger $log) : void {
    http_response_code(200);
    $log->error($message);
    echo json_encode([
        'status' => 'error',
        'message' => $message
    ]);
    exit;
}

function exitWithRevoked(string $message, Logger $log) : void {
    http_response_code(200);
    $log->debug($message);
    echo json_encode([
        'status' => 'revoked',
        'message' => $message
    ]);
    exit;
}

$googleId = $_POST['googleId'];
$name = $_POST['name'];

// Look up the user in the database by google id
try {
    $user = UserService::instance()->find_user_by_google_id($googleId);
}
catch (DatabaseException $ex) {
    $log->error("Database exception", ['ex' => $ex->getMessage(), 'googleId' => $googleId]);
    exitWithError("Database exception", $log);
}

if (empty($user)) {
    exitWithError("Sorry, your Google user does not have a SugarCurves account.", $log);
}


exitWithStatus("found user", $log);