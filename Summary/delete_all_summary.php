<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include('../database.php');

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!$data){ echo json_encode(['success'=>false,'error'=>'Invalid JSON']); exit(); }

$page = intval($data['page_number'] ?? 1);
$farm = intval($data['farm_id'] ?? 1);

try {
    $page_to_delete = $page;
    $pages_sql = "SELECT DISTINCT page_number FROM pagination WHERE farm_id = ? ORDER BY page_number ASC";
    $pages_result = fetchAll($pages_sql, [$farm]);
    $global_pages = array_column($pages_result, 'page_number');
    $page_mapping = [];
    foreach ($global_pages as $index => $global_page) {
        $display_page = $index + 1;
        $page_mapping[$display_page] = $global_page;
    }
    if (isset($page_mapping[$page])) {
        $page_to_delete = $page_mapping[$page];
    }

    $stmt = $pdo->prepare('DELETE FROM summary WHERE page_number = ? AND farm_id = ?');
    $stmt->execute([$page_to_delete, $farm]);
    echo json_encode(['success'=>true]);
} catch (Exception $e) {
    echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
}
?>
