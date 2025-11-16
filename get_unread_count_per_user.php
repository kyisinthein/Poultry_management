<?php
require_once 'database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}

$current_user_id = $_SESSION['user_id'];

// Get unread counts for private messages (sent to current user)
$private_sql = "
    SELECT 
        u.id as user_id,
        u.username,
        u.role,
        COUNT(m.id) as unread_count
    FROM users u
    LEFT JOIN messages m ON m.sender_id = u.id 
        AND m.receiver_id = ? 
        AND m.is_read = FALSE
    WHERE u.id != ? AND u.is_active = TRUE
    GROUP BY u.id, u.username, u.role
    ORDER BY u.role, u.username
";

$private_unread = fetchAll($private_sql, [$current_user_id, $current_user_id]);

// Get group chat unread count (receiver_id = 'all' or NULL)
$group_sql = "
    SELECT COUNT(*) as group_unread 
    FROM messages 
    WHERE (receiver_id = 'all' OR receiver_id IS NULL) 
    AND sender_id != ? 
    AND is_read = FALSE
";
$group_result = fetchOne($group_sql, [$current_user_id]);
$group_unread = $group_result ? $group_result['group_unread'] : 0;

// Calculate total unread count
$total_unread = $group_unread;
foreach ($private_unread as $user) {
    $total_unread += $user['unread_count'];
}

$response = [
    'users' => $private_unread,
    'group_unread' => $group_unread,
    'total_unread' => $total_unread
];

echo json_encode($response);
?>