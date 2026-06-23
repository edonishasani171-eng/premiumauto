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

class DBSessionHandler implements SessionHandlerInterface {
    private $conn;
    public function __construct($conn) { $this->conn = $conn; }
    public function open($path, $name): bool { return true; }
    public function close(): bool { return true; }
    public function read($id): string {
        $stmt = $this->conn->prepare("SELECT data FROM sessions WHERE id = ? AND expires > NOW()");
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ? $result['data'] : '';
    }
    public function write($id, $data): bool {
        $stmt = $this->conn->prepare("REPLACE INTO sessions (id, data, expires) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 2 HOUR))");
        $stmt->bind_param('ss', $id, $data);
        return $stmt->execute();
    }
    public function destroy($id): bool {
        $stmt = $this->conn->prepare("DELETE FROM sessions WHERE id = ?");
        $stmt->bind_param('s', $id);
        return $stmt->execute();
    }
    public function gc($max_lifetime): int|false { return 0; }
}

session_set_save_handler(new DBSessionHandler($conn), true);
?>
