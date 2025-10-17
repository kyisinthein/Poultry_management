<?php
require_once 'database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$current_username = $_SESSION['username'];
$current_role = $_SESSION['role'];

// Get all users for the user list
$users = fetchAll("SELECT id, username, role FROM users WHERE id != ? AND is_active = TRUE ORDER BY role, username", [$current_user_id]);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Chat System</title>
    <link rel="stylesheet" href="./assets/css/style.css">
    <audio id="notificationSound" class="notification-sound">
    <source src="notification.mp3" type="audio/mpeg">
</audio>
</head>
<body>
    <div class="chat-container">
        <div class="chat-header">
            <h2>Chat System - <?php echo $current_username; ?> (<?php echo $current_role; ?>)</h2>
            <div class="header-actions">
                <?php if ($current_role == 'admin'): ?>
                    <a href="admin.php" class="btn">Manage Users</a>
                <?php endif; ?>
                <a href="logout.php" class="btn logout">Logout</a>
            </div>
        </div>
        
        <div class="chat-layout">
            <!-- Users List -->
            <div class="users-list">
    <h3>Online Users</h3>
    <div class="user-item active" data-userid="all">
        <strong>Group Chat (All Users)</strong>
    </div>
    <?php foreach ($users as $user): ?>
        <div class="user-item" data-userid="<?php echo $user['id']; ?>">
            <?php echo $user['username']; ?> (<?php echo $user['role']; ?>)
        </div>
    <?php endforeach; ?>
</div>
            
            <!-- Chat Area -->
            <div class="chat-area">
                <div class="messages-container" id="messagesContainer">
                    <!-- Messages will be loaded here -->
                </div>
                
                <div class="message-input">
                    <form id="messageForm" enctype="multipart/form-data">
                        <input type="hidden" id="receiverId" name="receiver_id" value="all">
                        <input type="text" id="message" name="message" placeholder="Type your message..." required>
                        <input type="file" id="file" name="file" style="display: none;">
                        <button type="button" id="fileBtn" class="btn">📎</button>
                        <button type="submit" class="btn send-btn">Send</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<button class="scroll-to-bottom" id="scrollToBottom" title="Scroll to bottom">↓</button>
    <script src="./assets/js/chat.js"></script>
</body>
</html>