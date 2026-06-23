<?php
$host     = getenv('DB_HOST')     ?: 'localhost';
$username = getenv('DB_USER')     ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';
$dbname   = getenv('DB_NAME')     ?: 'carorder_db';
$port     = (int)(getenv('DB_PORT') ?: 3306);

echo "Host: $host<br>";
echo "Port: $port<br>";
echo "DB: $dbname<br>";
echo "User: $username<br>";

$conn = new mysqli($host, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    echo "FAILED: " . $conn->connect_error;
} else {
    echo "CONNECTED OK<br>";
    $r = $conn->query("SELECT id, username FROM admins");
    while ($row = $r->fetch_assoc()) {
        echo "User: " . $row['username'] . "<br>";
    }
}