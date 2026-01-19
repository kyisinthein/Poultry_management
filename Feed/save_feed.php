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
$target = $data['target'] ?? 'summary';
ensurePaginationExists($page, $farm);

$id = !empty($data['id']) ? intval($data['id']) : null;

if ($target === 'remain'){
    $rem_cate = $data['rem_cate'] ?? null;
    $rem_name = $data['rem_name'] ?? null;
    $rem_quan = isset($data['rem_quan']) ? floatval($data['rem_quan']) : 0;
    $rem_price = isset($data['rem_price']) ? floatval($data['rem_price']) : 0;
    $rem_total = isset($data['rem_total']) ? floatval($data['rem_total']) : ($rem_quan * $rem_price);

    if ($id){
        $stmt = $pdo->prepare("UPDATE feed_remain SET rem_cate=?, rem_name=?, rem_quan=?, rem_price=?, rem_total=?, page_number=?, farm_id=? WHERE id=?");
        $stmt->execute([$rem_cate, $rem_name, $rem_quan, $rem_price, $rem_total, $page, $farm, $id]);
        echo json_encode(['success'=>true,'id'=>$id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO feed_remain (rem_cate, rem_name, rem_quan, rem_price, rem_total, page_number, farm_id) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$rem_cate, $rem_name, $rem_quan, $rem_price, $rem_total, $page, $farm]);
        echo json_encode(['success'=>true,'id'=>$pdo->lastInsertId()]);
    }
} else {
    $date = !empty($data['date']) ? $data['date'] : date('Y-m-d');
    $feed_category = $data['feed_category'] ?? null;
    $feed_name = $data['feed_name'] ?? null;
    $quantity = isset($data['quantity']) ? floatval($data['quantity']) : 0;
    $unit_price = isset($data['unit_price']) ? floatval($data['unit_price']) : 0;
    $total_cost = isset($data['total_cost']) ? floatval($data['total_cost']) : ($quantity * $unit_price);

    if ($id){
        $stmt = $pdo->prepare("UPDATE feed_summary SET date=?, feed_category=?, feed_name=?, quantity=?, unit_price=?, total_cost=?, page_number=?, farm_id=? WHERE id=?");
        $stmt->execute([$date, $feed_category, $feed_name, $quantity, $unit_price, $total_cost, $page, $farm, $id]);
        echo json_encode(['success'=>true,'id'=>$id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO feed_summary (date, feed_category, feed_name, quantity, unit_price, total_cost, page_number, farm_id) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$date, $feed_category, $feed_name, $quantity, $unit_price, $total_cost, $page, $farm]);
        echo json_encode(['success'=>true,'id'=>$pdo->lastInsertId()]);
    }
}
?>
