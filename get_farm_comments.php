<?php
require_once 'database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}

$farm_id = intval($_GET['farm_id']);

$sql = "SELECT fc.*, u.username 
        FROM farm_comments fc 
        JOIN users u ON fc.user_id = u.id 
        WHERE fc.farm_id = ? 
        ORDER BY fc.created_at DESC";

$comments = fetchAll($sql, [$farm_id]);

echo json_encode($comments);
?>