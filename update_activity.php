<?php
require_once 'database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit();
}

$user_id = $_SESSION['user_id'];

// Update user's last activity
$sql = "UPDATE users SET last_activity = NOW() WHERE id = ?";
executeQuery($sql, [$user_id]);

echo json_encode(['success' => true]);
?>