<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include('../database.php');

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!$data || empty($data['items'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit();
}

$page_number = intval($data['page_number'] ?? 1);
$farm_id = intval($data['farm_id'] ?? 1);
$items = $data['items'];

function ensurePaginationExists($page_number, $farm_id)
{
    global $pdo;
    $check = $pdo->prepare('SELECT COUNT(*) c FROM pagination WHERE page_number = ? AND farm_id = ?');
    $check->execute([$page_number, $farm_id]);
    $r = $check->fetch(PDO::FETCH_ASSOC);
    if (($r['c'] ?? 0) == 0) {
        $types = ['summary', 'food', 'sales', 'medicine', 'grand-total'];
        foreach ($types as $t) {
            $stmt = $pdo->prepare('INSERT INTO pagination (page_number, page_type, farm_id) VALUES (?, ?, ?)');
            try {
                $stmt->execute([$page_number, $t, $farm_id]);
            } catch (Exception $e) {
            }
        }
    }
}

try {
    ensurePaginationExists($page_number, $farm_id);
    $prev_total_feed = 0;
    $prev_cumulative_rate = 0;
    foreach ($items as $row) {

        $id = $row['id'] ?? null;
        $chicken_type = $data['chicken_type'] ?? 'CP';
        $age = intval($row['age'] ?? 0);
        $date = $row['date'] ?? date('Y-m-d');
        $company_in   = floatval($row['company_in']   ?? 0);
        $mix_in       = floatval($row['mix_in']       ?? 0);


        //total feed 
        $total_feed = floatval($row['total_feed'] ??
            ($company_in + $mix_in + $prev_total_feed));
        $prev_total_feed = $total_feed;

        $company_left = floatval($row['company_left'] ?? 0);
        $mix_left     = floatval($row['mix_left']     ?? 0);

        // Daily eat rate 
        $daily_rate = floatval($row['daily_rate'] ??
            ($cumulative_rate - $prev_cumulative_rate));
        $prev_cumulative_rate = $cumulative_rate;

        // Cumulative eat rate
        $cumulative_rate = floatval($row['cumulative_rate'] ??
            ($total_feed - $company_left - $mix_left));

        // Weight
        $weight = floatval($row['weight'] ?? 0);

        // Dead
        $dead = intval($row['dead'] ?? 0);

        // Cumulative dead
        $cumulative_dead = intval($row['cumulative_dead'] ??
            ($cumulative_dead - $prev_cumulative_dead));
        $prev_cumulative_dead = $cumulative_dead;

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
        } else {

            $stmt = $pdo->prepare('INSERT INTO summary 
        ($chicken_type, age, date, company_in, mix_in, total_feed, company_left, mix_left, 
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
        }
    }
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
