<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include('../database.php');

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!$data){ echo json_encode(['success'=>false,'error'=>'Invalid JSON']); exit(); }

$page = $data['page_number'] ?? 1;
$farm = $data['farm_id'] ?? 1;

try {
    $stmt = $pdo->prepare('DELETE FROM feed_summary WHERE page_number = ? AND farm_id = ?');
    $stmt->execute([$page, $farm]);
    $stmt2 = $pdo->prepare('DELETE FROM feed_remain WHERE page_number = ? AND farm_id = ?');
    $stmt2->execute([$page, $farm]);
    echo json_encode(['success'=>true]);
} catch (Exception $e) {
    echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
}
?>
