<?php
require __DIR__ . '/../vendor/autoload.php';
require_once './config/Database.php';
require_once './config/secrets.php';
session_start();

$log = new Monolog\Logger('api');
$log->pushHandler(new Monolog\Handler\StreamHandler(__DIR__ . '/../app.log', Monolog\Logger::DEBUG));

// required headers
$secrets = getSecrets();
$origin = ($_SERVER['REMOTE_ADDR'] === $secrets['my_ip']) ? "http://localhost:3000" : 'https://shortsrecipes.com';
header("Access-Control-Allow-Origin: {$origin}");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// get posted data and validate the ID token
$userId = null;
$failReason = null;
$data = json_decode(file_get_contents("php://input"));
if (!empty($data->idToken)) {
    $client = new Google_Client(['client_id' => $secrets['googleClientId']]);
    $payload = $client->verifyIdToken($data->idToken);
    if ($payload) {
        $userId = $payload['sub'];
    }
    else {
        $failReason = "Invalid ID Token";
    }
}
else {
    $failReason = "No ID token was specified.";
}

// Look the user up in our database
$authenticated = false;
if ($userId) {
    $query = <<< MySQL
            SELECT *
            FROM users
            WHERE 
                user_id = :userId AND 
                user_type = 'google'
MySQL;

    $params = [
        ':userId' => [
            "value" => $userId,
            "type" => PDO::PARAM_STR
        ]
    ];

    try {
        $database = new Database();
        $conn = $database->getConnection();
        $stmt = $conn->prepare($query);

        foreach ($params as $name => $param) {
            $stmt->bindParam($name, $param["value"], $param["type"]);
        }

        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $authenticated = true;
        }
        else {
            $failReason = "Could not find user in the database.";
        }
    }
    catch (Exception $ex) {
        $log->debug($ex->getMessage());
    }
}

$_SESSION['idToken'] = $authenticated ? $data->idToken : '';

http_response_code(200);
echo json_encode([
    "authenticated" => $authenticated,
    "failReason" => $failReason
]);