<?php
session_start();
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use ShortAPI\config\Database;

require __DIR__ . '/../vendor/autoload.php';
//require_once './config/Database.php';
require_once './config/secrets.php';

//date_default_timezone_set('America/Boise');
$log = new Logger('api');
$log->pushHandler(new StreamHandler(__DIR__ . '/../app.log', Logger::DEBUG));

// required headers
$secrets = getSecrets();
$origin = ($_SERVER['REMOTE_ADDR'] === $secrets['my_ip']) ? "http://localhost:3000" : 'https://shortsrecipes.com';
header("Access-Control-Allow-Origin: {$origin}");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

function authenticate(string $googleClientId) : array {
    // get posted data and validate the ID token
    $data = json_decode(file_get_contents("php://input"));

    if (empty($data->idToken)) {
        return [false, 'No ID token was specified.'];
    }

    $client = new Google_Client(['client_id' => $googleClientId]);
    $payload = $client->verifyIdToken($data->idToken);
    if (empty($payload)) {
        return [false, 'Invalid ID Token.'];
    }

    $userId = $payload['sub'];

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
    $conn = $database->getConnection('timeline');
    $stmt = $conn->prepare($query);

    foreach ($params as $name => $param) {
        $stmt->bindParam($name, $param["value"], $param["type"]);
    }

    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        return [false, 'Could not find user in the database.'];
    }
    return [true, ''];
}

try {
    [$authenticated, $failReason] = authenticate($secrets['googleClientId']);
    http_response_code(200);
    echo json_encode([
        'authenticated' => $authenticated,
        'failReason' => $failReason
    ]);
}
catch (Exception $ex) {
    $log->debug($ex->getMessage());
    http_response_code(500);
    echo json_encode([
        'authenticated' => false,
        'failReason' => $ex->getMessage()
    ]);
}