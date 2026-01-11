<?php
require_once 'database.php';
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$current_user_id = $_SESSION['user_id'];
$receiver_id = isset($_GET['receiver_id']) ? $_GET['receiver_id'] : 'all';

try {
    if ($receiver_id === 'all' || $receiver_id === '') {
        // Group chat - get all messages where receiver_id is NULL
        $sql = "SELECT m.*, u.username as sender_name 
                FROM messages m 
                JOIN users u ON m.sender_id = u.id 
                WHERE m.receiver_id IS NULL 
                ORDER BY m.timestamp ASC";
        $messages = fetchAll($sql);
    } else {
        // Private chat - get messages between two users
        $sql = "SELECT m.*, u.username as sender_name 
                FROM messages m 
                JOIN users u ON m.sender_id = u.id 
                WHERE (m.sender_id = ? AND m.receiver_id = ?) 
                   OR (m.sender_id = ? AND m.receiver_id = ?)
                ORDER BY m.timestamp ASC";
        $messages = fetchAll($sql, [$current_user_id, $receiver_id, $receiver_id, $current_user_id]);
    }

    // Always return an array, even if empty
    $formatted_messages = [];
    if ($messages) {
        foreach ($messages as $message) {
            $formatted_messages[] = [
                'id' => $message['id'],
                'sender_name' => $message['sender_name'],
                'message_type' => $message['message_type'],
                'message' => $message['message'],
                'file_name' => $message['file_name'],
                'file_path' => $message['file_path'],
                'file_size' => $message['file_size'],
                'timestamp' => date('M j, g:i A', strtotime($message['timestamp'])),
                'is_sent' => $message['sender_id'] == $current_user_id
            ];
        }
    }

    header('Content-Type: application/json');
    echo json_encode($formatted_messages);

} catch (Exception $e) {
    error_log("Error in get_messages.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([]); // Return empty array instead of error
}
?>