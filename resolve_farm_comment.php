<?php
require_once 'database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit();
}

if ($_POST) {
    $comment_id = intval($_POST['comment_id']);
    
    $sql = "UPDATE farm_comments SET is_resolved = TRUE WHERE id = ?";
    $stmt = executeQuery($sql, [$comment_id]);
    
    if ($stmt) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to resolve comment']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}
?>