<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include('../database.php');

function ensurePaginationExists($page_number, $farm_id){
    global $pdo;
    $check = $pdo->prepare("SELECT COUNT(*) c FROM pagination WHERE page_number = ? AND farm_id = ?");
    $check->execute([$page_number, $farm_id]);
    $r = $check->fetch(PDO::FETCH_ASSOC);
    if (($r['c'] ?? 0) == 0){
        $types = ['summary','food','sales','medicine','grand-total'];
        foreach ($types as $t){
            $stmt = $pdo->prepare('INSERT INTO pagination (page_number, page_type, farm_id) VALUES (?, ?, ?)');
            try { $stmt->execute([$page_number, $t, $farm_id]); } catch(Exception $e){}
        }
    }
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!$data){ echo json_encode(['success'=>false,'error'=>'Invalid JSON']); exit(); }

$page = $data['page_number'] ?? 1;
$farm = $data['farm_id'] ?? 1;
ensurePaginationExists($page, $farm);

$date = !empty($data['date']) ? $data['date'] : date('Y-m-d');
$feed_category = $data['feed_category'] ?? null;
$feed_name = $data['feed_name'] ?? null;
$quantity = isset($data['quantity']) ? floatval($data['quantity']) : 0;
$unit_price = isset($data['unit_price']) ? floatval($data['unit_price']) : 0;
$total_cost = isset($data['total_cost']) ? floatval($data['total_cost']) : ($quantity * $unit_price);

if (!empty($data['id'])){
    $stmt = $pdo->prepare("UPDATE feed_summary SET date=?, feed_category=?, feed_name=?, quantity=?, unit_price=?, total_cost=?, page_number=?, farm_id=? WHERE id=?");
    $stmt->execute([$date, $feed_category, $feed_name, $quantity, $unit_price, $total_cost, $page, $farm, $data['id']]);
    echo json_encode(['success'=>true,'id'=>$data['id']]);
} else {
    $stmt = $pdo->prepare("INSERT INTO feed_summary (date, feed_category, feed_name, quantity, unit_price, total_cost, page_number, farm_id) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([$date, $feed_category, $feed_name, $quantity, $unit_price, $total_cost, $page, $farm]);
    echo json_encode(['success'=>true,'id'=>$pdo->lastInsertId()]);
}
?>