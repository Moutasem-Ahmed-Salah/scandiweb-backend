<?php
require '../vendor/autoload.php'; // Adjust the path as necessary

use Dotenv\Dotenv;

// Load .env file from the root directory
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Retrieve database connection parameters from environment variables
$hostname = $_ENV['DB_HOST'];
$port = $_ENV['DB_PORT'];
$dbname = $_ENV['DB_NAME'];
$username = $_ENV['DB_USER'];
$password = $_ENV['DB_PASS'];

// Create a new PDO instance
try {
    $dbh = new PDO("mysql:host=$hostname;port=$port;dbname=$dbname", $username, $password);
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Database connection successful!";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
