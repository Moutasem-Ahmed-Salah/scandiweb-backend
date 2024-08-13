<?php

require_once '../vendor/autoload.php';

use GraphQL\GraphQL;
use GraphQL\Type\Schema;
use GraphQL\Error\DebugFlag;
use GraphQL\Error\Error;
use App\Controller\GraphQLController; // Adjusted namespace to 'App'

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

try {
    $controller = new GraphQLController();

    $schema = new Schema([
        'query' => $controller->getQueryType(),
        'mutation' => $controller->getMutationType(),
    ]);

    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    $query = $input['query'];
    $variableValues = isset($input['variables']) ? $input['variables'] : null;

    $rootValue = [];
    $result = GraphQL::executeQuery($schema, $query, $rootValue, null, $variableValues);
    $output = $result->toArray(); // Changed from toSerializableArray
    

} catch (Throwable $e) {
    $output = [
        'errors' => [
            'message' => $e->getMessage()
        ]
    ];
}

echo json_encode($output);
