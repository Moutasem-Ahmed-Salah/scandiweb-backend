<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

$hostname = '127.0.0.1';
$username = 'root';
$password = '';
$port = 3306;

try {
    $dbh = new PDO("mysql:host=$hostname;port=$port;dbname=eCommerce", $username, $password);
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Get the POST data
        $input = json_decode(file_get_contents('php://input'), true);
        $name = $input['Name'];

        // Prepare and execute the insert statement
        $stmt = $dbh->prepare("INSERT INTO DummyTable (Name) VALUES (:name)");
        $stmt->bindParam(':name', $name);

        if ($stmt->execute()) {
            echo json_encode(['message' => 'Name inserted successfully']);
        } else {
            echo json_encode(['message' => 'Failed to insert name']);
        }
    } else {
        // Fetch data from DummyTable for GET requests
        $sql = 'SELECT Name FROM DummyTable';
        $stmt = $dbh->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($results);
    }
} catch(PDOException $e) {
    echo json_encode(['message' => 'Connection failed: ' . $e->getMessage()]);
}
?>
