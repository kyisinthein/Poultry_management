<?php 
include('database.php'); 

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get current user data
$user = fetchOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);

// Get current farm ID
$current_farm_id = $_GET['farm_id'] ?? $_SESSION['current_farm_id'] ?? 1;

// Store in session
$_SESSION['current_farm_id'] = $current_farm_id;

// Get current farm data with error handling
$current_farm = fetchOne("SELECT * FROM farms WHERE id = ?", [$current_farm_id]);

// If farm doesn't exist, use default farm 1
if (!$current_farm) {
    $current_farm_id = 1;
    $_SESSION['current_farm_id'] = 1;
    $current_farm = fetchOne("SELECT * FROM farms WHERE id = ?", [1]);
    
    // If farm 1 doesn't exist either, create a default farm
    if (!$current_farm) {
        // Create default farm if it doesn't exist
        executeQuery("INSERT INTO farms (farm_no, farm_username) VALUES (1, 'အောင်စိုးမင်း')");
        $current_farm = fetchOne("SELECT * FROM farms WHERE id = ?", [1]);
    }
}

// Get current page from URL or default to 1
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;

// PAGINATION LOGIC - Get global page mapping
try {
    // Get all available pages from database FOR CURRENT FARM ONLY
    $pages_sql = "SELECT DISTINCT page_number FROM pagination WHERE farm_id = ? ORDER BY page_number ASC";
    $pages_result = fetchAll($pages_sql, [$current_farm_id]);
    $global_pages = array_column($pages_result, 'page_number');
    
    // Create display mapping: display_page => global_page
    $page_mapping = [];
    $display_pages = [];
    
    foreach ($global_pages as $index => $global_page) {
        $display_page = $index + 1;
        $page_mapping[$display_page] = $global_page;
        $display_pages[] = $display_page;
    }
    
    // If no pages exist for this farm, create page 1
    if (empty($global_pages)) {
        // Get next global page number
        $max_page_sql = "SELECT MAX(page_number) as max_page FROM pagination";
        $max_page_result = fetchOne($max_page_sql);
        $new_global_page = ($max_page_result['max_page'] ?: 0) + 1;
        
        $page_types = ['summary', 'food', 'sales', 'medicine', 'grand-total'];
        foreach ($page_types as $type) {
            $insert_sql = "INSERT INTO pagination (page_number, page_type, farm_id) VALUES (?, ?, ?)";
            executeQuery($insert_sql, [$new_global_page, $type, $current_farm_id]);
        }
        
        $global_pages = [$new_global_page];
        $page_mapping = [1 => $new_global_page];
        $display_pages = [1];
    }
    
    // Convert current page from display to global
    $current_global_page = isset($page_mapping[$current_page]) ? $page_mapping[$current_page] : $page_mapping[1];
    
} catch (Exception $e) {
    error_log("Pagination error: " . $e->getMessage());
    $global_pages = [1];
    $page_mapping = [1 => 1];
    $display_pages = [1];
    $current_global_page = 1;
}

$total_pages = count($display_pages);

// Store mapping in session for use in other parts of the application
$_SESSION['page_mapping'][$current_farm_id] = $page_mapping;

// Handle add new table
if (isset($_POST['add_new_table'])) {
  try {
      $submitted_farm_id = $_POST['farm_id'] ?? $current_farm_id;
      
      // Find the next available page number that doesn't conflict globally
      $max_page_sql = "SELECT MAX(page_number) as max_page FROM pagination";
      $max_page_result = fetchOne($max_page_sql);
      $new_page = ($max_page_result['max_page'] ?: 0) + 1;
      
      // Insert new pagination record for ALL page types for this farm
      $page_types = ['summary', 'food', 'sales', 'medicine', 'grand-total'];
      $success_count = 0;
      
      foreach ($page_types as $type) {
          try {
              $insert_sql = "INSERT INTO pagination (page_number, page_type, farm_id) VALUES (?, ?, ?)";
              executeQuery($insert_sql, [$new_page, $type, $submitted_farm_id]);
              $success_count++;
          } catch (PDOException $e) {
              // If duplicate entry, try the next page number
              if (strpos($e->getMessage(), '1062') !== false) {
                  $new_page++;
                  $insert_sql = "INSERT INTO pagination (page_number, page_type, farm_id) VALUES (?, ?, ?)";
                  executeQuery($insert_sql, [$new_page, $type, $submitted_farm_id]);
                  $success_count++;
              } else {
                  throw $e; // Re-throw if it's not a duplicate error
              }
          }
      }
      
      if ($success_count > 0) {
        // Log the new page action
        logHistory($user['id'], $submitted_farm_id, $new_page, 'INSERT', 'pagination', $new_page, null, null, "ဇယားအသစ်ထပ်ယူခြင်း - ခြံ $submitted_farm_id - စာမျက်နှာ $new_page");
          $_SESSION['success'] = "ဇယားအသစ်ထပ်ယူပြီးပါပြီ။ စာမျက်နှာအသစ်: " . $new_page . " ကို ဖိုင်အားလုံးအတွက်ဖန်တီးပြီးပါပြီ။";
      } else {
          $_SESSION['error'] = "ဇယားအသစ်ထပ်ယူရန် မအောင်မြင်ပါ။";
      }
      
      header("Location: sell.php?page=" . $new_page . "&farm_id=" . $submitted_farm_id);
      exit();
      
  } catch (PDOException $e) {
      $_SESSION['error'] = "Error creating new table: " . $e->getMessage();
      header("Location: sell.php?farm_id=" . $current_farm_id);
      exit();
  }
}

// Handle delete current page - FIXED VERSION
if (isset($_POST['delete_current_page'])) {
    try {
        $current_page_to_delete = $_POST['page'] ?? 1;
        $farm_id_to_delete = $_POST['farm_id'] ?? $current_farm_id;
        $global_page_to_delete = $_POST['global_page'] ?? $current_global_page;
        
        // Don't allow deleting page 1
        if ($current_page_to_delete == 1) {
            $_SESSION['error'] = "စာမျက်နှာ ၁ ကိုဖျက်လို့မရပါ။";
            header("Location: sell.php?page=" . $current_page_to_delete . "&farm_id=" . $farm_id_to_delete);
            exit();
        }
        
        // Use the provided global page number
        if (!$global_page_to_delete) {
            $_SESSION['error'] = "စာမျက်နှာရှာမတွေ့ပါ။";
            header("Location: sell.php?page=1&farm_id=" . $farm_id_to_delete);
            exit();
        }
        
        // Delete from pagination table for all page types for this farm AND global page
        $page_types = ['summary', 'food', 'sales', 'medicine', 'grand-total'];
        $deleted_count = 0;
        
        foreach ($page_types as $type) {
            $delete_sql = "DELETE FROM pagination WHERE page_type = ? AND page_number = ? AND farm_id = ?";
            $stmt = $pdo->prepare($delete_sql);
            $stmt->execute([$type, $global_page_to_delete, $farm_id_to_delete]);
            $deleted_count += $stmt->rowCount();
        }
        
        // Delete sales data for this global page and farm
        $delete_data_sql = "DELETE FROM sales_summary WHERE page_number = ? AND farm_id = ?";
        executeQuery($delete_data_sql, [$global_page_to_delete, $farm_id_to_delete]);
        
        if ($deleted_count > 0) {
            $_SESSION['success'] = "စာမျက်နှာ " . $current_page_to_delete . " ကိုဖျက်ပြီးပါပြီ။ စာမျက်နှာ ၁ သို့ပြန်သွားပါမည်။";
        } else {
            $_SESSION['error'] = "စာမျက်နှာဖျက်ရန် မအောင်မြင်ပါ။";
        }
        
        header("Location: sell.php?page=1&farm_id=" . $farm_id_to_delete);
        exit();
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting page: " . $e->getMessage();
        header("Location: sell.php?page=" . ($_POST['page'] ?? 1) . "&farm_id=" . ($_POST['farm_id'] ?? $current_farm_id));
        exit();
    }
}

// Show success/error messages
if (isset($_SESSION['success'])) {
    echo '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-error">' . $_SESSION['error'] . '</div>';
    unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html lang="my">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>အရောင်းစာရင်းချုပ် - <?php echo htmlspecialchars($current_farm['farm_username'] ?? 'Default Farm'); ?> - ခြံ(<?php echo $current_farm['farm_no'] ?? 1; ?>)</title>
  <link href="https://fonts.cdnfonts.com/css/noto-serif-myanmar" rel="stylesheet">
  <link rel="stylesheet" href="./assets/css/sell.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

  <div class="sell_container">
    <!-- Sidebar -->
    <?php include('sidebar.php'); ?>

    <!-- Main Content -->
    <div class="main-content">
      <div class="content-header">
        <h1>အရောင်းစာရင်းချုပ် - စာမျက်နှာ <?php echo $current_page; ?></h1>
        <div class="header-actions">
          <button class="btn btn-primary">
            <i class="fas fa-plus"></i>
            rowအသစ်ထပ်ယူရန်
          </button>
          
          <!-- Add New Table Button -->
          <form method="POST" style="display: inline;">
            <input type="hidden" name="farm_id" value="<?php echo $current_farm_id; ?>">
            <button type="submit" name="add_new_table" class="btn btn-primary">
              <i class="fas fa-plus"></i>
              ဇယားအသစ်ထပ်ယူရန်
            </button>
          </form>
          
          <!-- Delete Current Page Button (Only show if not page 1) -->
          <?php if ($current_page > 1): ?>
          <form method="POST" style="display: inline;" onsubmit="return confirmDeletePage()">
            <input type="hidden" name="page" value="<?php echo $current_page; ?>">
            <input type="hidden" name="farm_id" value="<?php echo $current_farm_id; ?>">
            <input type="hidden" name="global_page" value="<?php echo $current_global_page; ?>">
            <button type="submit" name="delete_current_page" class="btn btn-delete-page">
              <i class="fas fa-trash"></i>
              ဒီစာမျက်နှာကိုဖျက်ရန် (စာမျက်နှာ <?php echo $current_page; ?>)
            </button>
          </form>
          <?php endif; ?>
          
          <button class="btn btn-secondary" id="saveAllData" data-farm-id="<?php echo $current_farm_id; ?>" data-page="<?php echo $current_page; ?>">
            <i class="fas fa-save"></i>
            Save-all
          </button>
          <button class="btn btn-secondary" id="downloadExcel">
            <i class="fas fa-file-excel"></i>
            Excel Download
          </button>
          <button class="btn btn-danger" id="deleteAllData">
            <i class="fas fa-trash"></i>
            Delete-all (ဒေတာအားလုံး)
          </button>
        </div>
      </div>

      <!-- Search and Filter Section -->
      <div class="search-section">
        <div class="search-container">
            <!-- User Information Bar -->
            <div class="user-info-bar">
              <span>လက်ရှိအသုံးပြုသူ: <strong><?php echo htmlspecialchars($user['username']); ?></strong></span><br>
              <span>အဆင့်: <strong><?php echo htmlspecialchars($user['role']); ?></strong></span>
            </div>
            <div class="search-group">
              <label for="startDate">စတင်ရက်စွဲ</label>
              <input type="date" id="startDate" class="date-input">
            </div>
            <div class="search-group">
              <label for="endDate">ပြီးဆုံးရက်စွဲ</label>
              <input type="date" id="endDate" class="date-input">
            </div>
            <button class="btn btn-search">
              <i class="fas fa-search"></i>
              ရှာဖွေရန်
            </button>
            <button class="btn btn-clear">
              <i class="fas fa-redo"></i>
              ရှင်းလင်းရန်
            </button>
        </div>
      </div>

      <div class="table-container">
        <?php include('pagination.php'); ?>
        <table>
          <thead>
            <tr><th style="padding-top: 25px;" colspan="20">အရောင်းစာရင်းချုပ် - <?php echo htmlspecialchars($current_farm['farm_username'] ?? 'Default Farm'); ?> - ခြံ(<?php echo $current_farm['farm_no'] ?? 1; ?>) - စာမျက်နှာ <?php echo $current_page; ?></th></tr>
            <tr>
              <th colspan="2" class="editable-header" data-field="chicken_type">ကြက်အမျိုးအစား CP</th>
              <th colspan="2">စာရင်းရှိ</th>
              <th colspan="3" class="editable-header" data-field="initial_count">
                <?php
                // Get initial count for this page and farm
                try {
                  $initial_count_sql = "SELECT initial_count FROM sales_summary WHERE page_number = ? AND farm_id = ? ORDER BY date ASC LIMIT 1";
                  $initial_count_result = fetchOne($initial_count_sql, [$current_global_page, $current_farm_id]);
                  echo $initial_count_result ? $initial_count_result['initial_count'] : '0';
                } catch (Exception $e) {
                  echo '0';
                }
                ?>
              </th>
              <th colspan="6">အသေ</th>
              <th>စုစုပေါင်းရောင်းကောင်</th>
              <th></th>
              <th class="editable-header" data-field="current_count">
                <?php
                // Get current count for this page and farm
                try {
                  $current_count_sql = "SELECT current_count FROM sales_summary WHERE page_number = ? AND farm_id = ? ORDER BY date DESC LIMIT 1";
                  $current_count_result = fetchOne($current_count_sql, [$current_global_page, $current_farm_id]);
                  echo $current_count_result ? $current_count_result['current_count'] : '0';
                } catch (Exception $e) {
                  echo '0';
                }
                ?>
              </th>
              <th></th>
              <th></th>
              <th></th>
              <th></th>
            </tr>
            <tr>
              <th data-field="date">ရက်စွဲ</th>
              <th data-field="sold_count">ရောင်းကောင်</th>
              <th data-field="weight_per_chicken">အလေးချိန်</th>
              <th data-field="total_sold_count">စုစုပေါင်းရောင်းကောင်</th>
              <th data-field="total_weight">စုစုပေါင်းအလေးချိန်</th>
              <th data-field="daily_weight">အလေးချိန်</th>
              <th data-field="dead_count">အသေပေါင်း</th>
              <th data-field="mortality_rate">အသေရာခိုင်နှုန်း</th>
              <th data-field="cumulative_sold_count">စုစုပေါင်းရောင်းကောင်</th>
              <th data-field="surplus_deficit">အပို/အလို</th>
              <th data-field="weight_21to30">21to30</th>
              <th data-field="weight_31to36">31to36</th>
              <th data-field="weight_37to_end">37toend</th>
              <th data-field="total_chicken_weight">ကြက်သားအလေးချိန်စုစုပေါင်း</th>
              <th data-field="total_feed_consumption_rate">စုစုပေါင်းအစာစားနှုန်း</th>
              <th data-field="total_feed_weight">ကြက်စာအလေးချိန်စုစုပေါင်း</th>
              <th data-field="final_weight">အလေးချိန်</th>
              <th data-field="fcr">FCR</th>
              <th>ပြုပြင်ရန်</th>
              <th data-field="comments">မှတ်ချက်ပေးရန်</th>
            </tr>
          </thead>
          <tbody id="salesTableBody">
            <?php
            // Fetch sales data for current page and farm
            try {
              $sales_sql = "SELECT s.*, u.username as comment_author 
              FROM sales_summary s 
              LEFT JOIN users u ON s.comment_author_id = u.id 
              WHERE s.page_number = ? AND s.farm_id = ? 
              ORDER BY s.date ASC";
              $sales_data = fetchAll($sales_sql, [$current_global_page, $current_farm_id]);
              
              if ($sales_data && count($sales_data) > 0) {
                foreach ($sales_data as $row) {
                  echo '<tr data-id="' . $row['id'] . '">';
                  echo '<td class="editable" data-field="date">' . ($row['date'] ?: '') . '</td>';
                  echo '<td class="editable" data-field="sold_count">' . ($row['sold_count'] ?: '0') . '</td>';
                  echo '<td class="editable" data-field="weight_per_chicken">' . ($row['weight_per_chicken'] ?: '0') . '</td>';
                  echo '<td class="editable" data-field="total_sold_count">' . ($row['total_sold_count'] ?: '0') . '</td>';
                  echo '<td class="editable" data-field="total_weight">' . ($row['total_weight'] ?: '0') . '</td>';
                  echo '<td class="editable" data-field="daily_weight">' . ($row['daily_weight'] ?: '0') . '</td>';
                  echo '<td class="editable" data-field="dead_count">' . ($row['dead_count'] ?: '0') . '</td>';
                  echo '<td class="editable" data-field="mortality_rate">' . ($row['mortality_rate'] ?: '0') . '</td>';
                  echo '<td class="editable" data-field="cumulative_sold_count">' . ($row['cumulative_sold_count'] ?: '0') . '</td>';
                  echo '<td class="editable" data-field="surplus_deficit">' . ($row['surplus_deficit'] ?: '0') . '</td>';
                  echo '<td class="editable" data-field="weight_21to30">' . ($row['weight_21to30'] ?: '0') . '</td>';
                  echo '<td class="editable" data-field="weight_31to36">' . ($row['weight_31to36'] ?: '0') . '</td>';
                  echo '<td class="editable" data-field="weight_37to_end">' . ($row['weight_37to_end'] ?: '0') . '</td>';
                  echo '<td class="editable" data-field="total_chicken_weight">' . ($row['total_chicken_weight'] ?: '0') . '</td>';
                  echo '<td class="editable" data-field="total_feed_consumption_rate">' . ($row['total_feed_consumption_rate'] ?: '0') . '</td>';
                  echo '<td class="editable" data-field="total_feed_weight">' . ($row['total_feed_weight'] ?: '0') . '</td>';
                  echo '<td class="editable" data-field="final_weight">' . ($row['final_weight'] ?: '0') . '</td>';
                  echo '<td class="editable" data-field="fcr">' . ($row['fcr'] ?: '0') . '</td>';
                echo '<td>
                  <button class="save-btn saved">သိမ်းပြီး</button>
                  <button class="btn-edit"><i class="fas fa-edit"></i></button>
                  <button class="btn-delete" data-id="' . $row['id'] . '"><i class="fas fa-trash"></i></button>
                </td>';
         // Comment column with badge
echo '<td class="comment-cell">';
echo '<div class="comment-container">';

// Get comment author name if exists
$comment_author_name = $row['comment_author'] ?? '';
if (empty($comment_author_name) && $row['comment_author_id']) {
    $author_sql = "SELECT username FROM users WHERE id = ?";
    $author_result = fetchOne($author_sql, [$row['comment_author_id']]);
    $comment_author_name = $author_result ? $author_result['username'] : '';
}

// Comment icon with badge
$badge_class = '';
$has_comment_value = $row['has_comment'] ? '1' : '0';
$comment_read_value = $row['comment_read'] ? '1' : '0';

if ($row['has_comment']) {
  if ($user['role'] === 'admin') {
    // Admin sees all comments with blue badge
    $badge_class = 'badge-comment-admin';
  } else {
    // User sees red badge for unread, green for read
    $badge_class = $row['comment_read'] ? 'badge-comment-read' : 'badge-comment-unread';
  }
}

echo '<button class="btn-comment" data-id="' . $row['id'] . '" 
        data-has-comment="' . $has_comment_value . '"
        data-comment-read="' . $comment_read_value . '"
        data-current-comment="' . htmlspecialchars($row['comments'] ?? '') . '"
        data-comment-author="' . htmlspecialchars($comment_author_name) . '"
        data-comment-date="' . ($row['comment_created_at'] ?? '') . '">';
echo '<i class="fa-regular fa-comment"></i>';
if ($row['has_comment']) {
  echo '<span class="comment-badge ' . $badge_class . '"></span>';
}
echo '</button>';

echo '</div>';
echo '</td>';
                
                echo '</tr>';
                }
              } else {
                echo '<tr><td colspan="20" style="text-align: center; padding: 20px;">ဒီစာမျက်နှာအတွက် ဒေတာမရှိပါ။ အသစ်ထည့်ရန် "rowအသစ်ထပ်ယူရန်" ကိုနှိပ်ပါ။</td></tr>';
              }
            } catch (Exception $e) {
              echo '<tr><td colspan="20" style="text-align: center; color: red;">Error loading data: ' . $e->getMessage() . '</td></tr>';
            }
            ?>
          </tbody>
        </table>
      </div>

      
    </div>
  </div>



<!-- Comment Modal -->
<div id="commentModal" class="comment-modal">
  <div class="comment-modal-content">
    <div class="comment-modal-header">
      <div class="comment-header-left">
        <span class="comment-modal-title" id="commentModalTitle">မှတ်ချက်ပေးရန်</span>
        <div id="commentInfo" class="comment-info"></div>
      </div>
      <button class="comment-modal-close">&times;</button>
    </div>
    <div class="comment-modal-body">
      <textarea id="commentText" class="comment-textarea" placeholder="မှတ်ချက်ရေးရန်..."></textarea>
    </div>
    <div class="comment-modal-footer">
      <button type="button" id="btnCommentSave" class="btn-comment-save">သိမ်းရန်</button>
      <button type="button" id="btnCommentMarkRead" class="btn-comment-mark-read" style="display: none;">ဖတ်ပြီးပါပြီ</button>
      <button type="button" id="btnCommentCancel" class="btn-comment-cancel">မလုပ်တော့ပါ</button>
    </div>
  </div>
</div>



<script>
// Pass PHP user data to JavaScript
window.currentUser = {
    id: <?php echo $user['id']; ?>,
    role: '<?php echo $user['role']; ?>',
    username: '<?php echo $user['username']; ?>'
};

// Get current farm ID for JavaScript
const currentFarmId = <?php echo $current_farm_id; ?>;
const currentPage = <?php echo $current_page; ?>;
const currentGlobalPage = <?php echo $current_global_page; ?>;
const totalPages = <?php echo $total_pages; ?>;
</script>


  <script>
    // Get current farm/page for JavaScript
    const currentFarmId = <?php echo $current_farm_id; ?>;
    const currentPage = <?php echo $current_page; ?>;
    const currentGlobalPage = window.currentGlobalPage || currentPage;
    const totalPages = <?php echo $total_pages; ?>;

    // Fixed Save-all functionality
    document.getElementById('saveAllData').addEventListener('click', function() {
        // Check if we're on the last page
        if (currentPage === totalPages) {
            // Warn user but don't auto-create new page
            if (!confirm(`သင်သည် နောက်ဆုံးစာမျက်နှာ (${currentPage}) တွင် ဒေတာသိမ်းနေပါသည်။\n\nဒေတာအသစ်ထည့်လိုပါက "ဇယားအသစ်ထပ်ယူရန်" ကိုအရင်နှိပ်ပါ။\n\nလက်ရှိစာမျက်နှာအတွက်သာ ဒေတာသိမ်းမည်။\n\nဆက်လုပ်မည်လား?`)) {
                return false;
            }
        }
        
        // Collect all data from the table (include new rows without data-id)
        const salesData = [];
        const rows = document.querySelectorAll('#salesTableBody tr');
        
        rows.forEach(row => {
            const rowData = {
                id: row.getAttribute('data-id'),
                date: row.querySelector('[data-field="date"]')?.textContent || '',
                sold_count: row.querySelector('[data-field="sold_count"]')?.textContent || '0',
                weight_per_chicken: row.querySelector('[data-field="weight_per_chicken"]')?.textContent || '0',
                total_sold_count: row.querySelector('[data-field="total_sold_count"]')?.textContent || '0',
                total_weight: row.querySelector('[data-field="total_weight"]')?.textContent || '0',
                daily_weight: row.querySelector('[data-field="daily_weight"]')?.textContent || '0',
                dead_count: row.querySelector('[data-field="dead_count"]')?.textContent || '0',
                mortality_rate: row.querySelector('[data-field="mortality_rate"]')?.textContent || '0',
                cumulative_sold_count: row.querySelector('[data-field="cumulative_sold_count"]')?.textContent || '0',
                surplus_deficit: row.querySelector('[data-field="surplus_deficit"]')?.textContent || '0',
                weight_21to30: row.querySelector('[data-field="weight_21to30"]')?.textContent || '0',
                weight_31to36: row.querySelector('[data-field="weight_31to36"]')?.textContent || '0',
                weight_37to_end: row.querySelector('[data-field="weight_37to_end"]')?.textContent || '0',
                total_chicken_weight: row.querySelector('[data-field="total_chicken_weight"]')?.textContent || '0',
                total_feed_consumption_rate: row.querySelector('[data-field="total_feed_consumption_rate"]')?.textContent || '0',
                total_feed_weight: row.querySelector('[data-field="total_feed_weight"]')?.textContent || '0',
                final_weight: row.querySelector('[data-field="final_weight"]')?.textContent || '0',
                fcr: row.querySelector('[data-field="fcr"]')?.textContent || '0',
                comments: row.querySelector('[data-field="comments"]')?.textContent || ''
            };
            salesData.push(rowData);
        });
        
        // Send data to server (bulk endpoint)
        fetch('save_bulk_sales.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                farm_id: currentFarmId,
                page_number: currentGlobalPage,
                sales: salesData
            })
        })
        .then(async response => {
            const text = await response.text();
            let data = null;
            try { data = JSON.parse(text); } catch (e) {}

            if (!response.ok) {
                const message = data && data.error ? data.error : (text || `HTTP ${response.status}`);
                throw new Error(message);
            }

            if (!data || !data.success) {
                const message = data && data.error ? data.error : 'Unknown error';
                throw new Error(message);
            }

            alert(`✅ ဒေတာများအောင်မြင်စွာသိမ်းဆည်းပြီးပါပြီ။\nသိမ်းဆည်းထားသောအရေအတွက်: ${data.total_processed ?? salesData.length}\nစာမျက်နှာ: ${currentPage}`);
            
            // Reload the page to reflect changes, but stay on same page
            setTimeout(() => {
                window.location.href = `sell.php?page=${currentPage}&farm_id=${currentFarmId}`;
            }, 800);
        })
        .catch(error => {
            console.error('Save-all error:', error);
            alert('❌ ဒေတာသိမ်းဆည်းရာတွင် အမှားတစ်ခုရှိပါသည်: ' + error.message);
        });
    });

    // Update confirm delete function
    function confirmDeletePage() {
        const currentPage = <?php echo $current_page; ?>;
        const currentFarmId = <?php echo $current_farm_id; ?>;
        
        return confirm(`⚠️ သတိပြုပါ!\n\nစာမျက်နှာ ${currentPage} ကိုဖျက်မှာသေချာပါသလား?\n\nဒီစာမျက်နှာကို ဖိုင်အားလုံးမှ (sell.php, food.php, medicine.php) ဖျက်သွားမည်။\n\nဒေတာများအားလုံးပျက်သွားမည်။ ဆက်လုပ်မှာသေချာပါသလား?`);
    }

    // Simple JavaScript for pagination and search functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Search functionality
        const searchBtn = document.querySelector('.btn-search');
        const clearBtn = document.querySelector('.btn-clear');
        const startDate = document.getElementById('startDate');
        const endDate = document.getElementById('endDate');

        searchBtn.addEventListener('click', function() {
            const start = startDate.value;
            const end = endDate.value;
            
            if (start && end) {
                // Include farm_id in search
                window.location.href = `sell.php?page=${currentPage}&farm_id=${currentFarmId}&start_date=${start}&end_date=${end}`;
            } else {
                alert('ကျေးဇူးပြု၍ ရက်စွဲနှစ်ခုလုံးထည့်ပါ');
            }
        });

        clearBtn.addEventListener('click', function() {
            startDate.value = '';
            endDate.value = '';
            // Reset to current page and farm
            window.location.href = `sell.php?page=${currentPage}&farm_id=${currentFarmId}`;
        });

        const downloadExcelBtn = document.getElementById('downloadExcel');
        if (downloadExcelBtn) {
            downloadExcelBtn.addEventListener('click', function() {
                const start = startDate.value;
                const end = endDate.value;
                const query = start && end ? `&start_date=${encodeURIComponent(start)}&end_date=${encodeURIComponent(end)}` : '';
                window.location.href = `download_excel_sell.php?page=${currentPage}&farm_id=${currentFarmId}${query}`;
            });
        }

        // Update pagination links to include farm_id
        const pageLinks = document.querySelectorAll('.page-btn');
        pageLinks.forEach(link => {
            if (link.href) {
                const url = new URL(link.href);
                url.searchParams.set('farm_id', currentFarmId);
                link.href = url.toString();
            }
        });
    });
  </script>

  <script src="./assets/js/sales_manager.js"></script>

  <script>// Comment System JavaScript
document.addEventListener('DOMContentLoaded', function() {
  const commentModal = document.getElementById('commentModal');
  const commentText = document.getElementById('commentText');
  const commentModalTitle = document.getElementById('commentModalTitle');
  const commentInfo = document.getElementById('commentInfo');
  const btnCommentSave = document.getElementById('btnCommentSave');
  const btnCommentMarkRead = document.getElementById('btnCommentMarkRead');
  const btnCommentCancel = document.getElementById('btnCommentCancel');
  const closeBtn = document.querySelector('.comment-modal-close');
  
  let currentCommentRowId = null;
  const currentUserRole = '<?php echo $user["role"]; ?>';
  const currentUserId = <?php echo $user["id"]; ?>;

  // Open comment modal
  document.addEventListener('click', function(e) {
    if (e.target.closest('.btn-comment')) {
      const btn = e.target.closest('.btn-comment');
      currentCommentRowId = btn.getAttribute('data-id');
      const hasComment = btn.getAttribute('data-has-comment') === '1';
      const commentRead = btn.getAttribute('data-comment-read') === '1';
      const currentComment = btn.getAttribute('data-current-comment') || '';
      const commentAuthor = btn.getAttribute('data-comment-author');
      const commentDate = btn.getAttribute('data-comment-date');
      
      openCommentModal(currentCommentRowId, hasComment, commentRead, currentComment, commentAuthor, commentDate);
    }
  });

  function openCommentModal(rowId, hasComment, commentRead, currentComment, commentAuthor, commentDate) {
    currentCommentRowId = rowId;
    commentText.value = currentComment;
    
    if (currentUserRole === 'admin') {
      // Admin mode - can edit any comment
      commentModalTitle.textContent = 'မှတ်ချက်ပေးရန်';
      commentText.readOnly = false;
      btnCommentSave.style.display = 'inline-block';
      btnCommentMarkRead.style.display = 'none';
      
      if (hasComment) {
        commentInfo.textContent = `မှတ်ချက်ရေးထားသူ: ${commentAuthor} | ရက်စွဲ: ${formatDate(commentDate)}`;
        commentInfo.style.display = 'block';
      } else {
        commentInfo.style.display = 'none';
      }
    } else {
      // User mode - view only
      commentModalTitle.textContent = 'မှတ်ချက်ကြည့်ရန်';
      commentText.readOnly = true;
      btnCommentSave.style.display = 'none';
      
      if (hasComment) {
        commentInfo.textContent = `မှတ်ချက်ရေးထားသူ: ${commentAuthor} | ရက်စွဲ: ${formatDate(commentDate)}`;
        commentInfo.style.display = 'block';
        
        // Show "Mark as Read" button if comment is unread
        if (!commentRead) {
          btnCommentMarkRead.style.display = 'inline-block';
        } else {
          btnCommentMarkRead.style.display = 'none';
        }
      } else {
        commentInfo.textContent = 'မှတ်ချက်မရှိပါ';
        commentInfo.style.display = 'block';
        btnCommentMarkRead.style.display = 'none';
      }
    }
    
    commentModal.style.display = 'block';
  }

// Save comment (Admin only) - FIXED JSON VERSION
btnCommentSave.addEventListener('click', function() {
    console.log('Save comment clicked - currentCommentRowId:', currentCommentRowId);
    
    if (!currentCommentRowId) {
        console.error('No currentCommentRowId found!');
        alert('မှတ်ချက်သိမ်းရန် row ID မရှိပါ');
        return;
    }
    
    // Check if it's a temporary ID
    if (currentCommentRowId.startsWith('temp_')) {
        alert('ကျေးဇူးပြု၍ ဒီ row ကိုအရင် save လုပ်ပါ (Save-all ကိုနှိပ်ပါ)');
        return;
    }
    
    const comment = commentText.value.trim();
    
    console.log('Saving comment:', {
        row_id: currentCommentRowId,
        comment: comment,
        user_id: currentUserId,
        comment_length: comment.length
    });
    
    // Show loading state
    const originalText = btnCommentSave.textContent;
    btnCommentSave.innerHTML = '<i class="fas fa-spinner fa-spin"></i> သိမ်းဆည်းနေသည်...';
    btnCommentSave.disabled = true;
    
    fetch('save_comment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            row_id: currentCommentRowId,
            comment: comment,
            user_id: currentUserId
        })
    })
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Invalid JSON response:', text);
                throw new Error('Invalid response from server: ' + text.substring(0, 100));
            }
        });
    })
    .then(result => {
        console.log('Save comment result:', result);
        
        if (result.success) {
            // Update the button badge
            const commentBtn = document.querySelector(`.btn-comment[data-id="${currentCommentRowId}"]`);
            console.log('Found comment button:', commentBtn);
            
            if (commentBtn) {
                commentBtn.setAttribute('data-has-comment', '1');
                commentBtn.setAttribute('data-comment-read', '0');
                commentBtn.setAttribute('data-current-comment', comment);
                commentBtn.setAttribute('data-comment-author', window.currentUser.username);
                commentBtn.setAttribute('data-comment-date', new Date().toISOString());
                
                // Update badge
                let badge = commentBtn.querySelector('.comment-badge');
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'comment-badge';
                    commentBtn.appendChild(badge);
                }
                badge.className = 'comment-badge badge-comment-admin';
                
                console.log('Updated comment button attributes');
            }
            
            alert('မှတ်ချက်သိမ်းဆည်းပြီးပါပြီ။');
            commentModal.style.display = 'none';
            
        } else {
            alert('မှတ်ချက်သိမ်းဆည်းရာတွင် အမှားတစ်ခုရှိပါသည်: ' + (result.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error saving comment:', error);
        alert('မှတ်ချက်သိမ်းဆည်းရာတွင် အမှားတစ်ခုရှိပါသည်။ ' + error.message);
    })
    .finally(() => {
        // Restore button state
        btnCommentSave.textContent = originalText;
        btnCommentSave.disabled = false;
    });
});

  // Mark as read (User only)
  btnCommentMarkRead.addEventListener('click', function() {
    if (!currentCommentRowId) return;
    
    fetch('mark_comment_read.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        row_id: currentCommentRowId
      })
    })
    .then(response => response.json())
    .then(result => {
      if (result.success) {
        // Update the button badge
        const commentBtn = document.querySelector(`.btn-comment[data-id="${currentCommentRowId}"]`);
        if (commentBtn) {
          commentBtn.setAttribute('data-comment-read', '1');
          
          // Change badge to green
          const badge = commentBtn.querySelector('.comment-badge');
          if (badge) {
            badge.className = 'comment-badge badge-comment-read';
          }
        }
        
        btnCommentMarkRead.style.display = 'none';
        alert('မှတ်ချက်ဖတ်ပြီးကြောင်းအမှတ်အသားပြုလုပ်ပြီးပါပြီ။');
      } else {
        alert('အမှတ်အသားပြုလုပ်ရာတွင် အမှားတစ်ခုရှိပါသည်: ' + result.error);
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('အမှတ်အသားပြုလုပ်ရာတွင် အမှားတစ်ခုရှိပါသည်။');
    });
  });

  // Close modal
  function closeCommentModal() {
    commentModal.style.display = 'none';
    currentCommentRowId = null;
    commentText.value = '';
  }

  btnCommentCancel.addEventListener('click', closeCommentModal);
  closeBtn.addEventListener('click', closeCommentModal);

  // Close modal when clicking outside
  window.addEventListener('click', function(e) {
    if (e.target === commentModal) {
      closeCommentModal();
    }
  });

  // Utility function to format date
  function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('my-MM') + ' ' + date.toLocaleTimeString('my-MM', { 
      hour: '2-digit', 
      minute: '2-digit' 
    });
  }
});</script>
</body>
<script>
  (function(){
    const root = document.querySelector('.sell_container');
    const btn = document.getElementById('toggleFullscreen');
    if (!btn || !root) return;
    function setState(active){
      root.classList.toggle('fullscreen', active);
      document.body.classList.toggle('fullscreen-mode', active);
      const icon = btn.querySelector('i');
      if (active) {
        icon.classList.remove('fa-expand');
        icon.classList.add('fa-compress');
        btn.title = 'ပုံမှန်အရွယ်သို့ပြန်ရန်';
        try { localStorage.setItem('sellFullscreen', '1'); } catch(e) {}
      } else {
        icon.classList.remove('fa-compress');
        icon.classList.add('fa-expand');
        btn.title = 'ကြည့်ရန်ကျယ်';
        try { localStorage.setItem('sellFullscreen', '0'); } catch(e) {}
      }
    }
    btn.addEventListener('click', function(){
      const active = !root.classList.contains('fullscreen');
      setState(active);
    });
    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape' && root.classList.contains('fullscreen')) setState(false);
    });
    // Persist fullscreen across page navigation
    try {
      const stored = localStorage.getItem('sellFullscreen');
      if (stored === '1') setState(true);
    } catch(e) {}
  })();
</script>
</html>
