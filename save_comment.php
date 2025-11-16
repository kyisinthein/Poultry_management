<?php
// Turn off all error reporting to prevent any output before JSON
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to catch any unexpected output
ob_start();

include('database.php');
session_start();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Clear any buffered output
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

// Get the raw POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if ($data === null) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
    exit();
}

$row_id = $data['row_id'] ?? null;
$comment = $data['comment'] ?? '';
$user_id = $data['user_id'] ?? null;

// Validate required fields
if (!$row_id || !$user_id) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Missing row_id or user_id']);
    exit();
}

// Validate row_id is numeric
if (!is_numeric($row_id)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Invalid row ID format']);
    exit();
}

try {
    // First check if the row exists
    $check_sql = "SELECT id FROM sales_summary WHERE id = ?";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$row_id]);
    $row_exists = $check_stmt->fetch();
    
    if (!$row_exists) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Row does not exist']);
        exit();
    }
    
    $has_comment = !empty($comment) ? 1 : 0;
    
    $sql = "UPDATE sales_summary SET 
            comments = ?, 
            has_comment = ?, 
            comment_author_id = ?, 
            comment_created_at = NOW(),
            comment_read = 0 
            WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$comment, $has_comment, $user_id, $row_id]);
    $rowCount = $stmt->rowCount();
    
    if ($result && $rowCount > 0) {
        ob_end_clean();
        echo json_encode(['success' => true]);
    } else {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'No changes made to database']);
    }
    
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

// Ensure no other output
exit();
?>