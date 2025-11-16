<?php
// Enable error reporting for debugging
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

// Include database without session start
include 'database.php';

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

// Get the raw POST data
$input = file_get_contents('php://input');
error_log("Bulk Save - Raw input: " . $input);

$data = json_decode($input, true);

if ($data === null) {
    error_log("Bulk Save - JSON decode error: " . json_last_error_msg());
    echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
    exit();
}

try {
    $results = [];
    
    // Get farm_id and page_number
    $farm_id = $data['farm_id'] ?? 1;
    $page_number = $data['page_number'] ?? 1;
    
    error_log("Bulk Save - Farm ID: $farm_id, Page: $page_number");
    
    // CHECK 1: Ensure pagination record exists for this page and farm
    ensurePaginationExists($page_number, $farm_id); // ✅ FIXED: Removed $this->
    
    if (isset($data['sales']) && is_array($data['sales'])) {
        error_log("Processing " . count($data['sales']) . " sales records");
        
        foreach ($data['sales'] as $index => $sale) {
            $current_farm_id = $sale['farm_id'] ?? $farm_id;
            $current_page = $sale['page_number'] ?? $page_number;
            
            // CHECK 2: Ensure pagination exists for each row's page number
            if ($current_page != $page_number) {
                ensurePaginationExists($current_page, $current_farm_id); // ✅ FIXED: Removed $this->
            }
            
            // Ensure all required fields have values
            $date = !empty($sale['date']) ? $sale['date'] : date('Y-m-d');
            $sold_count = $sale['sold_count'] ?? 0;
            $weight_per_chicken = $sale['weight_per_chicken'] ?? 0;
            $total_sold_count = $sale['total_sold_count'] ?? 0;
            $total_weight = $sale['total_weight'] ?? 0;
            $daily_weight = $sale['daily_weight'] ?? 0;
            $dead_count = $sale['dead_count'] ?? 0;
            $mortality_rate = $sale['mortality_rate'] ?? 0;
            $cumulative_sold_count = $sale['cumulative_sold_count'] ?? 0;
            $surplus_deficit = $sale['surplus_deficit'] ?? 0;
            $weight_21to30 = $sale['weight_21to30'] ?? 0;
            $weight_31to36 = $sale['weight_31to36'] ?? 0;
            $weight_37to_end = $sale['weight_37to_end'] ?? 0;
            $total_chicken_weight = $sale['total_chicken_weight'] ?? 0;
            $total_feed_consumption_rate = $sale['total_feed_consumption_rate'] ?? 0;
            $total_feed_weight = $sale['total_feed_weight'] ?? 0;
            $final_weight = $sale['final_weight'] ?? 0;
            $fcr = $sale['fcr'] ?? 0;
            $comments = $sale['comments'] ?? '';
            $chicken_type = $sale['chicken_type'] ?? 'CP';
            $initial_count = $sale['initial_count'] ?? 4080;
            $current_count = $sale['current_count'] ?? 3880;
            
            // Debug log to see what data we're processing
            error_log("Processing row $index - ID: " . ($sale['id'] ?? 'NEW') . ", Date: $date");
            
            if (isset($sale['id']) && $sale['id'] > 0) {
                // Update existing record
                error_log("Updating existing record ID: " . $sale['id']);
                
                $stmt = $pdo->prepare("
                    UPDATE sales_summary SET 
                    date = ?, sold_count = ?, weight_per_chicken = ?, total_sold_count = ?, total_weight = ?,
                    daily_weight = ?, dead_count = ?, mortality_rate = ?, cumulative_sold_count = ?, surplus_deficit = ?,
                    weight_21to30 = ?, weight_31to36 = ?, weight_37to_end = ?, total_chicken_weight = ?,
                    total_feed_consumption_rate = ?, total_feed_weight = ?, final_weight = ?, fcr = ?, comments = ?,
                    chicken_type = ?, initial_count = ?, current_count = ?, page_number = ?, farm_id = ?
                    WHERE id = ? AND farm_id = ?
                ");
                
                $stmt->execute([
                    $date, $sold_count, $weight_per_chicken, $total_sold_count, $total_weight,
                    $daily_weight, $dead_count, $mortality_rate, $cumulative_sold_count, $surplus_deficit,
                    $weight_21to30, $weight_31to36, $weight_37to_end, $total_chicken_weight,
                    $total_feed_consumption_rate, $total_feed_weight, $final_weight, $fcr, $comments,
                    $chicken_type, $initial_count, $current_count, $current_page, $current_farm_id,
                    $sale['id'], $current_farm_id
                ]);
                
                if ($stmt->rowCount() > 0) {
                    $results[] = ['success' => true, 'id' => $sale['id'], 'action' => 'updated'];
                    error_log("Successfully updated record ID: " . $sale['id']);
                } else {
                    $results[] = ['success' => false, 'error' => 'No rows updated', 'id' => $sale['id']];
                    error_log("No rows updated for ID: " . $sale['id']);
                }
                
            } else {
                // Insert new record
                error_log("Inserting new record");
                
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
                    $date, $sold_count, $weight_per_chicken, $total_sold_count, $total_weight,
                    $daily_weight, $dead_count, $mortality_rate, $cumulative_sold_count, $surplus_deficit,
                    $weight_21to30, $weight_31to36, $weight_37to_end, $total_chicken_weight,
                    $total_feed_consumption_rate, $total_feed_weight, $final_weight, $fcr, $comments,
                    $chicken_type, $initial_count, $current_count, $current_page, $current_farm_id
                ]);
                
                $id = $pdo->lastInsertId();
                $results[] = ['success' => true, 'id' => $id, 'action' => 'inserted'];
                error_log("Successfully inserted new record ID: " . $id);
            }
        }
    } else {
        error_log("No sales data found in request");
        echo json_encode(['success' => false, 'error' => 'No sales data provided']);
        exit();
    }
    
    echo json_encode([
        'success' => true, 
        'results' => $results,
        'message' => 'Bulk save completed successfully',
        'total_processed' => count($results)
    ]);
    
} catch (PDOException $e) {
    error_log("Bulk Save PDO Error: " . $e->getMessage());
    error_log("Bulk Save Error Code: " . $e->getCode());
    error_log("Bulk Save SQL State: " . $e->errorInfo[0] ?? 'Unknown');
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Database error: ' . $e->getMessage(),
        'error_info' => $e->errorInfo
    ]);
} catch (Exception $e) {
    error_log("Bulk Save General Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'General error: ' . $e->getMessage()
    ]);
}
?>