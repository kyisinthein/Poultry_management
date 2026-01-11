<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include('../database.php');

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!$data || empty($data['row_id'])){ echo json_encode(['success'=>false,'error'=>'Invalid row id']); exit(); }
$id = intval($data['row_id']);

try{
  $stmt = $pdo->prepare('UPDATE summary SET comment_read = 1 WHERE id = ? AND has_comment = 1');
  $stmt->execute([$id]);
  echo json_encode(['success'=>true]);
}catch(Exception $e){
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
?>