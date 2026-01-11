<?php
// update_sales.php
include 'database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    $field = $_POST['field'] ?? '';
    $value = $_POST['value'] ?? '';
    $page = $_POST['page'] ?? 1;
    
    $allowed_fields = [
        'date', 'sold_count', 'weight_per_chicken', 'total_sold_count', 
        'total_weight', 'daily_weight', 'dead_count', 'mortality_rate',
        'cumulative_sold_count', 'surplus_deficit', 'weight_21to30',
        'weight_31to36', 'weight_37to_end', 'total_chicken_weight',
        'total_feed_consumption_rate', 'total_feed_weight', 'final_weight',
        'fcr', 'comments', 'chicken_type', 'initial_count', 'current_count'
    ];
    
    if (in_array($field, $allowed_fields)) {
        try {
            if ($id > 0) {
                // Update existing row
                $sql = "UPDATE sales_summary SET $field = ?, page_number = ?, updated_at = NOW() WHERE id = ?";
                executeQuery($sql, [$value, $page, $id]);
                echo json_encode(['success' => true, 'message' => 'Updated successfully']);
            } else {
                // Insert new row
                $sql = "INSERT INTO sales_summary ($field, page_number, created_at) VALUES (?, ?, NOW())";
                executeQuery($sql, [$value, $page]);
                $new_id = $pdo->lastInsertId();
                echo json_encode(['success' => true, 'message' => 'Inserted successfully', 'id' => $new_id]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid field: ' . $field]);
    }
}
?>