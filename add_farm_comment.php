<?php
require_once 'database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

// Check if user is owner (admin)
if ($_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Only owners can add comments']);
    exit();
}

if ($_POST) {
    $farm_id = intval($_POST['farm_id']);
    $user_id = $_SESSION['user_id'];
    $comment_text = trim($_POST['comment_text']);
    
    if (empty($comment_text)) {
        echo json_encode(['success' => false, 'error' => 'Comment text is required']);
        exit();
    }
    
    $sql = "INSERT INTO farm_comments (farm_id, user_id, comment_text) VALUES (?, ?, ?)";
    $stmt = executeQuery($sql, [$farm_id, $user_id, $comment_text]);
    
    if ($stmt) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to add comment']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}
?>
