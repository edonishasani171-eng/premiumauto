<?php
$hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
$password = 'admin123';

echo "PHP version: " . phpversion() . "<br>";
echo "Hash: " . $hash . "<br>";
echo "Length: " . strlen($hash) . "<br>";
echo "Result: " . (password_verify($password, $hash) ? 'TRUE' : 'FALSE') . "<br>";

// Also test generating a new hash
$newHash = password_hash('admin123', PASSWORD_BCRYPT);
echo "New hash: " . $newHash . "<br>";
echo "New hash verify: " . (password_verify('admin123', $newHash) ? 'TRUE' : 'FALSE') . "<br>";
?>