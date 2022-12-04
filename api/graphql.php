<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use GraphQL\GraphQL;
use GraphQL\Type\Schema;

use ShortAPI\GraphQL\Type\MutationType;
use ShortAPI\GraphQL\Type\QueryType;
use ShortAPI\JWT;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../api/config/secrets.php';

$log = new Logger('api');
$log->pushHandler(new StreamHandler(__DIR__ . '/../app.log', Logger::DEBUG));

// required headers
$secrets = getSecrets();
$origin = ($_SERVER['REMOTE_ADDR'] === $secrets['my_ip']) ? "http://localhost:3000" : 'https://shortsrecipes.com';
header("Access-Control-Allow-Origin: $origin");
header("Access-Control-Allow-Headers: Accept, Origin, Content-Type, Authorization");
header('Content-Type: application/json');

$token = '';
if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
    if (preg_match('/^Bearer (.*)$/', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
        $token = $matches[1];

        $payload = JWT::instance()->decode($token);
        if (empty($payload)) {
            $output = [
                'errors' => [
                    [
                        'message' => "Permission denied",
                    ]
                ]
            ];
            echo json_encode($output);
            exit;
        }
        $log->debug("Processing request for user {$payload['user_id']}");
    }
}

if (empty($token)) {
    // TODO: return an error if this is a mutation
    $log->debug('No token was sent');
}

$queryType = new QueryType();
$mutationType = new MutationType();
try {
    $schema = new Schema([
        'query' => $queryType,
        'mutation' => $mutationType,
    ]);
}
catch (Throwable $ex) {
    $log->debug('Schema error', ['ex' => $ex]);
    $output = [
        'errors' => [
            [
                'message' => $ex->getMessage()
            ]
        ]
    ];
    echo json_encode($output);
    exit;
}


$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
$query = $input['query'];
$variableValues = $input['variables'] ?? null;

try {
    $rootValue = [];
    $output = GraphQL::executeQuery($schema, $query, $rootValue, null, $variableValues);
} catch (Throwable $e) {
    $log->error('There was an error with the query', ['error' => $e->getMessage()]);
    $output = [
        'errors' => [
            [
                'message' => $e->getMessage()
            ]
        ]
    ];
}

echo json_encode($output);