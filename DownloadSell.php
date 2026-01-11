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

// DATA INITIALIZATION - If no sales data exists, create initial data
try {
    $check_data_sql = "SELECT COUNT(*) as data_count FROM sales_summary WHERE page_number = ? AND farm_id = ?";
    $data_count_result = fetchOne($check_data_sql, [$current_global_page, $current_farm_id]);
    $data_count = $data_count_result ? $data_count_result['data_count'] : 0;
    
    // If no data exists for this farm/page, create initial data
    if ($data_count == 0) {
        // Create initial sample data
        $initial_data = [
            'farm_id' => $current_farm_id,
            'page_number' => $current_global_page,
            'date' => date('Y-m-d'),
            'sold_count' => 0,
            'weight_per_chicken' => 0,
            'total_sold_count' => 0,
            'total_weight' => 0,
            'daily_weight' => 0,
            'dead_count' => 0,
            'mortality_rate' => 0,
            'cumulative_sold_count' => 0,
            'surplus_deficit' => 0,
            'weight_21to30' => 0,
            'weight_31to36' => 0,
            'weight_37to_end' => 0,
            'total_chicken_weight' => 0,
            'total_feed_consumption_rate' => 0,
            'total_feed_weight' => 0,
            'final_weight' => 0,
            'fcr' => 0,
            'chicken_type' => 'CP',
            'initial_count' => 4080,
            'current_count' => 3880
        ];
        
        $fields = implode(', ', array_keys($initial_data));
        $placeholders = implode(', ', array_fill(0, count($initial_data), '?'));
        $values = array_values($initial_data);
        
        $insert_sql = "INSERT INTO sales_summary ($fields) VALUES ($placeholders)";
        executeQuery($insert_sql, $values);
    }
} catch (Exception $e) {
    error_log("Data initialization error: " . $e->getMessage());
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
          <!-- Download Buttons -->
          <button class="btn btn-secondary save-btn" id="downloadExcel">
            <i class="fas fa-file-excel"></i>
            Download as Excel
          </button>
          <button class="btn btn-primary" id="downloadWord">
            <i class="fas fa-file-word"></i>
            Download as Word
          </button>
          <button class="btn btn-yellow" id="downloadImage">
            <i class="fas fa-file-image"></i>
            Download as Image
          </button>
          <!-- Print Button -->
          <button class="btn btn-secondary" id="printTable">
            <i class="fas fa-print"></i>
            Print Table
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
        <table id="salesTable">
          <thead>
            <tr><th colspan="19">အရောင်းစာရင်းချုပ် - <?php echo htmlspecialchars($current_farm['farm_username'] ?? 'Default Farm'); ?> - ခြံ(<?php echo $current_farm['farm_no'] ?? 1; ?>) - စာမျက်နှာ <?php echo $current_page; ?></th></tr>
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
              <th>မှတ်ချက်</th>
            </tr>
          </thead>
          <tbody id="salesTableBody">
            <?php
            // Get search dates if any
            $start_date = $_GET['start_date'] ?? null;
            $end_date = $_GET['end_date'] ?? null;
            
            // Fetch sales data for current page and farm
            try {
              $sales_sql = "SELECT s.*, u.username as comment_author 
              FROM sales_summary s 
              LEFT JOIN users u ON s.comment_author_id = u.id 
              WHERE s.page_number = ? AND s.farm_id = ?";
              
              $params = [$current_global_page, $current_farm_id];
              
              // Add date filter if search dates are provided
              if ($start_date && $end_date) {
                  $sales_sql .= " AND s.date BETWEEN ? AND ?";
                  $params[] = $start_date;
                  $params[] = $end_date;
              }
              
              $sales_sql .= " ORDER BY s.date ASC";
              
              $sales_data = fetchAll($sales_sql, $params);
              
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
                echo '<tr><td colspan="19" style="text-align: center; padding: 20px;">ဒီစာမျက်နှာအတွက် ဒေတာမရှိပါ။</td></tr>';
              }
            } catch (Exception $e) {
              echo '<tr><td colspan="19" style="text-align: center; color: red;">Error loading data: ' . $e->getMessage() . '</td></tr>';
            }
            ?>
          </tbody>
        </table>
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
const totalPages = <?php echo $total_pages; ?>;

// Get search parameters
const urlParams = new URLSearchParams(window.location.search);
const startDate = urlParams.get('start_date');
const endDate = urlParams.get('end_date');

// Download functionality
document.addEventListener('DOMContentLoaded', function() {
    // Download as Excel
    document.getElementById('downloadExcel').addEventListener('click', function() {
        downloadTable('excel');
    });

    // Download as Word
    document.getElementById('downloadWord').addEventListener('click', function() {
        downloadTable('word');
    });

    // Download as Image
    document.getElementById('downloadImage').addEventListener('click', function() {
        downloadTable('image');
    });

    // Print functionality
    document.getElementById('printTable').addEventListener('click', function() {
        printTable();
    });

    // Search functionality
    const searchBtn = document.querySelector('.btn-search');
    const clearBtn = document.querySelector('.btn-clear');
    const startDateInput = document.getElementById('startDate');
    const endDateInput = document.getElementById('endDate');

    // Set current search dates in inputs if they exist
    if (startDate) {
        startDateInput.value = startDate;
    }
    if (endDate) {
        endDateInput.value = endDate;
    }

    searchBtn.addEventListener('click', function() {
        const start = startDateInput.value;
        const end = endDateInput.value;
        
        if (start && end) {
            // Include farm_id in search
            window.location.href = `DownloadSell.php?page=${currentPage}&farm_id=${currentFarmId}&start_date=${start}&end_date=${end}`;
        } else {
            alert('ကျေးဇူးပြု၍ ရက်စွဲနှစ်ခုလုံးထည့်ပါ');
        }
    });

    clearBtn.addEventListener('click', function() {
        startDateInput.value = '';
        endDateInput.value = '';
        // Reset to current page and farm without date filters
        window.location.href = `DownloadSell.php?page=${currentPage}&farm_id=${currentFarmId}`;
    });

    // Update pagination links to include farm_id and search dates
    const pageLinks = document.querySelectorAll('.page-btn');
    pageLinks.forEach(link => {
        if (link.href) {
            const url = new URL(link.href);
            url.searchParams.set('farm_id', currentFarmId);
            // Preserve search dates in pagination
            if (startDate) {
                url.searchParams.set('start_date', startDate);
            }
            if (endDate) {
                url.searchParams.set('end_date', endDate);
            }
            link.href = url.toString();
        }
    });
});

function downloadTable(format) {
    const table = document.getElementById('salesTable');
    const farmName = '<?php echo htmlspecialchars($current_farm['farm_username'] ?? 'Default Farm'); ?>';
    const pageNumber = <?php echo $current_page; ?>;
    
    let fileName = `Sales_Summary_${farmName}_Page_${pageNumber}`;
    
    // Add search dates to filename if searching by date
    if (startDate && endDate) {
        fileName += `_${startDate}_to_${endDate}`;
    }
    
    switch(format) {
        case 'excel':
            // Download as Excel
            window.location.href = `download_excel_sell.php?farm_id=${currentFarmId}&page=${currentPage}&start_date=${startDate || ''}&end_date=${endDate || ''}`;
            break;
        case 'word':
            // Download as Word
            window.location.href = `download_word_sell.php?farm_id=${currentFarmId}&page=${currentPage}&start_date=${startDate || ''}&end_date=${endDate || ''}`;
            break;
        case 'image':
            // Download as Image using html2canvas
            if (typeof html2canvas !== 'undefined') {
                html2canvas(table).then(canvas => {
                    const link = document.createElement('a');
                    link.download = `${fileName}.png`;
                    link.href = canvas.toDataURL();
                    link.click();
                });
            } else {
                alert('Image download requires html2canvas library. Please include it in your project.');
            }
            break;
    }
}

function printTable() {
    const table = document.getElementById('salesTable');
    const farmName = '<?php echo htmlspecialchars($current_farm['farm_username'] ?? 'Default Farm'); ?>';
    const pageNumber = <?php echo $current_page; ?>;
    
    // Create a new window for printing
    const printWindow = window.open('', '_blank');
    
    // Get search dates if any
    const urlParams = new URLSearchParams(window.location.search);
    const startDate = urlParams.get('start_date');
    const endDate = urlParams.get('end_date');
    
    printWindow.document.write(`
        <html>
        <head>
            <title>Print Sales Summary</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .print-header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
                .print-header h1 { margin: 0; font-size: 18px; }
                .print-info { text-align: center; margin-bottom: 15px; font-size: 14px; color: #666; }
                table { width: 100%; border-collapse: collapse; font-size: 12px; }
                th, td { border: 1px solid #000; padding: 6px; text-align: center; }
                th { background-color: #f0f0f0; font-weight: bold; }
                .print-footer { margin-top: 20px; text-align: center; font-size: 10px; color: #666; border-top: 1px solid #000; padding-top: 10px; }
                @media print {
                    body { margin: 0; }
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="print-header">
                <h1>အရောင်းစာရင်းချုပ် - ${farmName} - ခြံ(<?php echo $current_farm['farm_no'] ?? 1; ?>)</h1>
            </div>
            <div class="print-info">
                စာမျက်နှာ: ${pageNumber}
                ${startDate && endDate ? `<br>ရက်စွဲ: ${startDate} မှ ${endDate} အထိ` : ''}
                <br>ပရင့်ထုတ်သည့်ရက်စွဲ: ${new Date().toLocaleDateString('my-MM')}
            </div>
    `);
    
    printWindow.document.write(table.outerHTML);
    
    printWindow.document.write(`
            <div class="print-footer">
                <?php echo htmlspecialchars($user['username']); ?> မှ ပရင့်ထုတ်သည်
            </div>
            <div class="no-print" style="margin-top: 20px; text-align: center;">
                <button onclick="window.print()" style="padding: 10px 20px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer;">Print Now</button>
                <button onclick="window.close()" style="padding: 10px 20px; background: #f44336; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;">Close</button>
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    
    // Auto-print after a short delay
    setTimeout(() => {
        printWindow.print();
    }, 500);
}
</script>
<script src="./assets/js/sales_manager.js"></script>
<!-- Include html2canvas for image download -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
</body>
</html>