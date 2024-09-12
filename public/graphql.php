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
    http_response_code(204);
    exit;
}

try {
    $controller = new GraphQLController();
    $schema = new Schema([
        'query' => $controller->getQueryType(),
        'mutation' => $controller->getMutationType(),
    ]);

    // For testing purposes, hard-code a sample query if none is provided
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['query'])) {
        $query = $_GET['query'];
        $variableValues = isset($_GET['variables']) ? json_decode($_GET['variables'], true) : null;
    } else {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        $query = $input['query'] ?? '{ __schema { queryType { name } } }'; // Sample query here
        $variableValues = $input['variables'] ?? null;
    }

    // Log the query and variables for debugging
    error_log('GraphQL Query: ' . $query);
    error_log('GraphQL Variables: ' . json_encode($variableValues));

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
