<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

include 'database.php';

try {
    $start_date = $_GET['start_date'] ?? null;
    $end_date = $_GET['end_date'] ?? null;
    $page = $_GET['page'] ?? 1;
    $farm_id = $_GET['farm_id'] ?? 1;
    
    // Build base query
    $sql = "SELECT s.*, u.username as comment_author 
            FROM sales_summary s 
            LEFT JOIN users u ON s.comment_author_id = u.id 
            WHERE s.page_number = ? AND s.farm_id = ?";
    
    $params = [$page, $farm_id];
    
    // Add date filters if provided
    if ($start_date && $end_date) {
        $sql .= " AND s.date BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
    }
    
    // Add ORDER BY at the end
    $sql .= " ORDER BY s.date ASC";
    
    error_log("Get Sales SQL: " . $sql);
    error_log("Get Sales Params: " . implode(', ', $params));
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate cumulative totals
    $cumulativeSoldCount = 0;
    $cumulativeTotalWeight = 0;
    
    foreach ($data as &$row) {
        $soldCount = floatval($row['sold_count'] ?? 0);
        $weightPerChicken = floatval($row['weight_per_chicken'] ?? 0);
        
        // Calculate cumulative totals
        $cumulativeSoldCount += $soldCount;
        $cumulativeTotalWeight += $weightPerChicken;
        
        // Update cumulative fields
        $row['total_sold_count'] = $cumulativeSoldCount;
        $row['total_weight'] = $cumulativeTotalWeight;
        
        // Ensure all required fields have values for comments
        $row['has_comment'] = $row['has_comment'] ?? 0;
        $row['comment_read'] = $row['comment_read'] ?? 0;
        $row['comments'] = $row['comments'] ?? '';
        $row['comment_author'] = $row['comment_author'] ?? '';
        $row['comment_created_at'] = $row['comment_created_at'] ?? '';
    }
    
    echo json_encode($data);
    
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Get Sales Error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>