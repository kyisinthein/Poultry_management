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
$link_prefix = (preg_match('#/Poultry_management/(Summary|Feed|Medicine)/#', $_SERVER['PHP_SELF'])) ? '../' : '';
?>

<!-- sidebar.php -->
<div class="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-title">
      <span class="farm-name"><?php echo htmlspecialchars($current_farm['farm_username'] ?? 'အောင်စိုးမင်း'); ?></span>
      <span class="farm-no">ခြံ (<?php echo htmlspecialchars($current_farm['farm_no'] ?? 1); ?>)</span>
    </div>
    <button class="toggle-sidebar" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <i class="fas fa-bars"></i>
    </button>
  </div>

  <nav class="sidebar-nav">
    <a href="<?php echo $link_prefix; ?>dashboard.php" class="nav-item">
      <i class="fas fa-home"></i>
      <span>ပင်မစာမျက်နှာ</span>
    </a>
    <a href="<?php echo $link_prefix; ?>Summary/summary.php?farm_id=<?php echo $current_farm_id; ?>&page=<?php echo $current_page_number; ?>" class="nav-item <?php echo $current_file == 'summary.php' ? 'active' : ''; ?>" data-page="summary">
      <i class="fas fa-chart-bar"></i>
      <span><?php echo htmlspecialchars($current_farm['farm_username'] ?? 'အောင်စိုးမင်း'); ?></span>
    </a>
    <a href="<?php echo $link_prefix; ?>Feed/feed.php?farm_id=<?php echo $current_farm_id; ?>&page=<?php echo $current_page_number; ?>" class="nav-item <?php echo $current_file == 'feed.php' ? 'active' : ''; ?>" data-page="food">
      <i class="fas fa-utensils"></i>
      <span>အစာစာရင်းချုပ်</span>
    </a>
    <a href="<?php echo $link_prefix; ?>sell.php?farm_id=<?php echo $current_farm_id; ?>&page=<?php echo $current_page_number; ?>" class="nav-item <?php echo $current_file == 'sell.php' ? 'active' : ''; ?>" data-page="sales">
      <i class="fas fa-list-alt"></i>
      <span>အရောင်းစာရင်းချုပ်</span>
    </a>
    <a href="<?php echo $link_prefix; ?>Medicine/medicine.php?farm_id=<?php echo $current_farm_id; ?>&page=<?php echo $current_page_number; ?>" class="nav-item <?php echo $current_file == 'medicine.php' ? 'active' : ''; ?>" data-page="medicine">
      <i class="fas fa-plus"></i>
      <span>ဆေးစာရင်းချုပ်</span>
    </a>
    <a href="<?php echo $link_prefix; ?>grand-total.php?farm_id=<?php echo $current_farm_id; ?>&page=<?php echo $current_page_number; ?>" class="nav-item <?php echo $current_file == 'grand-total.php' ? 'active' : ''; ?>" data-page="grand-total">
      <i class="fas fa-calculator"></i>
      <span>Grand Total</span>
    </a>

    <!-- User Management (Only for Admin) -->
    <?php if ($user['role'] === 'admin'): ?>
      <a href="<?php echo $link_prefix; ?>usermanagement.php" class="nav-item <?php echo $current_file == 'user_management.php' ? 'active' : ''; ?>" data-page="users">
        <i class="fas fa-users"></i>
        <span>အသုံးပြုသူ စီမံခန့်ခွဲမှု</span>
      </a>
    <?php endif; ?>

    <!-- Logout -->
    <a href="<?php echo $link_prefix; ?>logout.php" class="nav-item logout-btn">
      <i class="fas fa-sign-out-alt"></i>
      <span>ထွက်ရန်</span>
    </a>
  </nav>
</div>

<script>
  function toggleSidebar() {
    const el = document.querySelector('.sidebar');
    const collapsed = el.classList.toggle('collapsed');
    try {
      localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0');
    } catch (e) {}
  }

  (() => {
    try {
      const stored = localStorage.getItem('sidebarCollapsed');
      if (stored === '1') {
        document.querySelector('.sidebar').classList.add('collapsed');
      }
    } catch (e) {}
  })();
</script>