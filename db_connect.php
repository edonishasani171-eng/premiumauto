<?php
// Look for Clever Cloud environment variables on Render, fall back to local XAMPP if not found
$host = getenv('DB_HOST')     ?: 'localhost';
$username = getenv('DB_USER')     ?: 'root';
$password = getenv('DB_PASSWORD') ?: ''; // Default XAMPP password is empty
$dbname = getenv('DB_NAME')     ?: 'carorder_db';
$port = getenv('DB_PORT')     ?: '3306';

// Create connection
$conn = new mysqli($host, $username, $password, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>