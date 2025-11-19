<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include('../database.php');

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!$data || empty($data['row_id'])){ echo json_encode(['success'=>false,'error'=>'Invalid row id']); exit(); }
$id = intval($data['row_id']);
$comment = trim($data['comment'] ?? '');
$user_id = intval($data['user_id'] ?? 0);

try{
  $stmt = $pdo->prepare('UPDATE feed_summary SET comments = ?, has_comment = 1, comment_read = 0, comment_author_id = ?, comment_created_at = NOW() WHERE id = ?');
  $stmt->execute([$comment, $user_id, $id]);
  echo json_encode(['success'=>true]);
}catch(Exception $e){
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
?>