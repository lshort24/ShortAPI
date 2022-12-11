<?php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use ShortAPI\auth\Authorization;
use ShortAPI\config\Database;
use ShortAPI\JWT;

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
        'failReason' => 'Could not verify your Google identity'
    ]);
    exit;
}

// Look the user up in our database
$query = "
        SELECT *
        FROM users
        WHERE 
            user_id = :userId AND 
            user_type = 'google'
    ";

$params = [
    ':userId' => [
        "value" => $userId,
        "type" => PDO::PARAM_STR
    ]
];

$database = new Database();
$conn = $database->getConnection('goodfood', Authorization::GUEST_ROLE);
$stmt = $conn->prepare($query);

foreach ($params as $name => $param) {
    $stmt->bindParam($name, $param["value"], $param["type"]);
}

$stmt->execute();
if ($stmt->rowCount() === 0) {
    $log->debug("Could not find user $userId in the database.");
    http_response_code(200);
    echo json_encode([
        'authenticated' => false,
        'profileName' => '',
        'failReason' => 'Only family members can login to the website.'
    ]);
    exit;
}

// Create a new session
session_unset();
session_destroy();
session_start();

// Generate an access token
try {
    $accessToken = JWT::instance()->encode(['user_id' => $userId]);
}
catch (Throwable $e) {
    $reason = 'Could not create access token.';
    $log->debug($reason, ['ex' => $e]);
    http_response_code(200);
    echo json_encode([
        'authenticated' => false,
        'profileName' => '',
        'failReason' => $reason
    ]);
    exit;
}


$expiresTimestamp = time() + 15 * 60; // 15 minutes
$expiresAt = new DateTime();
$expiresAt->setTimestamp($expiresTimestamp);
$expiresAtString = $expiresAt->format('Y-m-d H:i:s');

try {
    $success = setcookie(
        'accessToken',
        $accessToken,
        time() + 15 * 60,
        '/',
        'shortsrecipes.com'
    );
}
catch (Throwable $e) {
    $log->debug('Exception', ['ex' => $e->getMessage()]);
    http_response_code(200);
    echo json_encode([
        'authenticated' => false,
        'profileName' => '',
        'failReason' => 'Server error'
    ]);
    exit;
}

if (!$success) {
    $reason = 'Could not create cookie for access token.';
    $log->debug($reason);
    http_response_code(200);
    echo json_encode([
        'authenticated' => false,
        'profileName' => '',
        'failReason' => $reason
    ]);
    exit;
}

http_response_code(200);
echo json_encode([
    'authenticated' => true,
    'profileName' => $profileName,
    'failReason' => ''
]);