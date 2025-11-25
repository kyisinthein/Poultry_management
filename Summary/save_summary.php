<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include('../database.php');

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit();
}

$id = $data['id'] ?? null;

$page_number = intval($data['page_number'] ?? 1);
$farm_id = intval($data['farm_id'] ?? 1);

$chicken_type = $data['chicken_type'] ?? 'CP';

$date = $data['date'] ?? date('Y-m-d');
$age = $data['age'] ?? '';

$company_in = floatval($data['company_in'] ?? 0);
$mix_in = floatval($data['mix_in'] ?? 0);
$total_feed = floatval($data['total_feed'] ?? 0);

$company_left = floatval($data['company_left'] ?? 0);
$mix_left = floatval($data['mix_left'] ?? 0);

$cumulative_rate = floatval($data['cumulative_rate'] ?? 0);
$daily_rate = floatval($data['daily_rate'] ?? 0);

$weight = floatval($data['weight'] ?? 0);
$dead = intval($data['dead'] ?? 0);
$cumulative_dead = intval($data['cumulative_dead'] ?? 0);


try {
    if ($id) {

        $stmt = $pdo->prepare('UPDATE summary 
        SET chicken_type = ?, age=?, date=?, company_in=?, mix_in=?, total_feed=?, company_left=?, mix_left=?, 
            cumulative_rate=?, daily_rate=?, weight=?, dead=?, cumulative_dead=?, page_number=?, farm_id=?
        WHERE id=?');

        $stmt->execute([
            $chicken_type,
            $age,
            $date,
            $company_in,
            $mix_in,
            $total_feed,
            $company_left,
            $mix_left,
            $cumulative_rate,
            $daily_rate,
            $weight,
            $dead,
            $cumulative_dead,
            $page_number,
            $farm_id,
            $id
        ]);
        echo json_encode(['success' => true, 'id' => $id]);
    } else {

        $stmt = $pdo->prepare('INSERT INTO summary 
        (chicken_type,age, date, company_in, mix_in, total_feed, company_left, mix_left, 
         cumulative_rate, daily_rate, weight, dead, cumulative_dead, page_number, farm_id)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');

        $stmt->execute([
            $chicken_type,
            $age,
            $date,
            $company_in,
            $mix_in,
            $total_feed,
            $company_left,
            $mix_left,
            $cumulative_rate,
            $daily_rate,
            $weight,
            $dead,
            $cumulative_dead,
            $page_number,
            $farm_id
        ]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
