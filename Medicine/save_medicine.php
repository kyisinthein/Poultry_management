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
$date = $data['date'] ?? date('Y-m-d');
$age_group = $data['age_group'] ?? '';
$medicine_name = $data['medicine_name'] ?? '';
$dose_amount = floatval($data['dose_amount'] ?? 0);
$dose_unit = $data['dose_unit'] ?? null;
$frequency = intval($data['frequency'] ?? 0);
$total_used = floatval($data['total_used'] ?? ($dose_amount * $frequency));
$total_used_unit = $data['total_used_unit'] ?? $dose_unit;
$unit_price = floatval($data['unit_price'] ?? 0);
$total_cost = floatval($data['total_cost'] ?? ($total_used * $unit_price));

try {
  if ($id){
    $stmt = $pdo->prepare('UPDATE medicine_summary SET date=?, age_group=?, medicine_name=?, dose_amount=?, dose_unit=?, frequency=?, total_used=?, total_used_unit=?, unit_price=?, total_cost=?, page_number=?, farm_id=? WHERE id=?');
    $stmt->execute([$date, $age_group, $medicine_name, $dose_amount, $dose_unit, $frequency, $total_used, $total_used_unit, $unit_price, $total_cost, $page_number, $farm_id, $id]);
    echo json_encode(['success'=>true, 'id'=>$id]);
  } else {
    $stmt = $pdo->prepare('INSERT INTO medicine_summary (date, age_group, medicine_name, dose_amount, dose_unit, frequency, total_used, total_used_unit, unit_price, total_cost, page_number, farm_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([$date, $age_group, $medicine_name, $dose_amount, $dose_unit, $frequency, $total_used, $total_used_unit, $unit_price, $total_cost, $page_number, $farm_id]);
    echo json_encode(['success'=>true, 'id'=>$pdo->lastInsertId()]);
  }
} catch (Exception $e) {
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
?>