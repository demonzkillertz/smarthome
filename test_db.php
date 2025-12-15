<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain');

echo "Starting DB Test...\n";

$servername = "193.203.185.164";
$username = "u290660616_pustak";
$password = "Pustak@237";
$dbname = "u290660616_pustak";

echo "Connecting to $servername with user $username...\n";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo "Connection failed: " . $conn->connect_error . "\n";
} else {
    echo "Connected successfully!\n";
    
    $result = $conn->query("SHOW TABLES");
    if ($result) {
        echo "Tables in database:\n";
        while($row = $result->fetch_array()) {
            echo "- " . $row[0] . "\n";
        }
    } else {
        echo "Error listing tables: " . $conn->error . "\n";
    }
    
    $conn->close();
}
echo "Test finished.\n";
?>