<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

include 'database.php';

// Get the raw POST data
$input = file_get_contents('php://input');
error_log("Single Save - Raw input: " . $input);

$data = json_decode($input, true);

if ($data === null) {
    error_log("Single Save - JSON decode error: " . json_last_error_msg());
    echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
    exit();
}

/**
 * Ensure pagination record exists for the given page and farm
 */
function ensurePaginationExists($page_number, $farm_id) {
    global $pdo;
    
    // Check if pagination record exists
    $check_sql = "SELECT COUNT(*) as count FROM pagination WHERE page_number = ? AND farm_id = ? AND page_type = 'sales'";
    $stmt = $pdo->prepare($check_sql);
    $stmt->execute([$page_number, $farm_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] == 0) {
        error_log("Creating missing pagination record for page $page_number, farm $farm_id");
        
        // Create pagination records for all page types
        $page_types = ['summary', 'food', 'sales', 'medicine', 'grand-total'];
        foreach ($page_types as $type) {
            try {
                $insert_sql = "INSERT INTO pagination (page_number, page_type, farm_id) VALUES (?, ?, ?)";
                $stmt = $pdo->prepare($insert_sql);
                $stmt->execute([$page_number, $type, $farm_id]);
                error_log("Created pagination record: page $page_number, type $type, farm $farm_id");
            } catch (PDOException $e) {
                // If duplicate, that's fine - just log it
                if (strpos($e->getMessage(), '1062') !== false) {
                    error_log("Pagination record already exists: page $page_number, type $type, farm $farm_id");
                } else {
                    throw $e;
                }
            }
        }
    }
}

try {
    $page_number = $data['page_number'] ?? 1;
    $farm_id = $data['farm_id'] ?? 1;
    
    error_log("Single Save - Farm ID: $farm_id, Page: $page_number");
    
    // ✅ CORRECT PLACEMENT: Call ensurePaginationExists inside try block
    ensurePaginationExists($page_number, $farm_id);
    
    // Ensure required fields
    $date = !empty($data['date']) ? $data['date'] : date('Y-m-d');
    
    if (isset($data['id']) && $data['id'] > 0) {
        // Update existing record
        $stmt = $pdo->prepare("
            UPDATE sales_summary SET 
            date = ?, sold_count = ?, weight_per_chicken = ?, total_sold_count = ?, total_weight = ?,
            daily_weight = ?, dead_count = ?, mortality_rate = ?, cumulative_sold_count = ?, surplus_deficit = ?,
            weight_21to30 = ?, weight_31to36 = ?, weight_37to_end = ?, total_chicken_weight = ?,
            total_feed_consumption_rate = ?, total_feed_weight = ?, final_weight = ?, fcr = ?, comments = ?,
            chicken_type = ?, initial_count = ?, current_count = ?, page_number = ?, farm_id = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $date, 
            $data['sold_count'] ?? 0, 
            $data['weight_per_chicken'] ?? 0, 
            $data['total_sold_count'] ?? 0, 
            $data['total_weight'] ?? 0,
            $data['daily_weight'] ?? 0, 
            $data['dead_count'] ?? 0, 
            $data['mortality_rate'] ?? 0, 
            $data['cumulative_sold_count'] ?? 0, 
            $data['surplus_deficit'] ?? 0,
            $data['weight_21to30'] ?? 0, 
            $data['weight_31to36'] ?? 0, 
            $data['weight_37to_end'] ?? 0, 
            $data['total_chicken_weight'] ?? 0,
            $data['total_feed_consumption_rate'] ?? 0, 
            $data['total_feed_weight'] ?? 0, 
            $data['final_weight'] ?? 0, 
            $data['fcr'] ?? 0, 
            $data['comments'] ?? '',
            $data['chicken_type'] ?? 'CP',
            $data['initial_count'] ?? 4080,
            $data['current_count'] ?? 3880,
            $page_number,
            $farm_id,
            $data['id']
        ]);
        
        echo json_encode(['success' => true, 'id' => $data['id']]);
        
    } else {
        // Insert new record
        $stmt = $pdo->prepare("
            INSERT INTO sales_summary 
            (date, sold_count, weight_per_chicken, total_sold_count, total_weight,
             daily_weight, dead_count, mortality_rate, cumulative_sold_count, surplus_deficit,
             weight_21to30, weight_31to36, weight_37to_end, total_chicken_weight,
             total_feed_consumption_rate, total_feed_weight, final_weight, fcr, comments,
             chicken_type, initial_count, current_count, page_number, farm_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $date, 
            $data['sold_count'] ?? 0, 
            $data['weight_per_chicken'] ?? 0, 
            $data['total_sold_count'] ?? 0, 
            $data['total_weight'] ?? 0,
            $data['daily_weight'] ?? 0, 
            $data['dead_count'] ?? 0, 
            $data['mortality_rate'] ?? 0, 
            $data['cumulative_sold_count'] ?? 0, 
            $data['surplus_deficit'] ?? 0,
            $data['weight_21to30'] ?? 0, 
            $data['weight_31to36'] ?? 0, 
            $data['weight_37to_end'] ?? 0, 
            $data['total_chicken_weight'] ?? 0,
            $data['total_feed_consumption_rate'] ?? 0, 
            $data['total_feed_weight'] ?? 0, 
            $data['final_weight'] ?? 0, 
            $data['fcr'] ?? 0, 
            $data['comments'] ?? '',
            $data['chicken_type'] ?? 'CP',
            $data['initial_count'] ?? 4080,
            $data['current_count'] ?? 3880,
            $page_number,
            $farm_id
        ]);
        
        $id = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'id' => $id]);
    }
    
} catch (PDOException $e) {
    error_log("Single Save PDO Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>