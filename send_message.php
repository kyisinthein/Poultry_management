<?php
require_once 'database.php';


// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sender_id = $_SESSION['user_id'];
    $receiver_id = isset($_POST['receiver_id']) ? ($_POST['receiver_id'] === 'all' ? null : $_POST['receiver_id']) : null;
    $message = trim($_POST['message'] ?? '');
    
    // Handle file upload
    $file_name = null;
    $file_path = null;
    $file_size = null;
    $message_type = 'text';
    
    // Check if file was uploaded
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = './assets/uploads/';
        
        // Create uploads directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_name = basename($_FILES['file']['name']);
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Generate unique filename
        $unique_name = uniqid() . '_' . $file_name;
        $file_path = $upload_dir . $unique_name;
        $file_size = $_FILES['file']['size'];
        
     

// Validate file type - Add more file types
$allowed_extensions = [
    'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', // Images
    'pdf', // PDF
    'doc', 'docx', 'txt', // Documents
    'zip', 'rar', '7z', // Archives
    'mp4', 'avi', 'mov', // Videos
    'mp3', 'wav' ,// Audio
    'xlsx'
];


        
        if (!in_array($file_extension, $allowed_extensions)) {
            echo json_encode(['success' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', $allowed_extensions)]);
            exit;
        }
        
        // File size limit (100MB)
        $max_file_size = 100 * 1024 * 1024;
        if ($file_size > $max_file_size) {
            echo json_encode(['success' => false, 'error' => 'File too large. Maximum size is 5MB.']);
            exit;
        }
        
        // Move uploaded file
        if (move_uploaded_file($_FILES['file']['tmp_name'], $file_path)) {
            $message_type = 'file';
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to upload file.']);
            exit;
        }
    }
    
    // Validate: must have either message or file
    if (empty($message) && $message_type !== 'file') {
        echo json_encode(['success' => false, 'error' => 'Message or file is required']);
        exit;
    }
    
    // Insert message
    $sql = "INSERT INTO messages (sender_id, receiver_id, message_type, message, file_name, file_path, file_size) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = executeQuery($sql, [
        $sender_id, 
        $receiver_id, 
        $message_type, 
        $message, 
        $file_name, 
        $file_path, 
        $file_size
    ]);
    
    if ($stmt) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to send message']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>