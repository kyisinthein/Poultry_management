<?php
require_once 'database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}

// Get users who were active in the last 5 minutes
$sql = "SELECT id, username, role, last_activity 
        FROM users 
        WHERE last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE) 
        AND id != ? 
        AND is_active = TRUE
        ORDER BY role, username";

$online_users = fetchAll($sql, [$_SESSION['user_id']]);

echo json_encode($online_users);
?>