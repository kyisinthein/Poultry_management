<?php
// sidebar.php

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get current user data
$user = fetchOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);

$current_farm_id = $_GET['farm_id'] ?? $_SESSION['current_farm_id'] ?? 1;

// Get current farm data with error handling
$current_farm = fetchOne("SELECT * FROM farms WHERE id = ?", [$current_farm_id]);

// If farm doesn't exist, use default farm 1
if (!$current_farm) {
    $current_farm_id = 1;
    $_SESSION['current_farm_id'] = 1;
    $current_farm = fetchOne("SELECT * FROM farms WHERE id = ?", [1]);
    
    // If farm 1 doesn't exist, create default values
    if (!$current_farm) {
        $current_farm = [
            'farm_username' => 'အောင်စိုးမင်း',
            'farm_no' => 1
        ];
    }
}

// Store current farm in session
$_SESSION['current_farm_id'] = $current_farm_id;

// Get current page number from URL for active state
$current_page_number = $_GET['page'] ?? 1;

// Get current file name for navigation highlighting
$current_file = basename($_SERVER['PHP_SELF']);
?>

<!-- sidebar.php -->
<div class="sidebar">
  <div class="sidebar-header">
    <button class="back-btn" onclick="goBackToDashboard()">
      <i class="fas fa-arrow-left"></i>
    </button>
    <div class="user-profile">
      <div class="avatar">
        <?php 
          // Get first character of farm username for avatar
          echo mb_substr($current_farm['farm_username'] ?? 'အ', 0, 1, 'UTF-8');
        ?>
      </div>
      <div class="user-info">
        <span class="user-name"><?php echo htmlspecialchars($current_farm['farm_username'] ?? 'အောင်စိုးမင်း'); ?></span>
       
      </div>
      <i class="fa-solid fa-arrow-right"></i> <span class="user-name">ခြံ (<?php echo htmlspecialchars($current_farm['farm_no'] ?? 'ခြံ'); ?>)</span>
      
    </div>
  </div>

  <nav class="sidebar-nav">
  <a href="./dashboard.php" class="nav-item">
      <i class="fas fa-home"></i>
      <span>ပင်မစာမျက်နှာ</span>
    </a>
    <a href="summary.php?farm_id=<?php echo $current_farm_id; ?>&page=<?php echo $current_page_number; ?>" class="nav-item <?php echo $current_file == 'summary.php' ? 'active' : ''; ?>" data-page="summary">
      <i class="fas fa-chart-bar"></i>
      <span><?php echo htmlspecialchars($current_farm['farm_username']?? 'အောင်စိုးမင်း'); ?></span>
    </a>
    <a href="food.php?farm_id=<?php echo $current_farm_id; ?>&page=<?php echo $current_page_number; ?>" class="nav-item <?php echo $current_file == 'food.php' ? 'active' : ''; ?>" data-page="food">
      <i class="fas fa-utensils"></i>
      <span>အစာစာရင်းချုပ်</span>
    </a>
    <a href="sell.php?farm_id=<?php echo $current_farm_id; ?>&page=<?php echo $current_page_number; ?>" class="nav-item <?php echo $current_file == 'sell.php' ? 'active' : ''; ?>" data-page="sales">
      <i class="fas fa-list-alt"></i>
      <span>အရောင်းစာရင်းချုပ်</span>
    </a>
    <a href="medicine.php?farm_id=<?php echo $current_farm_id; ?>&page=<?php echo $current_page_number; ?>" class="nav-item <?php echo $current_file == 'medicine.php' ? 'active' : ''; ?>" data-page="medicine">
      <i class="fas fa-plus"></i>
      <span>ဆေးစာရင်းချုပ်</span>
    </a>
    <a href="grand-total.php?farm_id=<?php echo $current_farm_id; ?>&page=<?php echo $current_page_number; ?>" class="nav-item <?php echo $current_file == 'grand-total.php' ? 'active' : ''; ?>" data-page="grand-total">
      <i class="fas fa-calculator"></i>
      <span>Grand Total</span>
    </a>
    
    <!-- User Management (Only for Admin) -->
    <?php if ($user['role'] === 'admin'): ?>
    <a href="usermanagement.php" class="nav-item <?php echo $current_file == 'user_management.php' ? 'active' : ''; ?>" data-page="users">
      <i class="fas fa-users"></i>
      <span>အသုံးပြုသူ စီမံခန့်ခွဲမှု</span>
    </a>
    <?php endif; ?>
    
    <!-- Logout -->
    <a href="logout.php" class="nav-item logout-btn">
      <i class="fas fa-sign-out-alt"></i>
      <span>ထွက်ရန်</span>
    </a>
  </nav>
</div>

<script>
function goBackToDashboard() {
  window.location.href = 'dashboard.php';
}
</script>

