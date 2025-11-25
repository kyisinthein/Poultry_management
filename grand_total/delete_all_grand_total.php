<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include('../database.php');

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!$data){ echo json_encode(['success'=>false,'error'=>'Invalid JSON']); exit(); }

$page = intval($data['page_number'] ?? 1);
$farm = intval($data['farm_id'] ?? 1);

try {
    $stmt = $pdo->prepare('DELETE FROM grand_total WHERE page_number = ? AND farm_id = ?');
    $stmt->execute([$page, $farm]);
    echo json_encode(['success'=>true]);
} catch (Exception $e) {
    echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
}
?>