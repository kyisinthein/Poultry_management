<?php
require_once 'database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}

$current_user_id = $_SESSION['user_id'];

// Get unread counts for each user
$sql = "
    SELECT 
        u.id as user_id,
        u.username,
        u.role,
        COUNT(m.id) as unread_count
    FROM users u
    LEFT JOIN messages m ON (
        (m.sender_id = u.id AND m.receiver_id = ?) OR 
        (m.sender_id = u.id AND m.receiver_id IS NULL)
    ) AND m.is_read = FALSE AND m.sender_id != ?
    WHERE u.id != ? AND u.is_active = TRUE
    GROUP BY u.id, u.username, u.role
    ORDER BY u.role, u.username
";

$unread_counts = fetchAll($sql, [$current_user_id, $current_user_id, $current_user_id]);

// Also get group chat unread count
$group_sql = "
    SELECT COUNT(*) as group_unread 
    FROM messages 
    WHERE receiver_id IS NULL 
    AND sender_id != ? 
    AND is_read = FALSE
";
$group_result = fetchOne($group_sql, [$current_user_id]);
$group_unread = $group_result ? $group_result['group_unread'] : 0;

$response = [
    'users' => $unread_counts,
    'group_unread' => $group_unread
];

echo json_encode($response);
?>