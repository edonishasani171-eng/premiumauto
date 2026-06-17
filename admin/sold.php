<?php
require_once 'auth_guard.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: dashboard.php?section=manage'); exit; }

$stmt = $conn->prepare("UPDATE cars SET sold = 1, sold_at = NOW() WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();

$_SESSION['sold_success'] = true;
header('Location: dashboard.php?section=manage');
exit;