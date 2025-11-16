<?php
// update_food.php
include 'database.php';

header('Content-Type: application/json');

try {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update':
            updateFoodData();
            break;
            
        case 'delete':
            deleteFoodRow();
            break;
            
        case 'delete_all':
            deleteAllFoodData();
            break;
            
        case 'bulk_update':
        default:
            updateBulkFoodData();
            break;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function updateFoodData() {
    $row_id = $_POST['row_id'] ?? 0;
    $current_page = $_POST['page'] ?? 1;
    
    if (!$row_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid row ID']);
        return;
    }
    
    // Verify the row belongs to current page
    $verify_sql = "SELECT page_number FROM food_summary WHERE id = ?";
    $row_data = fetchOne($verify_sql, [$row_id]);
    
    if (!$row_data || $row_data['page_number'] != $current_page) {
        echo json_encode(['success' => false, 'message' => 'Row does not belong to current page']);
        return;
    }
    
    $fields = [
        'date', 'food_type', 'food_amount', 'food_price', 
        'total_cost', 'remaining_food', 'consumption_rate', 'comments'
    ];
    
    $updates = [];
    $params = [];
    
    foreach ($fields as $field) {
        if (isset($_POST[$field][$row_id])) {
            $updates[] = "$field = ?";
            $params[] = $_POST[$field][$row_id];
        }
    }
    
    if (empty($updates)) {
        echo json_encode(['success' => false, 'message' => 'No data to update']);
        return;
    }
    
    $params[] = $row_id;
    $sql = "UPDATE food_summary SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
    executeQuery($sql, $params);
    
    echo json_encode(['success' => true, 'message' => 'Food data updated successfully on page ' . $current_page]);
}

function updateBulkFoodData() {
    $current_page = $_POST['page'] ?? 1;
    
    // Update individual rows
    if (isset($_POST['date'])) {
        foreach ($_POST['date'] as $row_id => $value) {
            // Verify each row belongs to current page before updating
            $verify_sql = "SELECT page_number FROM food_summary WHERE id = ?";
            $row_data = fetchOne($verify_sql, [$row_id]);
            
            if ($row_data && $row_data['page_number'] == $current_page) {
                $fields = [
                    'date', 'food_type', 'food_amount', 'food_price', 
                    'total_cost', 'remaining_food', 'consumption_rate', 'comments'
                ];
                
                $updates = [];
                $params = [];
                
                foreach ($fields as $field) {
                    if (isset($_POST[$field][$row_id])) {
                        $updates[] = "$field = ?";
                        $params[] = $_POST[$field][$row_id];
                    }
                }
                
                if (!empty($updates)) {
                    $params[] = $row_id;
                    $sql = "UPDATE food_summary SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
                    executeQuery($sql, $params);
                }
            }
        }
    }
    
    $_SESSION['success'] = "စာမျက်နှာ $current_page ရှိ အစာစာရင်းအားလုံး သိမ်းပြီးပါပြီ။";
    header("Location: food.php?page=" . $current_page);
    exit();
}

function deleteFoodRow() {
    $row_id = $_POST['row_id'] ?? 0;
    $current_page = $_POST['page'] ?? 1;
    
    if (!$row_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid row ID']);
        return;
    }
    
    // Verify the row belongs to current page before deleting
    $verify_sql = "SELECT page_number FROM food_summary WHERE id = ?";
    $row_data = fetchOne($verify_sql, [$row_id]);
    
    if (!$row_data || $row_data['page_number'] != $current_page) {
        echo json_encode(['success' => false, 'message' => 'Row does not belong to current page']);
        return;
    }
    
    $sql = "DELETE FROM food_summary WHERE id = ?";
    executeQuery($sql, [$row_id]);
    
    echo json_encode(['success' => true, 'message' => 'Food data deleted successfully from page ' . $current_page]);
}

function deleteAllFoodData() {
    $current_page = $_POST['page'] ?? 1;
    
    $sql = "DELETE FROM food_summary WHERE page_number = ?";
    executeQuery($sql, [$current_page]);
    
    echo json_encode(['success' => true, 'message' => 'All food data deleted successfully from page ' . $current_page]);
}
?>