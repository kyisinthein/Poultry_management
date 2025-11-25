<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include('../database.php');

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!$data){ echo json_encode(['success'=>false,'error'=>'Invalid JSON']); exit(); }

$id = $data['id'] ?? null;
$page_number = intval($data['page_number'] ?? 1);
$farm_id = intval($data['farm_id'] ?? 1);
$serial_no = intval($data['serial_no'] ?? 0);
$name = $data['name'] ?? '';
$type = $data['type'] ?? '';
$quantity = intval($data['quantity'] ?? 0);
$weight = floatval($data['weight'] ?? 0);
$sold = floatval($data['sold'] ?? 0);
$dead = intval($data['dead'] ?? 0);
$excess_deficit = $data['excess_deficit'] ?? '';
$finished_weight = floatval($data['finished_weight'] ?? 0);
$feed_weight = floatval($data['feed_weight'] ?? 0);
$company = $data['company'] ?? '';
$mixed = $data['mixed'] ?? '';
$feed_bag = intval($data['feed_bag'] ?? 0);
$medicine = floatval($data['medicine'] ?? 0);
$feed = floatval($data['feed'] ?? 0);
$charcoal = floatval($data['charcoal'] ?? 0);
$bran = floatval($data['bran'] ?? 0);
$lime = floatval($data['lime'] ?? 0);
$tfcr = floatval($data['tfcr'] ?? 0);

try {
  if ($id){
    $stmt = $pdo->prepare('UPDATE grand_total SET serial_no=?, name=?, type=?, quantity=?, weight=?, sold=?, dead=?, excess_deficit=?, finished_weight=?, feed_weight=?, company=?, mixed=?, feed_bag=?, medicine=?, feed=?, charcoal=?, bran=?, lime=?, tfcr=?, page_number=?, farm_id=? WHERE id=?');
    $stmt->execute([$serial_no, $name, $type, $quantity, $weight, $sold, $dead, $excess_deficit, $finished_weight, $feed_weight, $company, $mixed, $feed_bag, $medicine, $feed, $charcoal, $bran, $lime, $tfcr, $page_number, $farm_id, $id]);
    echo json_encode(['success'=>true, 'id'=>$id]);
  } else {
    $stmt = $pdo->prepare('INSERT INTO grand_total (serial_no, name, type, quantity, weight, sold, dead, excess_deficit, finished_weight, feed_weight, company, mixed, feed_bag, medicine, feed, charcoal, bran, lime, tfcr, page_number, farm_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([$serial_no, $name, $type, $quantity, $weight, $sold, $dead, $excess_deficit, $finished_weight, $feed_weight, $company, $mixed, $feed_bag, $medicine, $feed, $charcoal, $bran, $lime, $tfcr, $page_number, $farm_id]);
    echo json_encode(['success'=>true, 'id'=>$pdo->lastInsertId()]);
  }
} catch (Exception $e) {
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
?>