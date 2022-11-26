<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use GraphQL\GraphQL;
use GraphQL\Type\Schema;

use ShortAPI\GraphQL\Type\QueryType;


require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../api/config/secrets.php';

$log = new Logger('graphql');
$log->pushHandler(new StreamHandler(__DIR__ . '/../graphql.log', Logger::DEBUG));

// required headers
$secrets = getSecrets();
$origin = ($_SERVER['REMOTE_ADDR'] === $secrets['my_ip']) ? "http://localhost:3000" : 'https://shortsrecipes.com';
header("Access-Control-Allow-Origin: $origin");
header("Access-Control-Allow-Headers: Accept, Origin, Content-Type");
header('Content-Type: application/json');

/*
$queryType = new ObjectType([
    'name' => 'Query',
    'fields' => [
        'echo' => [
            'type' => Type::string(),
            'args' => [
                'message' => Type::nonNull(Type::string()),
            ],
            'resolve' => function ($rootValue, $args) {
                return $rootValue['prefix'] . $args['message'];
            }
        ],
    ],
]);
*/

$queryType = new QueryType();
$schema = new Schema([
    'query' => $queryType
]);

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