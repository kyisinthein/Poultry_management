<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Database configuration
include 'database.php';

// Get the raw POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Debug: Log the received data
error_log("Received data: " . print_r($data, true));

if (isset($data['ids']) && is_array($data['ids']) && !empty($data['ids'])) {
    // Filter out empty IDs
    $ids = array_filter($data['ids']);
    if (empty($ids)) {
        echo json_encode(['success' => false, 'error' => 'No valid IDs provided']);
        exit;
    }
    
    try {
        // Create placeholders for the IN clause
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $sql = "DELETE FROM sales_summary WHERE id IN ($placeholders)";
        
        error_log("Executing SQL: " . $sql); // Debug log
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($ids);
        
        $deleted_count = $stmt->rowCount();
        echo json_encode([
            'success' => true, 
            'deleted_count' => $deleted_count,
            'message' => "Successfully deleted $deleted_count records"
        ]);
        
    } catch (PDOException $e) {
        error_log("PDO Error: " . $e->getMessage()); // Debug log
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'No IDs provided or invalid format']);
}
?>