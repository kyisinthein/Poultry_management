<?php
require_once 'database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit();
}

$user_id = $_SESSION['user_id'];
$receiver_id = isset($_POST['receiver_id']) ? ($_POST['receiver_id'] === 'all' ? null : $_POST['receiver_id']) : null;

if ($receiver_id === null) {
    // Mark group messages as read
    $sql = "UPDATE messages SET is_read = TRUE 
            WHERE receiver_id IS NULL 
            AND sender_id != ?";
    executeQuery($sql, [$user_id]);
} else {
    // Mark private messages from this specific user as read
    $sql = "UPDATE messages SET is_read = TRUE 
            WHERE sender_id = ? AND receiver_id = ? 
            AND is_read = FALSE";
    executeQuery($sql, [$receiver_id, $user_id]);
}

echo json_encode(['success' => true]);
?>