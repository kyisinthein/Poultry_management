<?php
require_once 'database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

if (!isset($_GET['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User ID required']);
    exit();
}

$user_id = $_GET['user_id'];
$user = fetchOne("SELECT password FROM users WHERE id = ?", [$user_id]);

if ($user) {
    echo json_encode(['success' => true, 'password' => $user['password']]);
} else {
    echo json_encode(['success' => false, 'message' => 'User not found']);
}
?>