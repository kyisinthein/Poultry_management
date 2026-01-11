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
if (!$data || !isset($data['feeds']) || !is_array($data['feeds'])){ echo json_encode(['success'=>false,'error'=>'Invalid payload']); exit(); }

$farm = $data['farm_id'] ?? 1;
$page = $data['page_number'] ?? 1;
ensurePaginationExists($page, $farm);

$results = [];
foreach ($data['feeds'] as $feed){
    $date = !empty($feed['date']) ? $feed['date'] : date('Y-m-d');
    $feed_category = $feed['feed_category'] ?? null;
    $feed_name = $feed['feed_name'] ?? null;
    $quantity = isset($feed['quantity']) ? floatval($feed['quantity']) : 0;
    $unit_price = isset($feed['unit_price']) ? floatval($feed['unit_price']) : 0;
    $total_cost = isset($feed['total_cost']) ? floatval($feed['total_cost']) : ($quantity * $unit_price);
    $page_number = $feed['page_number'] ?? $page;
    $farm_id = $feed['farm_id'] ?? $farm;

    if (!empty($feed['id'])){
        $stmt = $pdo->prepare("UPDATE feed_summary SET date=?, feed_category=?, feed_name=?, quantity=?, unit_price=?, total_cost=?, page_number=?, farm_id=? WHERE id=?");
        $stmt->execute([$date, $feed_category, $feed_name, $quantity, $unit_price, $total_cost, $page_number, $farm_id, $feed['id']]);
        $results[] = ['id'=>$feed['id'],'action'=>'updated'];
    } else {
        $stmt = $pdo->prepare("INSERT INTO feed_summary (date, feed_category, feed_name, quantity, unit_price, total_cost, page_number, farm_id) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$date, $feed_category, $feed_name, $quantity, $unit_price, $total_cost, $page_number, $farm_id]);
        $results[] = ['id'=>$pdo->lastInsertId(),'action'=>'inserted'];
    }
}

echo json_encode(['success'=>true,'results'=>$results,'total_processed'=>count($results)]);
?>