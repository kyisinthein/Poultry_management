<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include('../database.php');

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!$data || empty($data['items'])){ echo json_encode(['success'=>false,'error'=>'Invalid JSON']); exit(); }

$page_number = intval($data['page_number'] ?? 1);
$farm_id = intval($data['farm_id'] ?? 1);
$items = $data['items'];

function ensurePaginationExists($page_number, $farm_id){
  global $pdo;
  $check = $pdo->prepare('SELECT COUNT(*) c FROM pagination WHERE page_number = ? AND farm_id = ?');
  $check->execute([$page_number, $farm_id]);
  $r = $check->fetch(PDO::FETCH_ASSOC);
  if (($r['c'] ?? 0) == 0){
    $types = ['summary','food','sales','medicine','grand-total'];
    foreach ($types as $t){ $stmt = $pdo->prepare('INSERT INTO pagination (page_number, page_type, farm_id) VALUES (?, ?, ?)'); try{ $stmt->execute([$page_number, $t, $farm_id]); }catch(Exception $e){} }
  }
}

try{
  ensurePaginationExists($page_number, $farm_id);
  foreach ($items as $row){
    $id = $row['id'] ?? null;
    $serial_no = intval($row['serial_no'] ?? 0);
    $name = $row['name'] ?? '';
    $type = $row['type'] ?? '';
    $quantity = intval($row['quantity'] ?? 0);
    $weight = floatval($row['weight'] ?? 0);
    $sold = floatval($row['sold'] ?? 0);
    $dead = intval($row['dead'] ?? 0);
    $excess_deficit = $row['excess_deficit'] ?? '';
    $finished_weight = floatval($row['finished_weight'] ?? 0);
    $feed_weight = floatval($row['feed_weight'] ?? 0);
    $company = $row['company'] ?? '';
    $mixed = $row['mixed'] ?? '';
    $feed_bag = intval($row['feed_bag'] ?? 0);
    $medicine = floatval($row['medicine'] ?? 0);
    $feed = floatval($row['feed'] ?? 0);
    $charcoal = floatval($row['charcoal'] ?? 0);
    $bran = floatval($row['bran'] ?? 0);
    $lime = floatval($row['lime'] ?? 0);
    $tfcr = floatval($row['tfcr'] ?? 0);
    
    if ($id){
      $stmt = $pdo->prepare('UPDATE grand_total SET serial_no=?, name=?, type=?, quantity=?, weight=?, sold=?, dead=?, excess_deficit=?, finished_weight=?, feed_weight=?, company=?, mixed=?, feed_bag=?, medicine=?, feed=?, charcoal=?, bran=?, lime=?, tfcr=?, page_number=?, farm_id=? WHERE id=?');
      $stmt->execute([$serial_no, $name, $type, $quantity, $weight, $sold, $dead, $excess_deficit, $finished_weight, $feed_weight, $company, $mixed, $feed_bag, $medicine, $feed, $charcoal, $bran, $lime, $tfcr, $page_number, $farm_id, $id]);
    } else {
      $stmt = $pdo->prepare('INSERT INTO grand_total (serial_no, name, type, quantity, weight, sold, dead, excess_deficit, finished_weight, feed_weight, company, mixed, feed_bag, medicine, feed, charcoal, bran, lime, tfcr, page_number, farm_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
      $stmt->execute([$serial_no, $name, $type, $quantity, $weight, $sold, $dead, $excess_deficit, $finished_weight, $feed_weight, $company, $mixed, $feed_bag, $medicine, $feed, $charcoal, $bran, $lime, $tfcr, $page_number, $farm_id]);
    }
  }
  echo json_encode(['success'=>true]);
}catch(Exception $e){ echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }
?>