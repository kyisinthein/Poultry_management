<?php
include 'database.php';

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
    
    // If farm 1 doesn't exist, create default values
    if (!$current_farm) {
        $current_farm = [
            'farm_username' => 'အောင်စိုးမင်း',
            'farm_no' => 1
        ];
    }
}

// Handle add new table
if (isset($_POST['add_new_table'])) {
  try {
      $new_page = getNextPaginationPageNumber();
      ensurePaginationPageTypes($new_page, $current_farm_id);
      
      $_SESSION['success'] = "ဇယားအသစ်ထပ်ယူပြီးပါပြီ။ စာမျက်နှာအသစ်: " . $new_page . " ကို ဖိုင်အားလုံးအတွက်ဖန်တီးပြီးပါပြီ။";
      header("Location: food.php?page=" . $new_page . "&farm_id=" . $current_farm_id);
      exit();
      
  } catch (PDOException $e) {
      $_SESSION['error'] = "Error creating new table: " . $e->getMessage();
      header("Location: food.php?farm_id=" . $current_farm_id);
      exit();
  }
}

// Handle add new row - AUTO SET page_number
if (isset($_POST['add_new_row'])) {
  try {
      $current_page = $_POST['page'] ?? 1;
      
      // First, let's create the food_summary table if it doesn't exist
      $create_table_sql = "
      CREATE TABLE IF NOT EXISTS food_summary (
          id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
          date DATE NOT NULL,
          food_type VARCHAR(100) DEFAULT NULL,
          food_amount DECIMAL(10,2) DEFAULT NULL,
          food_price DECIMAL(10,2) DEFAULT NULL,
          total_cost DECIMAL(12,2) DEFAULT NULL,
          remaining_food DECIMAL(10,2) DEFAULT NULL,
          consumption_rate DECIMAL(5,2) DEFAULT NULL,
          comments TEXT DEFAULT NULL,
          page_number INT(11) NOT NULL DEFAULT 1,
          farm_id INT(11) NOT NULL DEFAULT 1,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      )";
      executeQuery($create_table_sql);
      
      // Insert a new empty row with the current page number and farm_id
      $insert_sql = "INSERT INTO food_summary (date, page_number, farm_id) VALUES (CURDATE(), ?, ?)";
      executeQuery($insert_sql, [$current_page, $current_farm_id]);
      
      $_SESSION['success'] = "rowအသစ်ထပ်ယူပြီးပါပြီ။ (စာမျက်နှာ: $current_page)";
      header("Location: food.php?page=" . $current_page . "&farm_id=" . $current_farm_id);
      exit();
      
  } catch (PDOException $e) {
      $_SESSION['error'] = "Error adding new row: " . $e->getMessage();
      header("Location: food.php?page=" . ($_POST['page'] ?? 1) . "&farm_id=" . $current_farm_id);
      exit();
  }
}

// Get current page from URL or default to 1
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;

// Validate that the page exists in pagination table for this farm
try {
    $page_check_sql = "SELECT id FROM pagination WHERE page_type = 'food' AND page_number = ? AND farm_id = ?";
    $page_exists = fetchOne($page_check_sql, [$current_page, $current_farm_id]);
    
    if (!$page_exists) {
        $_SESSION['error'] = "စာမျက်နှာ $current_page မတွေ့ပါ။ စာမျက်နှာ ၁ သို့ပြန်သွားပါမည်။";
        header("Location: food.php?page=1&farm_id=" . $current_farm_id);
        exit();
    }
    
    // Check if food_summary table exists, if not create it
    $table_check = fetchOne("SHOW TABLES LIKE 'food_summary'");
    if (!$table_check) {
        $create_table_sql = "
        CREATE TABLE food_summary (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            date DATE NOT NULL,
            food_type VARCHAR(100) DEFAULT NULL,
            food_amount DECIMAL(10,2) DEFAULT NULL,
            food_price DECIMAL(10,2) DEFAULT NULL,
            total_cost DECIMAL(12,2) DEFAULT NULL,
            remaining_food DECIMAL(10,2) DEFAULT NULL,
            consumption_rate DECIMAL(5,2) DEFAULT NULL,
            comments TEXT DEFAULT NULL,
            page_number INT(11) NOT NULL DEFAULT 1,
            farm_id INT(11) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        executeQuery($create_table_sql);
    }
    
    // Get food data for current page and farm
    $food_sql = "SELECT * FROM food_summary WHERE page_number = ? AND farm_id = ? ORDER BY date DESC, id DESC";
    $food_data = fetchAll($food_sql, [$current_page, $current_farm_id]);
    
    // Get all available pages for pagination for this farm
    $pages_sql = "SELECT DISTINCT page_number FROM pagination WHERE page_type = 'food' AND farm_id = ? ORDER BY page_number";
    $pages_result = fetchAll($pages_sql, [$current_farm_id]);
    $all_pages = array_column($pages_result, 'page_number');
    
} catch (PDOException $e) {
    $food_data = [];
    $all_pages = [1];
    $_SESSION['error'] = "Error loading data: " . $e->getMessage();
}

// Set variables for pagination.php
$current_data = $food_data;
// $current_page and $all_pages are already set above
?>


<!DOCTYPE html>
<html lang="my">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>အစာစာရင်းချုပ် - <?php echo htmlspecialchars($current_farm['farm_username'] ?? 'Default Farm'); ?> - ခြံ(<?php echo $current_farm['farm_no'] ?? 1; ?>) - စာမျက်နှာ <?php echo $current_page; ?></title>
  <link rel="stylesheet" href="./assets/css/sell.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    .page-indicator {
      background: #28a745;
      color: white;
      padding: 5px 10px;
      border-radius: 4px;
      font-weight: bold;
      margin-left: 10px;
    }
    .available-pages {
      margin: 10px 0;
      padding: 10px;
      background: #f8f9fa;
      border-radius: 4px;
    }
    .alert {
      padding: 10px;
      margin: 10px 0;
      border-radius: 4px;
    }
    .alert-success {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }
    .alert-error {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
    .farm-info {
      background: #f8f9fa;
      padding: 10px;
      border-radius: 5px;
      margin-bottom: 15px;
      border-left: 4px solid #4CAF50;
    }
  </style>
</head>
<body>

  <div class="sell_container">
    <!-- Include Sidebar -->
    <?php include('sidebar.php'); ?>

    <!-- Main Content -->
    <div class="main-content">
      <div class="content-header">
        <div class="farm-info">
          <h2><?php echo htmlspecialchars($current_farm['farm_username'] ?? 'Default Farm'); ?> - ခြံ(<?php echo $current_farm['farm_no'] ?? 1; ?>)</h2>
        </div>
        <h1>
          အစာစာရင်းချုပ် 
          <span class="page-indicator">စာမျက်နှာ <?php echo $current_page; ?></span>
        </h1>
        
        <!-- Show available pages for debugging -->
        <div class="available-pages">
          <strong>ရနိုင်သောစာမျက်နှာများ:</strong> 
          <?php echo implode(', ', $all_pages); ?>
          <br>
          <small>ဒီစာမျက်နှာများကို sell.php, food.php, medicine.php ဖိုင်အားလုံးမှာမြင်ရမည်</small>
        </div>
        
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <div class="header-actions">
          <form method="POST" style="display: inline;">
            <input type="hidden" name="page" value="<?php echo $current_page; ?>">
            <input type="hidden" name="farm_id" value="<?php echo $current_farm_id; ?>">
            <button type="submit" name="add_new_row" class="btn btn-primary">
              <i class="fas fa-plus"></i>
              rowအသစ်ထပ်ယူရန် (စာမျက်နှာ <?php echo $current_page; ?>)
            </button>
          </form>
          
          <form method="POST" style="display: inline;">
            <input type="hidden" name="farm_id" value="<?php echo $current_farm_id; ?>">
            <button type="submit" name="add_new_table" class="btn btn-primary">
              <i class="fas fa-plus"></i>
              ဇယားအသစ်ထပ်ယူရန် (ဖိုင်အားလုံးအတွက်)
            </button>
          </form>
          
          <button class="btn btn-secondary" id="saveAllData">
            <i class="fas fa-save"></i>
            Save-all (စာမျက်နှာ <?php echo $current_page; ?>)
          </button>
          <button class="btn btn-danger" id="deleteAllData">
            <i class="fas fa-trash"></i>
            Delete-all (စာမျက်နှာ <?php echo $current_page; ?>)
          </button>
        </div>
      </div>

      <!-- Search and Filter Section -->
      <div class="search-section">
        <div class="search-container">
          <div class="search-group">
            <label for="startDate">စတင်ရက်စွဲ</label>
            <input type="date" id="startDate" class="date-input" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
          </div>
          <div class="search-group">
            <label for="endDate">ပြီးဆုံးရက်စွဲ</label>
            <input type="date" id="endDate" class="date-input" value="<?php echo date('Y-m-d'); ?>">
          </div>
          <button class="btn btn-search" id="searchBtn">
            <i class="fas fa-search"></i>
            ရှာဖွေရန်
          </button>
          <button class="btn btn-clear" id="clearBtn">
            <i class="fas fa-redo"></i>
            ရှင်းလင်းရန်
          </button>
        </div>
      </div>

      <div class="table-container">
        <?php include 'pagination.php'; ?>
        <form id="foodForm" method="POST" action="update_food.php">
          <input type="hidden" name="page" value="<?php echo $current_page; ?>">
          <input type="hidden" name="farm_id" value="<?php echo $current_farm_id; ?>">
          <input type="hidden" name="action" value="bulk_update">
          
          <table>
            <thead>
              <tr><th colspan="10">အစာစာရင်းချုပ် - <?php echo htmlspecialchars($current_farm['farm_username'] ?? 'Default Farm'); ?> - ခြံ(<?php echo $current_farm['farm_no'] ?? 1; ?>) - စာမျက်နှာ <?php echo $current_page; ?></th></tr>
              <tr>
                <th>ရက်စွဲ</th>
                <th>အစာအမျိုးအစား</th>
                <th>အစာပမာဏ (kg)</th>
                <th>စျေးနှုန်း (တစ်ယူနစ်)</th>
                <th>စုစုပေါင်းကုန်ကျ</th>
                <th>ကျန်အစာ (kg)</th>
                <th>စားသုံးမှုနှုန်း (%)</th>
                <th>မှတ်ချက်</th>
                <th>ပြုပြင်ရန်</th>
                <th>ဖျက်ရန်</th>
              </tr>
            </thead>
            <tbody id="foodTableBody">
              <?php if (count($food_data) > 0): ?>
                <?php foreach ($food_data as $row): ?>
                  <tr data-id="<?php echo $row['id']; ?>">
                    <td><input type="date" name="date[<?php echo $row['id']; ?>]" value="<?php echo $row['date']; ?>"></td>
                    <td>
                      <select name="food_type[<?php echo $row['id']; ?>]">
                        <option value="">ရွေးချယ်ပါ</option>
                        <option value="ကြက်ကလေး အစာ" <?php echo ($row['food_type'] == 'ကြက်ကလေး အစာ') ? 'selected' : ''; ?>>ကြက်ကလေး အစာ</option>
                        <option value="ကြက်ကြီး အစာ" <?php echo ($row['food_type'] == 'ကြက်ကြီး အစာ') ? 'selected' : ''; ?>>ကြက်ကြီး အစာ</option>
                        <option value="အထူးအစာ" <?php echo ($row['food_type'] == 'အထူးအစာ') ? 'selected' : ''; ?>>အထူးအစာ</option>
                        <option value="ဗီတာမင်အစာ" <?php echo ($row['food_type'] == 'ဗီတာမင်အစာ') ? 'selected' : ''; ?>>ဗီတာမင်အစာ</option>
                      </select>
                    </td>
                    <td><input type="number" step="0.01" name="food_amount[<?php echo $row['id']; ?>]" value="<?php echo $row['food_amount'] ?? ''; ?>" placeholder="kg"></td>
                    <td><input type="number" step="0.01" name="food_price[<?php echo $row['id']; ?>]" value="<?php echo $row['food_price'] ?? ''; ?>" placeholder="စျေးနှုန်"></td>
                    <td><input type="number" step="0.01" name="total_cost[<?php echo $row['id']; ?>]" value="<?php echo $row['total_cost'] ?? ''; ?>" placeholder="စုစုပေါင်းကုန်ကျ"></td>
                    <td><input type="number" step="0.01" name="remaining_food[<?php echo $row['id']; ?>]" value="<?php echo $row['remaining_food'] ?? ''; ?>" placeholder="ကျန်အစာ"></td>
                    <td><input type="number" step="0.01" name="consumption_rate[<?php echo $row['id']; ?>]" value="<?php echo $row['consumption_rate'] ?? ''; ?>" placeholder="%"></td>
                    <td><input type="text" name="comments[<?php echo $row['id']; ?>]" value="<?php echo htmlspecialchars($row['comments'] ?? ''); ?>" placeholder="မှတ်ချက်"></td>
                    <td>
                      <button type="button" class="btn-edit" onclick="updateFoodRow(<?php echo $row['id']; ?>)">ပြုပြင်</button>
                    </td>
                    <td>
                      <button type="button" class="btn-delete" onclick="deleteFoodRow(<?php echo $row['id']; ?>)">ဖျက်ရန်</button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="10" style="text-align: center; padding: 20px;">
                    စာမျက်နှာ <?php echo $current_page; ?> တွင် ဒေတာမရှိပါ။ "rowအသစ်ထပ်ယူရန်" ကိုနှိပ်ပြီး အသစ်ထည့်ပါ။
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </form>
      </div>

      
    </div>
  </div>

  <script>
    // Get current farm ID and page for JavaScript
    const currentFarmId = <?php echo $current_farm_id; ?>;
    const currentPage = <?php echo $current_page; ?>;

    document.addEventListener('DOMContentLoaded', function() {
      // Search functionality
      const searchBtn = document.getElementById('searchBtn');
      const clearBtn = document.getElementById('clearBtn');
      const startDate = document.getElementById('startDate');
      const endDate = document.getElementById('endDate');

      searchBtn.addEventListener('click', function() {
        const start = startDate.value;
        const end = endDate.value;
        
        if (start && end) {
          filterFoodData(start, end);
        } else {
          alert('ကျေးဇူးပြု၍ ရက်စွဲနှစ်ခုလုံးထည့်ပါ');
        }
      });

      clearBtn.addEventListener('click', function() {
        startDate.value = '<?php echo date('Y-m-d', strtotime('-30 days')); ?>';
        endDate.value = '<?php echo date('Y-m-d'); ?>';
        window.location.href = 'food.php?page=' + currentPage + '&farm_id=' + currentFarmId;
      });

      // Save all data
      document.getElementById('saveAllData').addEventListener('click', function() {
        document.getElementById('foodForm').submit();
      });

      // Delete all data
      document.getElementById('deleteAllData').addEventListener('click', function() {
        if (confirm('စာမျက်နှာ ' + currentPage + ' ရှိ စာရင်းအားလုံးကို ဖျက်မှာသေချာပါသလား?')) {
          deleteAllFoodData();
        }
      });
    });

    function filterFoodData(startDate, endDate) {
      const formData = new FormData();
      formData.append('start_date', startDate);
      formData.append('end_date', endDate);
      formData.append('page', currentPage);
      formData.append('farm_id', currentFarmId);

      fetch('filter_food.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.text())
      .then(data => {
        document.getElementById('foodTableBody').innerHTML = data;
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Filtering failed');
      });
    }

    function updateFoodRow(rowId) {
      const formData = new FormData(document.getElementById('foodForm'));
      formData.append('action', 'update');
      formData.append('row_id', rowId);
      formData.append('farm_id', currentFarmId);

      fetch('update_food.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert('ပြုပြင်ပြီးပါပြီ။ (စာမျက်နှာ ' + currentPage + ')');
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Update failed');
      });
    }

    function deleteFoodRow(rowId) {
      if (!confirm('ဒီ row ကိုဖျက်မှာသေချာပါသလား?')) return;

      const formData = new FormData();
      formData.append('action', 'delete');
      formData.append('row_id', rowId);
      formData.append('page', currentPage);
      formData.append('farm_id', currentFarmId);

      fetch('update_food.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert('ဖျက်ပြီးပါပြီ။ (စာမျက်နှာ ' + currentPage + ')');
          window.location.reload();
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Delete failed');
      });
    }

    function deleteAllFoodData() {
      const formData = new FormData();
      formData.append('action', 'delete_all');
      formData.append('page', currentPage);
      formData.append('farm_id', currentFarmId);

      fetch('update_food.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert('စာမျက်နှာ ' + currentPage + ' ရှိ အားလုံးဖျက်ပြီးပါပြီ။');
          window.location.reload();
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Delete all failed');
      });
    }
  </script>
</body>
</html>
