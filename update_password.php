<?php
// Clear any existing output
if (ob_get_length()) ob_clean();

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Turn off all error reporting to prevent HTML output
error_reporting(0);
ini_set('display_errors', 0);

require_once 'database.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die(json_encode(['success' => false, 'message' => 'Access denied']));
}

if (!isset($_POST['user_id']) || !isset($_POST['new_password'])) {
    die(json_encode(['success' => false, 'message' => 'Missing required fields']));
}

$user_id = $_POST['user_id'];
$new_password = trim($_POST['new_password']);

// Validate password length
if (strlen($new_password) < 4) {
    die(json_encode(['success' => false, 'message' => 'Password must be at least 4 characters']));
}

try {
    // Get user data to verify existence
    $user = fetchOne("SELECT id, username FROM users WHERE id = ?", [$user_id]);
    
    if (!$user) {
        die(json_encode(['success' => false, 'message' => 'User not found']));
    }

    // Store password as plain text
    $result = executeQuery(
        "UPDATE users SET password = ? WHERE id = ?",
        [$new_password, $user_id]
    );
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'Password updated successfully for user: ' . $user['username']
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to update password'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred'
    ]);
}
?>