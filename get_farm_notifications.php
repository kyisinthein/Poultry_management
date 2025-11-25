<?php
require_once 'database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}

// Get unresolved comment count for each farm
$sql = "SELECT farm_id, COUNT(*) as unresolved_count 
        FROM farm_comments 
        WHERE is_resolved = FALSE 
        GROUP BY farm_id";

$notifications = fetchAll($sql);

echo json_encode($notifications);
?>