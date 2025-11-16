<?php
require_once 'database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['total_unread' => 0]);
    exit();
}

$current_user_id = $_SESSION['user_id'];

// Get group chat unread count
$group_sql = "
    SELECT COUNT(*) as count 
    FROM messages 
    WHERE (receiver_id = 'all' OR receiver_id IS NULL) 
    AND sender_id != ? 
    AND is_read = FALSE
";
$group_result = fetchOne($group_sql, [$current_user_id]);
$group_unread = $group_result ? $group_result['count'] : 0;

// Get private messages unread count
$private_sql = "
    SELECT COUNT(*) as count 
    FROM messages 
    WHERE receiver_id = ? 
    AND sender_id != ? 
    AND is_read = FALSE
";
$private_result = fetchOne($private_sql, [$current_user_id, $current_user_id]);
$private_unread = $private_result ? $private_result['count'] : 0;

$total_unread = $group_unread + $private_unread;

echo json_encode(['total_unread' => $total_unread]);
?>