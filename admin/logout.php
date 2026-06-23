<?php
require_once '../db_connect.php';
session_start();
session_unset();
session_destroy();
header('Location: login.php');
exit;
?>
