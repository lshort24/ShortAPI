<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Headers: Accept, Origin, Content-Type");
header('Content-Type: application/json');

require __DIR__ . '/../../../vendor/autoload.php';
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use ShortAPI\services\DatabaseException;
use ShortAPI\SugarCurves\services\UserService;

$log = new Logger('sugarCurvesAPI');
$log->pushHandler(new StreamHandler(__DIR__ . '/../../../sugar_curves_api.log', Logger::DEBUG));

function exitWithStatus(bool $status, string $reason, Logger $log) {
    http_response_code(200);
    if (!$status) {
        $log->error("Could not authenticate.", ['reason' => $reason]);
    }
    echo json_encode([
        'status' => $status,
        'reason' => $reason
    ]);
    exit;
}

$googleId = $_POST['googleId'];

// Look up the user in the database by google id
$user = [];
try {
    $user = UserService::instance()->find_user_by_google_id($googleId);
}
catch (DatabaseException $ex) {
    $log->error("Database exception", ['ex' => $ex->getMessage(), 'googleId' => $googleId]);
    exitWithStatus(false, "There was a database error while looking up the SugarCurves user.", $log);
}

if (empty($user['googleId'])) {
    $message = "Sorry, your Google user does not have a SugarCurves account.";
    $log->error($message, ['googleId' => $googleId]);
    exitWithStatus(false, $message, $log);
}

// Remove the access token for the user
try {
    UserService::instance()->removeAccessToken($user['id']);
}
catch (DatabaseException $ex) {
    $message = "Could not remove user's access token.";
    $log->error($message, ['userId' => $user['id'], 'googleId' => $googleId]);
    exitWithStatus(false, $message, $log);
}