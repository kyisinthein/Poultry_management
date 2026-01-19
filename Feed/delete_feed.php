<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include('../database.php');

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!$data || empty($data['id'])){ echo json_encode(['success'=>false,'error'=>'Invalid ID']); exit(); }

$target = $data['target'] ?? 'summary';
$table = $target === 'remain' ? 'feed_remain' : 'feed_summary';

try {
    $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id = ?");
    $stmt->execute([$data['id']]);
    echo json_encode(['success'=>true]);
} catch (Exception $e) {
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
?>
