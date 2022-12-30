<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

use GraphQL\Error\DebugFlag;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use GraphQL\GraphQL;
use GraphQL\Type\Schema;

use ShortAPI\auth\Authorization;
use ShortAPI\GraphQL\Type\MutationType;
use ShortAPI\GraphQL\Type\QueryType;

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

if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
    if (preg_match('/^Bearer (.*)$/', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
        $token = $matches[1];
        Authorization::instance()->setToken($token);
    }
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
    $log->debug('GraphQL: Schema error', ['ex' => $ex]);
    $output = [
        'errors' => [
            [
                'schema error' => $ex->getMessage()
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

$rootValue = [];
$debugFlag = DebugFlag::NONE;
//$debugFlag = DebugFlag::INCLUDE_DEBUG_MESSAGE;
$output = GraphQL::executeQuery($schema, $query, $rootValue, null, $variableValues)->toArray($debugFlag);

// Re-write GraphQL errors
if (isset($output['errors'])) {
    $output['errors'] = array_map(function ($error) use ($log) {
        $message = $error['message'];
        if ($error['extensions']['category'] === 'graphql') {
            $message = 'There was an error with your request.';
            $log->error('GraphQL: Rewriting GraphQL error message.', ['graphqlError' => $error['message']]);
        }
        return [
            'extensions' => $error['extensions'],
            'locations' => $error['location'],
            'message' => $message,
        ];
    }, $output['errors']);
}

echo json_encode($output);