<?php

require_once '../vendor/autoload.php';

use GraphQL\GraphQL;
use GraphQL\Type\Schema;
use GraphQL\Error\DebugFlag;
use GraphQL\Type\Introspection;
use App\Controller\GraphQLController;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

try {
    $controller = new GraphQLController();

    $schema = new Schema([
        'query' => $controller->getQueryType(),
        'mutation' => $controller->getMutationType(),
    ]);

    // Handle GET or POST request
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['query'])) {
        $query = $_GET['query'];
        $variableValues = isset($_GET['variables']) ? json_decode($_GET['variables'], true) : null;
    } else {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        $query = $input['query'] ?? null;
        $variableValues = $input['variables'] ?? null;
    }

    // Handle introspection query
    if ($query === Introspection::getIntrospectionQuery()) {
        $result = GraphQL::executeQuery($schema, $query);
        $output = $result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE);
        echo json_encode($output);
        exit;
    }

    // Execute the query
    $rootValue = [];
    $result = GraphQL::executeQuery($schema, $query, $rootValue, null, $variableValues);

    // Include debug information in the output
    $output = $result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE);

} catch (Throwable $e) {
    error_log('GraphQL Error: ' . $e->getMessage());
    error_log('Stack Trace: ' . $e->getTraceAsString());
    $output = [
        'errors' => [
            [
                'message' => 'An unexpected error occurred: ' . $e->getMessage(),
                'locations' => [],
                'path' => [],
                'trace' => $e->getTraceAsString()
            ]
        ]
    ];
}

echo json_encode($output);
