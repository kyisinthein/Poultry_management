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
    $name = $row['name'] ?? '';
    $type = $row['type'] ?? '';
    $quantity = intval($row['quantity'] ?? 0);
    $sold = floatval($row['sold'] ?? 0);
    $dead = intval($row['dead'] ?? 0);
    $excess_deficit = $row['excess_deficit'] ?? '';
    $finished_weight = floatval($row['finished_weight'] ?? 0);
    $feed_weight = floatval($row['feed_weight'] ?? 0);
    $company = $row['company'] ?? '';
    $mixed = $row['mixed'] ?? '';
    $feed_bag = intval($row['feed_bag'] ?? 0);
    $used_feed_bags = intval($row['used_feed_bags'] ?? 0);
    $feed_balance = intval($row['feed_balance'] ?? 0);
    $medicine = floatval($row['medicine'] ?? 0);
    $feed = floatval($row['feed'] ?? 0);
    $other_cost = floatval($row['other_cost'] ?? 0);
    $avg_weight = floatval($row['avg_weight'] ?? 0);
    $mortality_rate = floatval($row['mortality_rate'] ?? 0);
    $fcr = floatval($row['fcr'] ?? 0);
    $tfcr = floatval($row['tfcr'] ?? 0);
    
    if ($id){
      $stmt = $pdo->prepare('UPDATE grand_total SET name=?, type=?, quantity=?, sold=?, dead=?, excess_deficit=?, finished_weight=?, feed_weight=?, company=?, mixed=?, feed_bag=?, used_feed_bags=?, feed_balance=?, medicine=?, feed=?, other_cost=?, avg_weight=?, mortality_rate=?, fcr=?, tfcr=?, page_number=?, farm_id=? WHERE id=?');
      $stmt->execute([$name, $type, $quantity, $sold, $dead, $excess_deficit, $finished_weight, $feed_weight, $company, $mixed, $feed_bag, $used_feed_bags, $feed_balance, $medicine, $feed, $other_cost, $avg_weight, $mortality_rate, $fcr, $tfcr, $page_number, $farm_id, $id]);
    } else {
      $stmt = $pdo->prepare('INSERT INTO grand_total (name, type, quantity, sold, dead, excess_deficit, finished_weight, feed_weight, company, mixed, feed_bag, used_feed_bags, feed_balance, medicine, feed, other_cost, avg_weight, mortality_rate, fcr, tfcr, page_number, farm_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
      $stmt->execute([$name, $type, $quantity, $sold, $dead, $excess_deficit, $finished_weight, $feed_weight, $company, $mixed, $feed_bag, $used_feed_bags, $feed_balance, $medicine, $feed, $other_cost, $avg_weight, $mortality_rate, $fcr, $tfcr, $page_number, $farm_id]);
    }
  }
  echo json_encode(['success'=>true]);
}catch(Exception $e){ echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }
?>
