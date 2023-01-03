<?php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use ShortAPI\JWT;
use ShortAPI\services\DatabaseException;
use ShortAPI\services\UserService;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config/secrets.php';

$log = new Logger('api');
$log->pushHandler(new StreamHandler(__DIR__ . '/../app.log', Logger::DEBUG));

// required headers
$secrets = getSecrets();
$origin = ($_SERVER['REMOTE_ADDR'] === $secrets['my_ip']) ? "http://localhost:3000" : 'https://shortsrecipes.com';
header("Access-Control-Allow-Origin: $origin");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// get posted data and validate the JWT
$data = json_decode(file_get_contents("php://input"));

$clientId = $secrets['goodFoodGoogleClientId'];
$client = new Google_Client(['client_id' => $clientId]);
$payload = $client->verifyIdToken($data->credential);
if (isset($payload['sub'])) {
    $userId = $payload['sub'];
    $profileName = $payload['name'];
} else {
    $log->error("Could not verify the JWT.");
    http_response_code(200);
    echo json_encode([
        'authenticated' => false,
        'profileName' => '',
        'failReason' => 'Could not verify your Google identity',
        'role' => 'guest',
    ]);
    exit;
}

// Look the user up in our database
try {
    $user = UserService::instance()->getUserByUserId($userId, 'google', true);
}
catch (DatabaseException $ex) {
    $log->error("Could not find user $userId in the database.", ['ex' => $ex->getMessage()]);
    http_response_code(200);
    echo json_encode([
        'authenticated' => false,
        'profileName' => '',
        'failReason' => 'Only family members can login to the website.',
        'role' => 'guest',
    ]);
    exit;
}

// Create a new session
session_unset();
session_destroy();
session_start();

// Generate an access token
try {
    $expiresAt = new DateTime();
    $expiresAt->setTimezone(new DateTimeZone('America/Denver'));
    $expiresAt->modify('+15 minutes');
    $accessTokenPayload = [
        'userId' => $userId,
        'profileName' => $profileName,
        'role' => $user['role'],
        'expiresAt' => $expiresAt->getTimestamp(),
        'expiresAtDate' => $expiresAt->format('D, M d Y g:i A T')
    ];
    $accessToken = JWT::instance()->encode($accessTokenPayload);
}
catch (Throwable $e) {
    $reason = 'Could not create access token.';
    $log->debug($reason, ['ex' => $e]);
    http_response_code(200);
    echo json_encode([
        'authenticated' => false,
        'profileName' => '',
        'failReason' => $reason,
        'role' => 'guest',
    ]);
    exit;
}

http_response_code(200);
echo json_encode([
    'authenticated' => true,
    'profileName' => $profileName,
    'failReason' => '',
    'role' => $user['role'],
    'accessToken' => $accessToken
]);