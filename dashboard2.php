<?php
require_once 'database.php';



// Checking if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Getting current user data
$user = fetchOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);

// Getting all users (for admin)
$users = [];
if ($_SESSION['role'] === 'admin') {
    $users = fetchAll("SELECT id, username, role, profile_pic, is_active, created_at, last_activity FROM users");
}

// Function to get total unread count for dashboard
function getTotalUnreadCount($userId) {
  try {
      // Get group chat unread count
      $groupUnread = fetchOne("
          SELECT COUNT(*) as count FROM messages 
          WHERE (receiver_id = 'all' OR receiver_id IS NULL) 
          AND sender_id != ? 
          AND is_read = 0
      ", [$userId]);
      
      // Get private chat unread count
      $privateUnread = fetchOne("
          SELECT COUNT(*) as count FROM messages 
          WHERE receiver_id = ? 
          AND sender_id != ? 
          AND is_read = 0
      ", [$userId, $userId]);
      
      return ($groupUnread['count'] ?? 0) + ($privateUnread['count'] ?? 0);
      
  } catch (Exception $e) {
      error_log("Error getting unread count: " . $e->getMessage());
      return 0;
  }
}

// Get initial unread count
$totalUnread = getTotalUnreadCount($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="my">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Polutry Dashboard</title>
  <link rel="stylesheet" href="./assets/css/dashboard.css">
</head>
<body>
<?php include('navbar.php'); ?>
  <div class="container">
    <!-- Search bar -->
    <div class="search-bar">
      <input type="text" placeholder="ရှာဖွေရန်...">
      <button><i class="fa fa-search"></i></button>
    </div>

    <div class="grid2">
      <a href="chat.php"> 
        <div class="card">
          <div class="leftbox">
            <span class="left2"><img src="./assets/images/message.png" alt=""></span>
          </div>
          <span class="text">စကားပြောရန်</span>
          <span class="badge" id="headerNotification" style="<?php echo $totalUnread > 0 ? '' : 'display: none;' ?>">
            <?php echo $totalUnread > 99 ? '99+' : $totalUnread; ?>
          </span>
        </div>
      </a>
      <a href="./DownloadDashboard.php">
      <div class="card">
        <div class="leftbox">
          <span class="left2"><img src="./assets/images/download.png" alt=""></span>
        </div>
        <span class="text">စာရင်းများDownloadရယူရန်</span>
        <!-- <span class="badge">1</span> -->
      </div>
      </a>
      
     <a href="./history.php">
      <div class="card">
        <div class="leftbox">
          <span class="left2"><img src="./assets/images/history.png" alt=""></span>
        </div>
        <span class="text">ပြီးစီးခဲ့သည့်လုပ်ဆောင်ချက်များ</span>
        <!-- <span class="badge">1</span> -->
      </div></a>
    </div>
  </div>

  <script>
  // Dashboard specific notification update
  function updateDashboardNotification() {
      fetch('get_total_unread.php')
      .then(response => response.json())
      .then(data => {
          const badge = document.getElementById('headerNotification');
          if (badge) {
              if (data.total_unread > 0) {
                  badge.textContent = data.total_unread > 99 ? '99+' : data.total_unread;
                  badge.style.display = 'flex';
              } else {
                  badge.style.display = 'none';
              }
          }
      })
      .catch(error => {
          console.error('Error updating dashboard notification:', error);
      });
  }

  // Update badge every 5 seconds
  setInterval(updateDashboardNotification, 5000);

  // Initial update
  document.addEventListener('DOMContentLoaded', function() {
      updateDashboardNotification();
  });
  </script>
  <script src="./assets/js/dashboard.js"></script>
</body>
</html>