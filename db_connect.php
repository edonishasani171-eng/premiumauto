<?php
// Look for Clever Cloud environment variables on Render, fall back to local XAMPP if not found
$host = getenv('DB_HOST')     ?: 'b7wqnxtrqrmjsplfibrn-mysql.services.clever-cloud.com';
$username = getenv('DB_USER')     ?: 'uvibbujjmdiva7cq';
$password = getenv('DB_PASSWORD') ?: '95nGnn40Cj3F1Q2ov2yO'; // Default XAMPP password is empty
$dbname = getenv('DB_NAME')     ?: 'b7wqnxtrqrmjsplfibrn';
$port = getenv('DB_PORT')     ?: '3306';

// Create connection
$conn = new mysqli($host, $username, $password, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>