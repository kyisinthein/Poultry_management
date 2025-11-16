<?php
require_once 'database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Validate session data
if (!isset($_SESSION['username']) || !isset($_SESSION['role'])) {
    // Session corrupted, redirect to login
    session_destroy();
    header("Location: login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$current_username = $_SESSION['username'];
$current_role = $_SESSION['role'];

// Initialize variables
$user = [];
$users = [];

try {
    // Get current user data
    $user = fetchOne("SELECT * FROM users WHERE id = ?", [$current_user_id]);
    
    if (!$user) {
        // User not found in database, logout
        session_destroy();
        header("Location: login.php");
        exit();
    }

    // Check if user is active
    if (!$user['is_active']) {
        session_destroy();
        header("Location: login.php?error=account_deactivated");
        exit();
    }

  // Get users based on role
if ($current_role === 'admin') {
    // Admin can see all users with detailed info
    $users = fetchAll("SELECT id, username, role, profile_pic, is_active, created_at, last_activity FROM users WHERE id != ? ORDER BY role, username", [$current_user_id]);
} else {
    // Regular users can see other active users (with is_active field)
    $users = fetchAll("SELECT id, username, role, profile_pic, is_active, last_activity FROM users WHERE id != ? AND is_active = TRUE ORDER BY role, username", [$current_user_id]);
}

} catch (Exception $e) {
    // Log the error (in production, use proper logging)
    error_log("Database error: " . $e->getMessage());
    $error = "System error occurred. Please try again.";
}

// Function to format time in Burmese
function formatBurmeseTime($datetime) {
    if (empty($datetime) || $datetime == '0000-00-00 00:00:00') {
        return 'မရှိပါ';
    }
    
    $time = strtotime($datetime);
    if ($time === false) {
        return 'မရှိပါ';
    }
    
    $hour = date('H', $time);
    
    if ($hour < 12) {
        return date('g:i', $time) . ' မနက်';
    } else {
        return date('g:i', $time) . ' ညနေ';
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>chat_poultrymanagement</title>
    <link rel="stylesheet" href="./assets/css/style.css">
    <link rel="stylesheet" href="./assets/css/dashboard.css">
    <audio id="notificationSound" preload="auto">
        <source src="./assets/sounds/notification.wav" type="audio/wav">
        <source src="./assets/sounds/notification.mp3" type="audio/mpeg">
    </audio>
</audio>
</head>
<body>
     <?php include('navbar.php'); ?>
    <div class="chat-container">
        <div class="chat-layout">
            <!-- Users List -->
            <div class="users-list">
    <h3>Online Users</h3>


    <div class="user-item active" data-userid="all">
    <div class="group-chat-design">
        <div class="avatar-stack">
            <?php 
            // Display first 3 users' profile pictures
            $displayUsers = array_slice($users, 0, 3);
            foreach ($displayUsers as $index => $user): ?>
                <div class="avatar-item" style="z-index: <?php echo 10 - $index; ?>;">
                    <img src="./assets/profile_pics/<?php echo !empty($user['profile_pic']) ? $user['profile_pic'] : 'images/default-avatar.png'; ?>" 
                         alt="<?php echo $user['username']; ?>" 
                         class="avatar-image">
                </div>
            <?php endforeach; ?>
            
            <?php if (count($users) > 3): ?>
                <div class="avatar-more">+<?php echo count($users) - 3; ?></div>
            <?php endif; ?>
        </div>
        <div class="group-info">
            <div class="group-title">
                Group Chat
                <span class="group-notification-badge" style="display: none;"></span>
            </div>
            <div class="group-subtitle">All Members</div>
        </div>
    </div>
</div>

    
    <?php foreach ($users as $user): ?>
        <div class="user-item" data-userid="<?php echo $user['id']; ?>">
            <?php echo $user['username']; ?>
            <img src="./assets/profile_pics/<?php echo !empty($user['profile_pic']) ? $user['profile_pic'] : 'assets/default-profile.jpg'; ?>" 
                     alt="Profile" class="profile-image">
                <div class="online-indicator <?php echo $user['is_active'] ? 'online' : ''; ?>"></div>
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
                        <button type="button" id="fileBtn" class="btn"><img src="./assets/images/attach.png" alt=""></button>
                        <button type="submit" class="btn send-btn">ပေးပို့မည်</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<button class="scroll-to-bottom" id="scrollToBottom" title="Scroll to bottom" style="display: none;">
    <span>↓</span>
</button>
    <script src="./assets/js/chat.js"></script>
</body>
</html>