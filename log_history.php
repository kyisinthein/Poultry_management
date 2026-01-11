<?php
include('database.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required_fields = ['farm_id', 'page_number', 'action_type', 'table_name', 'description'];
    
    // Validate required fields
    foreach ($required_fields as $field) {
        if (!isset($input[$field])) {
            echo json_encode(['success' => false, 'error' => "Missing field: $field"]);
            exit();
        }
    }
    
    $user_id = $_SESSION['user_id'];
    $farm_id = intval($input['farm_id']);
    $page_number = intval($input['page_number']);
    $action_type = $input['action_type'];
    $table_name = $input['table_name'];
    $record_id = isset($input['record_id']) ? intval($input['record_id']) : 0;
    $description = $input['description'];
    
    try {
        // Use the logHistory function from database.php
        $log_id = logHistory($user_id, $farm_id, $page_number, $action_type, $table_name, $record_id, null, null, $description);
        
        if ($log_id) {
            echo json_encode(['success' => true, 'id' => $log_id]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to log history']);
        }
        
    } catch (PDOException $e) {
        error_log("History log error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>