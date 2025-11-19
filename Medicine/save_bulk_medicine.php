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
    $date = $row['date'] ?? date('Y-m-d');
    $age_group = $row['age_group'] ?? '';
    $medicine_name = $row['medicine_name'] ?? '';
    $dose_amount = floatval($row['dose_amount'] ?? 0);
    $dose_unit = $row['dose_unit'] ?? null;
    $frequency = intval($row['frequency'] ?? 0);
    $total_used = floatval($row['total_used'] ?? ($dose_amount * $frequency));
    $total_used_unit = $row['total_used_unit'] ?? $dose_unit;
    $unit_price = floatval($row['unit_price'] ?? 0);
    $total_cost = floatval($row['total_cost'] ?? ($total_used * $unit_price));
    if ($id){
      $stmt = $pdo->prepare('UPDATE medicine_summary SET date=?, age_group=?, medicine_name=?, dose_amount=?, dose_unit=?, frequency=?, total_used=?, total_used_unit=?, unit_price=?, total_cost=?, page_number=?, farm_id=? WHERE id=?');
      $stmt->execute([$date, $age_group, $medicine_name, $dose_amount, $dose_unit, $frequency, $total_used, $total_used_unit, $unit_price, $total_cost, $page_number, $farm_id, $id]);
    } else {
      $stmt = $pdo->prepare('INSERT INTO medicine_summary (date, age_group, medicine_name, dose_amount, dose_unit, frequency, total_used, total_used_unit, unit_price, total_cost, page_number, farm_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
      $stmt->execute([$date, $age_group, $medicine_name, $dose_amount, $dose_unit, $frequency, $total_used, $total_used_unit, $unit_price, $total_cost, $page_number, $farm_id]);
    }
  }
  echo json_encode(['success'=>true]);
}catch(Exception $e){ echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }
?>