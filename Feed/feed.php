<?php 
include('../database.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user = fetchOne('SELECT * FROM users WHERE id = ?', [$_SESSION['user_id']]);

$current_farm_id = $_GET['farm_id'] ?? $_SESSION['current_farm_id'] ?? 1;
$_SESSION['current_farm_id'] = $current_farm_id;

$current_farm = fetchOne('SELECT * FROM farms WHERE id = ?', [$current_farm_id]);
if (!$current_farm) {
    $current_farm_id = 1;
    $_SESSION['current_farm_id'] = 1;
    $current_farm = fetchOne('SELECT * FROM farms WHERE id = ?', [1]);
}

$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;

try {
    $pages_sql = 'SELECT DISTINCT page_number FROM pagination WHERE farm_id = ? ORDER BY page_number ASC';
    $pages_result = fetchAll($pages_sql, [$current_farm_id]);
    $global_pages = array_column($pages_result, 'page_number');

    $page_mapping = [];
    $display_pages = [];
    foreach ($global_pages as $index => $global_page) {
        $display_page = $index + 1;
        $page_mapping[$display_page] = $global_page;
        $display_pages[] = $display_page;
    }

    if (empty($global_pages)) {
        $max_page_sql = 'SELECT MAX(page_number) as max_page FROM pagination';
        $max_page_result = fetchOne($max_page_sql);
        $new_global_page = ($max_page_result['max_page'] ?: 0) + 1;
        $page_types = ['summary', 'food', 'sales', 'medicine', 'grand-total'];
        foreach ($page_types as $type) {
            executeQuery('INSERT INTO pagination (page_number, page_type, farm_id) VALUES (?, ?, ?)', [$new_global_page, $type, $current_farm_id]);
        }
        $global_pages = [$new_global_page];
        $page_mapping = [1 => $new_global_page];
        $display_pages = [1];
    }

    $current_global_page = isset($page_mapping[$current_page]) ? $page_mapping[$current_page] : $page_mapping[1];
} catch (Exception $e) {
    $global_pages = [1];
    $page_mapping = [1 => 1];
    $display_pages = [1];
    $current_global_page = 1;
}

$total_pages = count($display_pages);
$_SESSION['page_mapping'][$current_farm_id] = $page_mapping;

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
            $_SESSION['success'] = "ဇယားအသစ်ထပ်ယူပြီးပါပြီ။ စာမျက်နှာအသစ်: " . $new_page . " ကို ဖိုင်အားလုံးအတွက်ဖန်တီးပြီးပါပြီ။";
        } else {
            $_SESSION['error'] = "ဇယားအသစ်ထပ်ယူရန် မအောင်မြင်ပါ။";
        }

        header('Location: feed.php?page=' . $new_page . '&farm_id=' . $submitted_farm_id);
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error creating new table: ' . $e->getMessage();
        header('Location: feed.php?page=' . $current_page . '&farm_id=' . $current_farm_id);
        exit();
    }
}

// Handle delete current page (similar to sell.php)
if (isset($_POST['delete_current_page'])) {
    try {
        $current_page_to_delete = $_POST['page'] ?? 1;
        $farm_id_to_delete = $_POST['farm_id'] ?? $current_farm_id;
        $global_page_to_delete = $_POST['global_page'] ?? $current_global_page;

        if ($current_page_to_delete == 1) {
            $_SESSION['error'] = "စာမျက်နှာ ၁ ကိုဖျက်လို့မရပါ";
            header('Location: feed.php?page=' . $current_page_to_delete . '&farm_id=' . $farm_id_to_delete);
            exit();
        }

        if (!$global_page_to_delete) {
            $_SESSION['error'] = 'စာမျက်နှာရှာမတွေ့ပါ';
            header('Location: feed.php?page=1&farm_id=' . $farm_id_to_delete);
            exit();
        }

        $page_types = ['summary','food','sales','medicine','grand-total'];
        $deleted_count = 0;
        foreach ($page_types as $type) {
            $delete_sql = 'DELETE FROM pagination WHERE page_type = ? AND page_number = ? AND farm_id = ?';
            $stmt = $pdo->prepare($delete_sql);
            $stmt->execute([$type, $global_page_to_delete, $farm_id_to_delete]);
            $deleted_count += $stmt->rowCount();
        }

        $stmt2 = $pdo->prepare('DELETE FROM feed_summary WHERE page_number = ? AND farm_id = ?');
        $stmt2->execute([$global_page_to_delete, $farm_id_to_delete]);

        if ($deleted_count > 0) { $_SESSION['success'] = 'စာမျက်နှာ ' . $current_page_to_delete . ' ကိုဖျက်ပြီးပါပြီ'; }
        else { $_SESSION['error'] = 'စာမျက်နှာဖျက်ရန် မအောင်မြင်ပါ'; }

        header('Location: feed.php?page=1&farm_id=' . $farm_id_to_delete);
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error deleting page: ' . $e->getMessage();
        header('Location: feed.php?page=' . (isset($_POST['page']) ? intval($_POST['page']) : 1) . '&farm_id=' . $current_farm_id);
        exit();
    }
}

$table_check = fetchOne("SHOW TABLES LIKE 'feed_summary'");
if (!$table_check) {
    $create_sql = "
    CREATE TABLE IF NOT EXISTS feed_summary (
      id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
      date DATE NOT NULL,
      feed_category VARCHAR(120) DEFAULT NULL,
      feed_name VARCHAR(120) DEFAULT NULL,
      quantity DECIMAL(10,2) DEFAULT 0,
      unit_price DECIMAL(12,2) DEFAULT 0,
      total_cost DECIMAL(14,2) DEFAULT 0,
      comments TEXT DEFAULT NULL,
      has_comment TINYINT(1) NOT NULL DEFAULT 0,
      comment_read TINYINT(1) NOT NULL DEFAULT 0,
      comment_author_id INT(11) DEFAULT NULL,
      comment_created_at TIMESTAMP NULL DEFAULT NULL,
      page_number INT(11) NOT NULL DEFAULT 1,
      farm_id INT(11) NOT NULL DEFAULT 1,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    executeQuery($create_sql);
}

$remain_table_check = fetchOne("SHOW TABLES LIKE 'feed_remain'");
if (!$remain_table_check) {
    $create_remain_sql = "
    CREATE TABLE IF NOT EXISTS feed_remain (
      id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
      rem_cate VARCHAR(120) DEFAULT NULL,
      rem_name VARCHAR(120) DEFAULT NULL,
      rem_quan DECIMAL(10,2) DEFAULT 0,
      rem_price DECIMAL(12,2) DEFAULT 0,
      rem_total DECIMAL(14,2) DEFAULT 0,
      comments TEXT DEFAULT NULL,
      has_comment TINYINT(1) NOT NULL DEFAULT 0,
      comment_read TINYINT(1) NOT NULL DEFAULT 0,
      comment_author_id INT(11) DEFAULT NULL,
      comment_created_at TIMESTAMP NULL DEFAULT NULL,
      page_number INT(11) NOT NULL DEFAULT 1,
      farm_id INT(11) NOT NULL DEFAULT 1,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    executeQuery($create_remain_sql);
}

$checkCol = fetchOne("SHOW COLUMNS FROM feed_summary LIKE 'comments'");
if (!$checkCol) { executeQuery("ALTER TABLE feed_summary ADD COLUMN comments TEXT DEFAULT NULL"); }
$checkCol = fetchOne("SHOW COLUMNS FROM feed_summary LIKE 'has_comment'");
if (!$checkCol) { executeQuery("ALTER TABLE feed_summary ADD COLUMN has_comment TINYINT(1) NOT NULL DEFAULT 0"); }
$checkCol = fetchOne("SHOW COLUMNS FROM feed_summary LIKE 'comment_read'");
if (!$checkCol) { executeQuery("ALTER TABLE feed_summary ADD COLUMN comment_read TINYINT(1) NOT NULL DEFAULT 0"); }
$checkCol = fetchOne("SHOW COLUMNS FROM feed_summary LIKE 'comment_author_id'");
if (!$checkCol) { executeQuery("ALTER TABLE feed_summary ADD COLUMN comment_author_id INT(11) DEFAULT NULL"); }
$checkCol = fetchOne("SHOW COLUMNS FROM feed_summary LIKE 'comment_created_at'");
if (!$checkCol) { executeQuery("ALTER TABLE feed_summary ADD COLUMN comment_created_at TIMESTAMP NULL DEFAULT NULL"); }

$checkCol = fetchOne("SHOW COLUMNS FROM feed_remain LIKE 'total'");
if (!$checkCol) { executeQuery("ALTER TABLE feed_remain ADD COLUMN total DECIMAL(14,2) DEFAULT 0"); }
$checkCol = fetchOne("SHOW COLUMNS FROM feed_remain LIKE 'total_quan'");
if (!$checkCol) { executeQuery("ALTER TABLE feed_remain ADD COLUMN total_quan DECIMAL(10,2) DEFAULT 0"); }

$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;
$sql = 'SELECT * FROM feed_summary WHERE page_number = ? AND farm_id = ?';
$params = [$current_global_page, $current_farm_id];
if ($start_date && $end_date) {
  $sql .= ' AND date BETWEEN ? AND ?';
  $params[] = $start_date;
  $params[] = $end_date;
}
$sql .= ' ORDER BY date ASC';
$feed_data = fetchAll($sql, $params);

$remain_sql = 'SELECT * FROM feed_remain WHERE page_number = ? AND farm_id = ? ORDER BY id ASC';
$remain_params = [$current_global_page, $current_farm_id];
$remain_data = fetchAll($remain_sql, $remain_params);

function fmt($n){
  if ($n === null || $n === '') return '0';
  $s = (string)$n;
  if (strpos($s, '.') !== false) { $s = rtrim(rtrim($s, '0'), '.'); }
  return $s === '' ? '0' : $s;
}
?>
<!DOCTYPE html>
<html lang="my">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>အစာစာရင်းချုပ် - <?php echo htmlspecialchars($current_farm['farm_username'] ?? 'Default Farm'); ?> - ခြံ(<?php echo $current_farm['farm_no'] ?? 1; ?>)</title>
  <link rel="stylesheet" href="../assets/css/sell.css">
  <link rel="stylesheet" href="../assets/css/feed.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
      /* Force sticky header styles to avoid caching issues */
      .sell_container #feedTable {
        overflow: visible !important;
        border-radius: 15px !important;
        border-collapse: separate; 
        border-spacing: 0;
      }
      .sell_container #feedTable thead th {
        position: sticky !important;
        top: 75px !important; /* Adjust based on pagination height */
        z-index: 100 !important;
        background-color: #e0ddddff !important;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border-radius: 0 !important; /* Reset border radius for all */
        border-bottom: 2px solid #2563eb !important;
      }
      
      /* Apply border radius only to the first and last header cells */
      .sell_container #feedTable thead th:first-child {
        border-top-left-radius: 15px !important;
      }
      
      .sell_container #feedTable thead th:last-child {
        border-top-right-radius: 15px !important;
      }
      
      /* Apply border radius to bottom corners */
      .sell_container #feedTable tfoot tr:last-child th:first-child {
        border-bottom-left-radius: 15px !important;
      }
      .sell_container #feedTable tfoot tr:last-child th:last-child {
        border-bottom-right-radius: 15px !important;
      }
       
       /* Apply border radius to footer corners */
       .sell_container #feedTable tfoot tr:last-child th:first-child {
         border-bottom-left-radius: 15px !important;
       }
       .sell_container #feedTable tfoot tr:last-child th:last-child {
         border-bottom-right-radius: 15px !important;
       }

      /* Add spacing between tables */
      #feedRemainTable {
        margin-top: 50px;
      }
      
      #feedBagTotalTable {
        margin-top: 50px;
      }
      
      /* CRITICAL FIX: Allow main-content to be visible so sticky header sees viewport */
      .sell_container .main-content {
        overflow: visible !important;
      }
    </style>
</head>
<body>
  <div class="sell_container">
    <?php include('../sidebar.php'); ?>
    <div class="main-content">
      <div class="search-section">
        <div class="search-container">
            <div class="user-info-bar">
              <h1 style="font-size: 20px; margin: 0; color: var(--text-color);">အစာစာရင်းချုပ် - <?php echo htmlspecialchars($current_farm['farm_username'] ?? 'Default Farm'); ?> - ခြံ(<?php echo $current_farm['farm_no'] ?? 1; ?>) - စာမျက်နှာ <?php echo $current_page; ?></h1>
            </div>
            <div class="search-group">
              <label for="startDate">စတင်ရက်စွဲ</label>
              <input type="date" id="startDate" class="date-input" value="<?php echo htmlspecialchars($start_date ?? ''); ?>">
            </div>
            <div class="search-group">
              <label for="endDate">ပြီးဆုံးရက်စွဲ</label>
              <input type="date" id="endDate" class="date-input" value="<?php echo htmlspecialchars($end_date ?? ''); ?>">
            </div>
            <button class="btn btn-search" id="btnSearch">
              <i class="fas fa-search"></i>
              ရှာဖွေရန်
            </button>
            <button class="btn btn-clear" id="btnClear">
              <i class="fas fa-redo"></i>
              ရှင်းလင်းရန်
            </button>
        </div>
      </div>
      <div class="table-container" style="max-height: calc(100vh - 170px) !important;">
        <?php 
        ob_start();
        ?>
        <button class="btn btn-primary" id="addRow"><i class="fas fa-plus"></i> row အသစ်</button>
        <!-- <button class="btn btn-secondary" id="saveAll"><i class="fas fa-save"></i> Save-all</button> -->
        <form method="POST" style="display:inline;">
          <input type="hidden" name="farm_id" value="<?php echo $current_farm_id; ?>">
          <button type="submit" name="add_new_table" class="btn btn-primary"><i class="fas fa-plus"></i> ဇယားအသစ်</button>
        </form>
        <?php if ($current_page > 1): ?>
        <form method="POST" style="display:inline;" onsubmit="return confirmDeletePage();">
          <input type="hidden" name="page" value="<?php echo $current_page; ?>">
          <input type="hidden" name="farm_id" value="<?php echo $current_farm_id; ?>">
          <input type="hidden" name="global_page" value="<?php echo $current_global_page; ?>">
          <button type="submit" name="delete_current_page" class="btn btn-delete-page"><i class="fas fa-trash"></i> ဒီစာမျက်နှာကိုဖျက်ရန် (စာမျက်နှာ <?php echo $current_page; ?>)</button>
        </form>
        <?php endif; ?>
        <button type="button" class="btn btn-secondary" id="downloadExcel" onclick="downloadFeedExcel()"><i class="fas fa-file-excel"></i> Download</button>
        <button class="btn btn-danger" id="deleteAllData"><i class="fas fa-trash"></i> Delete-all </button>
        <?php
        $action_buttons_html = ob_get_clean();
        $show_pagination_buttons = true;
        include('../pagination.php'); 
        ?>
        <table id="feedTable" style="overflow: visible !important;">
          <thead>
            <!-- <tr style="height: 100px;"><th colspan="8">အစာစာရင်းချုပ် - <?php echo htmlspecialchars($current_farm['farm_username'] ?? 'Default Farm'); ?> - ခြံ(<?php echo $current_farm['farm_no'] ?? 1; ?>) - စာမျက်နှာ <?php echo $current_page; ?></th></tr> -->
            <tr style="height: 90px;">
              <th>ရက်စွဲ</th>
              <th>အစာအမျိုးအစား</th>
              <th>အစာအမည်</th>
              <th>အရေအတွက်</th>
              <th>ဈေးနှုန်း</th>
              <th>ကုန်ကျငွေ</th>
              <th style="width: 100px;">ဖျက်ရန်</th>
              <th>မှတ်ချက်</th>
            </tr>
          </thead>
          <tbody id="feedTableBody">
            <?php if ($feed_data && count($feed_data) > 0): ?>
              <?php foreach ($feed_data as $row): ?>
                <tr data-id="<?php echo $row['id']; ?>">
                  <td style="width: 14%;" class="editable" data-field="date"><?php echo $row['date'] ?: ''; ?></td>
                  <td style="width: 14%;" class="editable" data-field="feed_category"><?php echo htmlspecialchars($row['feed_category'] ?? ''); ?></td>
                  <td style="width: 14%;" class="editable" data-field="feed_name"><?php echo htmlspecialchars($row['feed_name'] ?? ''); ?></td>
                  <td style="width: 10%;" class="editable" data-field="quantity"><?php echo fmt($row['quantity']); ?></td>
                  <td style="width: 16%;" class="editable" data-field="unit_price"><?php echo fmt($row['unit_price']); ?></td>
                  <td style="width: 16%;" class="editable" data-field="total_cost"><?php echo fmt($row['total_cost']); ?></td>
                  <td style="width: 5%;">
                    <button class="btn-delete" data-id="<?php echo $row['id']; ?>">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </td>
                  <td style="width: 5%;" class="comment-cell">
                    <div class="comment-container">
                      <?php
                        $comment_author_name = '';
                        if (!empty($row['comment_author_id'])) {
                          $author_result = fetchOne('SELECT username FROM users WHERE id = ?', [$row['comment_author_id']]);
                          $comment_author_name = $author_result ? $author_result['username'] : '';
                        }
                        $badge_class = '';
                        $has_comment_value = !empty($row['has_comment']) ? '1' : '0';
                        $comment_read_value = !empty($row['comment_read']) ? '1' : '0';
                        if (!empty($row['has_comment'])) {
                          if ($user['role'] === 'admin') { $badge_class = 'badge-comment-admin'; }
                          else { $badge_class = !empty($row['comment_read']) ? 'badge-comment-read' : 'badge-comment-unread'; }
                        }
                      ?>
                      <button class="btn-comment" data-id="<?php echo $row['id']; ?>" data-table="summary"
                        data-has-comment="<?php echo $has_comment_value; ?>"
                        data-comment-read="<?php echo $comment_read_value; ?>"
                        data-current-comment="<?php echo htmlspecialchars($row['comments'] ?? ''); ?>"
                        data-comment-author="<?php echo htmlspecialchars($comment_author_name); ?>"
                        data-comment-date="<?php echo $row['comment_created_at'] ?? ''; ?>">
                        <i class="fa-regular fa-comment"></i>
                        <?php if (!empty($row['has_comment'])): ?><span class="comment-badge <?php echo $badge_class; ?>"></span><?php endif; ?>
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="8" style="text-align:center;padding:20px;">ဒေတာမရှိပါ။ row အသစ်ထပ်ယူပါ။</td></tr>
            <?php endif; ?>
          </tbody>
          <tfoot style="height: 80px;">
            <tr>
              <th colspan="3" style="font-weight: 400;">ရောက်ရှိအစာစုစုပေါင်း</th>
              <th id="sumQuantity">0</th>
              <th></th>
              <th id="sumTotal">0</th>
              <th></th>
              <th></th>
            </tr>
          </tfoot>
        </table>
      <?php
        $rem_total_quantity = 0;
        $rem_total_amount = 0;
        if ($remain_data && count($remain_data) > 0) {
          foreach ($remain_data as $row) {
            $rem_total_quantity += isset($row['rem_quan']) ? (float)$row['rem_quan'] : 0;
            $rem_total_amount += isset($row['rem_total']) ? (float)$row['rem_total'] : 0;
          }
        }
      ?>
        <table id="feedRemainTable">
          <thead>
            <!-- <tr style="height: 80px;">
              <th colspan="8">အစာကျန်</th>
            </tr> -->
          </thead>
          <tbody id="feedRemainBody">
            <?php if ($remain_data && count($remain_data) > 0): ?>
              <?php foreach ($remain_data as $row): ?>
                <tr data-id="<?php echo $row['id']; ?>">
                  <td style="width: 14%;">အစာကျန်စုစုပေါင်း</td>
                  <td style="width: 14%;" class="editable-rem" data-field="rem_cate"><?php echo htmlspecialchars($row['rem_cate'] ?? ''); ?></td>
                  <td style="width: 14%;" class="editable-rem" data-field="rem_name"><?php echo htmlspecialchars($row['rem_name'] ?? ''); ?></td>
                  <td style="width: 10%;" class="editable-rem" data-field="rem_quan"><?php echo fmt($row['rem_quan'] ?? 0); ?></td>
                  <td style="width: 16%;" class="editable-rem" data-field="rem_price"><?php echo fmt($row['rem_price'] ?? 0); ?></td>
                  <td style="width: 16%;" class="editable-rem" data-field="rem_total"><?php echo fmt($row['rem_total'] ?? 0); ?></td>
                  <td style="width: 5%;">
                    <button class="btn-delete" data-id="<?php echo $row['id']; ?>">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </td>
                  <td style="width: 5%;" class="comment-cell">
                    <div class="comment-container">
                      <?php
                        $rem_comment_author_name = '';
                        if (!empty($row['comment_author_id'])) {
                          $rem_author_result = fetchOne('SELECT username FROM users WHERE id = ?', [$row['comment_author_id']]);
                          $rem_comment_author_name = $rem_author_result ? $rem_author_result['username'] : '';
                        }
                        $rem_badge_class = '';
                        $rem_has_comment_value = !empty($row['has_comment']) ? '1' : '0';
                        $rem_comment_read_value = !empty($row['comment_read']) ? '1' : '0';
                        if (!empty($row['has_comment'])) {
                          if ($user['role'] === 'admin') { $rem_badge_class = 'badge-comment-admin'; }
                          else { $rem_badge_class = !empty($row['comment_read']) ? 'badge-comment-read' : 'badge-comment-unread'; }
                        }
                      ?>
                      <button class="btn-comment" data-id="<?php echo $row['id']; ?>"
                        data-table="remain"
                        data-has-comment="<?php echo $rem_has_comment_value; ?>"
                        data-comment-read="<?php echo $rem_comment_read_value; ?>"
                        data-current-comment="<?php echo htmlspecialchars($row['comments'] ?? ''); ?>"
                        data-comment-author="<?php echo htmlspecialchars($rem_comment_author_name); ?>"
                        data-comment-date="<?php echo $row['comment_created_at'] ?? ''; ?>">
                        <i class="fa-regular fa-comment"></i>
                        <?php if (!empty($row['has_comment'])): ?><span class="comment-badge <?php echo $rem_badge_class; ?>"></span><?php endif; ?>
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="8" style="text-align:center;padding:20px;">အစာကျန်ဒေတာမရှိပါ</td></tr>
            <?php endif; ?>
          </tbody>
          <tfoot style="height: 80px;">
            <tr>
              <th colspan="3" style="font-weight: 400;">
                စုစုပေါင်းအစာစားနှုန်း
                <button id="addRemainRow" class="btn btn-primary" style="margin-left: 12px; padding: 6px 10px;">
                  <i class="fas fa-plus"></i>
                </button>
              </th>
              <th id="sumRemainQuantity"><?php echo fmt($rem_total_quantity); ?></th>
              <th></th>
              <th id="sumRemainTotal"><?php echo fmt($rem_total_amount); ?></th>
              <th></th>
              <th></th>
            </tr>
          </tfoot>
        </table>
        <table id="feedBagTotalTable">
          <tfoot style="height: 80px;">
            <tr>
              <th colspan="5" class="feed-bag-label">အစာအိတ်စုစုပေါင်းကျသင့်ငွေ</th>
              <th id="sumFeedBagCost" class="feed-bag-amount">0</th>
              <th></th>
              <th></th>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

<script>
const currentFarmId = <?php echo $current_farm_id; ?>;
const currentPage = <?php echo $current_page; ?>;
const currentGlobalPage = <?php echo $current_global_page; ?>;
let startDate = <?php echo json_encode($start_date ?? null); ?>;
let endDate = <?php echo json_encode($end_date ?? null); ?>;

window.downloadFeedExcel = function() {
  const s = document.getElementById('startDate').value;
  const e = document.getElementById('endDate').value;
  const query = s && e ? `&start_date=${encodeURIComponent(s)}&end_date=${encodeURIComponent(e)}` : '';
  window.location.href = `download_excel_feed.php?page=${currentPage}&farm_id=${currentFarmId}${query}`;
};

function recalcRow(row){
  const qtyCell = row.querySelector('[data-field="quantity"]');
  const priceCell = row.querySelector('[data-field="unit_price"]');
  const totalCell = row.querySelector('[data-field="total_cost"]');
  if (!qtyCell || !priceCell || !totalCell) return;
  const q = parseFloat(qtyCell.textContent) || 0;
  const p = parseFloat(priceCell.textContent) || 0;
  const t = q * p;
  totalCell.textContent = Math.round(t);
}

function recalcTotals(){
  let sumQ = 0, sumT = 0;
  document.querySelectorAll('#feedTableBody tr').forEach(row=>{
    const qtyCell = row.querySelector('[data-field="quantity"]');
    const totalCell = row.querySelector('[data-field="total_cost"]');
    if (!qtyCell || !totalCell) return;
    const q = parseFloat(qtyCell.textContent) || 0;
    const t = parseFloat(totalCell.textContent) || 0;
    sumQ += q; sumT += t;
  });
  document.getElementById('sumQuantity').textContent = Math.round(sumQ);
  document.getElementById('sumTotal').textContent = Math.round(sumT);
  recalcRemainTotals();
}

function recalcRemainRow(row){
  const qtyCell = row.querySelector('[data-field="rem_quan"]');
  const priceCell = row.querySelector('[data-field="rem_price"]');
  const totalCell = row.querySelector('[data-field="rem_total"]');
  if (!qtyCell || !priceCell || !totalCell) return;
  const q = parseFloat(qtyCell.textContent) || 0;
  const p = parseFloat(priceCell.textContent) || 0;
  const t = q * p;
  totalCell.textContent = Math.round(t);
}

function recalcRemainTotals(){
  let sumQ = 0, sumT = 0;
  document.querySelectorAll('#feedRemainBody tr').forEach(row=>{
    const qtyCell = row.querySelector('[data-field="rem_quan"]');
    const totalCell = row.querySelector('[data-field="rem_total"]');
    if (!qtyCell || !totalCell) return;
    const q = parseFloat(qtyCell.textContent) || 0;
    const t = parseFloat(totalCell.textContent) || 0;
    sumQ += q; sumT += t;
  });
  const mainQEl = document.getElementById('sumQuantity');
  const mainTEl = document.getElementById('sumTotal');
  const sumQEl = document.getElementById('sumRemainQuantity');
  const sumTEl = document.getElementById('sumRemainTotal');
  const bagCostEl = document.getElementById('sumFeedBagCost');
  const mainQ = mainQEl ? parseFloat(mainQEl.textContent) || 0 : 0;
  const mainT = mainTEl ? parseFloat(mainTEl.textContent) || 0 : 0;
  const consumedQ = mainQ - sumQ;
  const consumedT = mainT - sumT;
  if (sumQEl) sumQEl.textContent = Math.round(consumedQ);
  if (sumTEl) sumTEl.textContent = Math.round(consumedT);
  if (bagCostEl) bagCostEl.textContent = Math.round(consumedT) + ' ကျပ်';
  saveConsumedTotal(consumedT, consumedQ);
}

let saveConsumedTimer = null;
function saveConsumedTotal(cost, quantity){
  if (saveConsumedTimer) clearTimeout(saveConsumedTimer);
  saveConsumedTimer = setTimeout(() => {
    fetch('save_feed.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        target: 'update_consumed_total',
        total: cost,
        total_quan: quantity,
        page_number: currentGlobalPage,
        farm_id: currentFarmId
      })
    }).catch(e => console.error(e));
  }, 1000);
}

function startEditing(cell){
  if (cell.querySelector('input')) return;
  const field = cell.getAttribute('data-field');
  const val = cell.textContent.trim();
  let input;
  if (field === 'date') input = `<input type="date" class="edit-input" value="${val}">`;
  else input = `<input type="text" class="edit-input" value="${val}">`;
  cell.innerHTML = input;
  const el = cell.querySelector('input');
  el.focus();
  el.select();
  el.addEventListener('blur', ()=>finishEditing(cell));
  el.addEventListener('keydown', (e)=>{ if(e.key==='Enter') finishEditing(cell); if(e.key==='Escape') cancelEditing(cell); });
}

function finishEditing(cell){
  const el = cell.querySelector('input');
  const val = el ? el.value : '';
  cell.textContent = val;
  const row = cell.closest('tr');
  recalcRow(row);
  recalcTotals();
  markRowPending(row);
  const id = row.getAttribute('data-id');
  if (id){
    sendMainRow(row).then(res=>{
      if(res && res.success){
        if(res.id) row.setAttribute('data-id', res.id);
        markRowSaved(row);
      } else {
        alert('သိမ်းရာတွင် အမှားရှိသည်');
      }
    }).catch(()=>alert('Network error'));
  }
}

function cancelEditing(cell){
  const el = cell.querySelector('input');
  cell.textContent = el ? el.defaultValue : cell.textContent;
}

function getRowData(row){
  const data = {};
  row.querySelectorAll('[data-field]').forEach(c=>{
    const f = c.getAttribute('data-field');
    const input = c.querySelector('input');
    let v = input ? input.value.trim() : c.textContent.trim();
    if (['quantity','unit_price','total_cost'].includes(f)) v = parseFloat(v)||0;
    data[f] = v;
  });
  const id = row.getAttribute('data-id') || null;
  data.id = id;
  data.page_number = currentGlobalPage;
  data.farm_id = currentFarmId;
  data.target = 'summary';
  return data;
}

function getRemainRowData(row){
  const data = {};
  row.querySelectorAll('[data-field]').forEach(c=>{
    const f = c.getAttribute('data-field');
    const input = c.querySelector('input');
    let v = input ? input.value.trim() : c.textContent.trim();
    if (['rem_quan','rem_price','rem_total'].includes(f)) v = parseFloat(v)||0;
    data[f] = v;
  });
  const id = row.getAttribute('data-id') || null;
  data.id = id;
  data.page_number = currentGlobalPage;
  data.farm_id = currentFarmId;
  data.target = 'remain';
  return data;
}

function markRowPending(row){
  const btn = row.querySelector('.save-btn');
  if (!btn) return;
  btn.textContent = 'သိမ်းရန်';
  btn.classList.add('pending');
  btn.classList.remove('saved');
}

function markRowSaved(row){
  const btn = row.querySelector('.save-btn');
  if (!btn) return;
  btn.textContent = 'သိမ်းပြီး';
  btn.classList.remove('pending');
  btn.classList.add('saved');
}

function startEditingRemain(cell){
  if (cell.querySelector('input')) return;
  const val = cell.textContent.trim();
  const input = `<input type="text" class="edit-input" value="${val}">`;
  cell.innerHTML = input;
  const el = cell.querySelector('input');
  el.focus();
  el.select();
  el.addEventListener('blur', ()=>finishEditingRemain(cell));
  el.addEventListener('keydown', (e)=>{ if(e.key==='Enter') finishEditingRemain(cell); if(e.key==='Escape') cancelEditing(cell); });
}

function finishEditingRemain(cell){
  const el = cell.querySelector('input');
  const val = el ? el.value : '';
  cell.textContent = val;
  const row = cell.closest('tr');
  recalcRemainRow(row);
  recalcRemainTotals();
  sendRemainRow(row).then(res=>{
    if(res && res.success){
      if (res.id){
        row.setAttribute('data-id', res.id);
        const commentBtn = row.querySelector('.btn-comment');
        if (commentBtn) commentBtn.setAttribute('data-id', res.id);
      }
    } else {
      alert('သိမ်းရာတွင် အမှားရှိသည်');
    }
  }).catch(()=>alert('Network error'));
}

function sendMainRow(row){
  const payload = getRowData(row);
  return fetch('save_feed.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  }).then(r=>r.json());
}

function sendRemainRow(row){
  const payload = getRemainRowData(row);
  return fetch('save_feed.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  }).then(r=>r.json());
}

document.addEventListener('DOMContentLoaded', ()=>{
  document.getElementById('feedTableBody').addEventListener('dblclick', (e)=>{
    const cell = e.target.closest('td.editable');
    if (cell) startEditing(cell);
  });
  const remainBody = document.getElementById('feedRemainBody');
  if (remainBody){
    remainBody.addEventListener('dblclick', (e)=>{
      const cell = e.target.closest('td.editable-rem');
      if (cell) startEditingRemain(cell);
    });
  }
  function handleDeleteRow(row, type){
    const id = row.getAttribute('data-id');
    if (!id){
      row.remove();
      if (type === 'remain') recalcRemainTotals();
      else recalcTotals();
      return;
    }
    if (!confirm('ဤအချက်အလက်ကိုဖျက်မှာသေချာပါသလား?')) return;
    fetch('delete_feed.php',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ id, target: type })
    })
    .then(r=>r.json())
    .then(res=>{
      if(res && res.success){
        if (type === 'remain'){
          const remRow = document.querySelector('#feedRemainBody tr[data-id="'+id+'"]') || row;
          if (remRow) remRow.remove();
          recalcRemainTotals();
        } else {
          const mainRow = document.querySelector('#feedTableBody tr[data-id="'+id+'"]') || row;
          if (mainRow) mainRow.remove();
          recalcTotals();
        }
        alert('ဖျက်ပြီးပါပြီ');
      } else {
        alert('ဖျက်ရာတွင် အမှားရှိသည်');
      }
    })
    .catch(()=>alert('Network error'));
  }
  document.getElementById('feedTableBody').addEventListener('click', (e)=>{
    const btn = e.target.closest('.save-btn');
    if (btn){
      const row = btn.closest('tr');
      sendMainRow(row).then(res=>{
        if(res && res.success){
          if(res.id) row.setAttribute('data-id', res.id);
          alert('အောင်မြင်စွာသိမ်းဆည်းပြီး');
          markRowSaved(row);
        } else {
          alert('သိမ်းရာတွင် အမှားရှိသည်');
        }
      }).catch(()=>alert('Network error'));
    }
    const delBtn = e.target.closest('.btn-delete');
    if (delBtn){
      const row = delBtn.closest('tr');
      handleDeleteRow(row, 'summary');
    }
  });
  if (remainBody){
    remainBody.addEventListener('click', (e)=>{
      const delBtn = e.target.closest('.btn-delete');
      if (delBtn){
        const row = delBtn.closest('tr');
        handleDeleteRow(row, 'remain');
      }
    });
  }
  const addRemainBtn = document.getElementById('addRemainRow');
  if (addRemainBtn && remainBody){
    addRemainBtn.addEventListener('click', ()=>{
      const noDataRow = remainBody.querySelector('tr td[colspan]');
      if (noDataRow) noDataRow.closest('tr').remove();
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td style="width: 14%;">အစာကျန်စုစုပေါင်း</td>
        <td style="width: 14%;" class="editable-rem" data-field="rem_cate"></td>
        <td style="width: 14%;" class="editable-rem" data-field="rem_name"></td>
        <td style="width: 10%;" class="editable-rem" data-field="rem_quan">0</td>
        <td style="width: 16%;" class="editable-rem" data-field="rem_price">0</td>
        <td style="width: 16%;" class="editable-rem" data-field="rem_total">0</td>
        <td style="width: 5%;"><button class="btn-delete"><i class="fa-solid fa-trash"></i></button></td>
        <td style="width: 5%;" class="comment-cell"><div class="comment-container"><button class="btn-comment" data-id="" data-table="remain"><i class="fa-regular fa-comment"></i></button></div></td>
      `;
      remainBody.appendChild(tr);
      recalcRemainRow(tr);
      recalcRemainTotals();
      sendRemainRow(tr).then(res=>{
        if(res && res.success){
          if (res.id){
            tr.setAttribute('data-id', res.id);
            const commentBtn = tr.querySelector('.btn-comment');
            if (commentBtn) commentBtn.setAttribute('data-id', res.id);
          }
        } else {
          alert('သိမ်းရာတွင် အမှားရှိသည်');
        }
      }).catch(()=>alert('Network error'));
    });
  }
  document.getElementById('addRow').addEventListener('click', ()=>{
    const tbody = document.getElementById('feedTableBody');
    const noDataRow = tbody.querySelector('tr td[colspan]');
    if (noDataRow) { noDataRow.closest('tr').remove(); }
    const today = new Date().toISOString().split('T')[0];
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td class="editable" data-field="date">${today}</td>
      <td class="editable" data-field="feed_category"></td>
      <td class="editable" data-field="feed_name"></td>
      <td class="editable" data-field="quantity">0</td>
      <td class="editable" data-field="unit_price">0</td>
      <td class="editable" data-field="total_cost">0</td>
      <td><button class="btn-delete"><i class="fa-solid fa-trash"></i></button></td>
      <td class="comment-cell"><div class="comment-container"><button class="btn-comment" data-id="" data-table="summary"><i class="fa-regular fa-comment"></i></button></div></td>
    `;
    tbody.appendChild(tr);
    recalcRow(tr);
    recalcTotals();
    markRowPending(tr);
    sendMainRow(tr).then(res=>{
      if(res && res.success){
        if(res.id){
          tr.setAttribute('data-id', res.id);
          const commentBtn = tr.querySelector('.btn-comment');
          if (commentBtn) commentBtn.setAttribute('data-id', res.id);
        }
        markRowSaved(tr);
      } else {
        alert('သိမ်းရာတွင် အမှားရှိသည်');
      }
    }).catch(()=>alert('Network error'));
  });
  document.getElementById('saveAll').addEventListener('click', ()=>{
    const rows = Array.from(document.querySelectorAll('#feedTableBody tr')).filter(r=>r.querySelector('[data-field]'));
    if (rows.length === 0){ alert('သိမ်းရန် ဒေတာမရှိပါ'); return; }
    const payload = rows.map(getRowData);
    fetch('save_bulk_feed.php',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({feeds: payload, farm_id: currentFarmId, page_number: currentGlobalPage})
    })
    .then(r=>r.json())
    .then(res=>{ if(res && res.success){ alert('ဒေတာအားလုံးသိမ်းပြီး'); location.href = `feed.php?page=${currentPage}&farm_id=${currentFarmId}${startDate&&endDate?`&start_date=${startDate}&end_date=${endDate}`:''}`; } else { alert('သိမ်းရာတွင် အမှားရှိသည်'); } })
    .catch(()=>alert('Network error'));
  });
  document.querySelectorAll('#feedTableBody tr').forEach(recalcRow);
  recalcTotals();
  document.querySelectorAll('#feedRemainBody tr').forEach(recalcRemainRow);
  recalcRemainTotals();

  const searchBtn = document.getElementById('btnSearch');
  const clearBtn = document.getElementById('btnClear');
  const startInput = document.getElementById('startDate');
  const endInput = document.getElementById('endDate');

  if (searchBtn) {
    searchBtn.addEventListener('click', ()=>{
      const s = startInput.value; const e = endInput.value;
      if (s && e){ window.location.href = `feed.php?page=${currentPage}&farm_id=${currentFarmId}&start_date=${s}&end_date=${e}`; }
      else { alert('ကျေးဇူးပြု၍ ရက်စွဲနှစ်ခုလုံးထည့်ပါ'); }
    });
  }

  if (clearBtn) {
    clearBtn.addEventListener('click', ()=>{
      startInput.value = ''; endInput.value = '';
      window.location.href = `feed.php?page=${currentPage}&farm_id=${currentFarmId}`;
    });
  }

  const pageLinks = document.querySelectorAll('.page-btn');
  pageLinks.forEach(link=>{
    if (link.href){ const url = new URL(link.href); url.searchParams.set('farm_id', currentFarmId); if (startDate && endDate){ url.searchParams.set('start_date', startDate); url.searchParams.set('end_date', endDate);} link.href = url.toString(); }
  });

  const deleteAllBtn = document.getElementById('deleteAllData');
  deleteAllBtn.addEventListener('click', ()=>{
    if (!confirm('ဤစာမျက်နှာရှိ ဒေတာအားလုံးကိုဖျက်မှာသေချာပါသလား?')) return;
    fetch('delete_all_feed.php',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ page_number: currentGlobalPage, farm_id: currentFarmId })
    })
    .then(r=>r.json())
    .then(res=>{ if(res && res.success){ alert('ဒေတာအားလုံးဖျက်ပြီးပါပြီ'); location.href = `feed.php?page=${currentPage}&farm_id=${currentFarmId}`; } else { alert('ဖျက်ရာတွင် အမှားရှိသည်'); } })
    .catch(()=>alert('Network error'));
  });
});
</script>
<script>
function confirmDeletePage(){
  const currentPage = <?php echo $current_page; ?>;
  const currentFarmId = <?php echo $current_farm_id; ?>;
  return confirm(`⚠️ သတိပြုပါ!\n\nစာမျက်နှာ ${currentPage} ကိုဖျက်မှာသေချာပါသလား?\n\nဒီစာမျက်နှာကို ဖိုင်အားလုံးမှ (sell.php, food.php, medicine.php) ဖျက်သွားမည်။\n\nဒေတာများအားလုံးပျက်သွားမည်။ ဆက်လုပ်မှာသေချာပါသလား?`);
}
</script>
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
      <button type="button" id="btnCommentMarkRead" class="btn-comment-mark-read" style="display:none;">ဖတ်ပြီးပါပြီ</button>
      <button type="button" id="btnCommentCancel" class="btn-comment-cancel">မလုပ်တော့ပါ</button>
    </div>
  </div>
</div>
<script>
(function(){
  const root = document.querySelector('.sell_container');
  const btn = document.getElementById('toggleFullscreen');
  if (!btn || !root) return;
  function setState(active){
    root.classList.toggle('fullscreen', active);
    document.body.classList.toggle('fullscreen-mode', active);
    const icon = btn.querySelector('i');
    if (active){
      icon.classList.remove('fa-expand');
      icon.classList.add('fa-compress');
      btn.title = 'ပုံမှန်အရွယ်သို့ပြန်ရန်';
      try{ localStorage.setItem('feedFullscreen','1'); }catch(e){}
    } else {
      icon.classList.remove('fa-compress');
      icon.classList.add('fa-expand');
      btn.title = 'ကြည့်ရန်ကျယ်';
      try{ localStorage.setItem('feedFullscreen','0'); }catch(e){}
    }
  }
  btn.addEventListener('click', function(){ const active = !root.classList.contains('fullscreen'); setState(active); });
  document.addEventListener('keydown', function(e){ if (e.key==='Escape' && root.classList.contains('fullscreen')) setState(false); });
  try{ const stored = localStorage.getItem('feedFullscreen'); if (stored==='1') setState(true);}catch(e){}
})();
</script>
<script>
document.addEventListener('DOMContentLoaded', function(){
  const commentModal = document.getElementById('commentModal');
  const commentText = document.getElementById('commentText');
  const commentModalTitle = document.getElementById('commentModalTitle');
  const commentInfo = document.getElementById('commentInfo');
  const btnCommentSave = document.getElementById('btnCommentSave');
  const btnCommentMarkRead = document.getElementById('btnCommentMarkRead');
  const btnCommentCancel = document.getElementById('btnCommentCancel');
  const closeBtn = document.querySelector('.comment-modal-close');
  let currentCommentRowId = null;
  let currentCommentTable = 'summary';
  const currentUserRole = '<?php echo $user["role"]; ?>';
  const currentUserId = <?php echo $user["id"]; ?>;
  document.addEventListener('click', function(e){
    if (e.target.closest('.btn-comment')){
      const btn = e.target.closest('.btn-comment');
      currentCommentRowId = btn.getAttribute('data-id') || '';
      currentCommentTable = btn.getAttribute('data-table') || 'summary';
      const hasComment = (btn.getAttribute('data-has-comment')||'0') === '1';
      const commentRead = (btn.getAttribute('data-comment-read')||'0') === '1';
      const currentComment = btn.getAttribute('data-current-comment') || '';
      const commentAuthor = btn.getAttribute('data-comment-author') || '';
      const commentDate = btn.getAttribute('data-comment-date') || '';
      openCommentModal(currentCommentRowId, hasComment, commentRead, currentComment, commentAuthor, commentDate);
    }
  });
  function openCommentModal(rowId, hasComment, commentRead, currentComment, commentAuthor, commentDate){
    currentCommentRowId = rowId;
    commentText.value = currentComment;
    if (currentUserRole === 'admin'){
      commentModalTitle.textContent = 'မှတ်ချက်ပေးရန်';
      commentText.readOnly = false;
      btnCommentSave.style.display = 'inline-block';
      btnCommentMarkRead.style.display = 'none';
      if (hasComment){ commentInfo.textContent = `မှတ်ချက်ရေးထားသူ: ${commentAuthor} | ရက်စွဲ: ${commentDate}`; commentInfo.style.display = 'block'; } else { commentInfo.style.display = 'none'; }
    } else {
      commentModalTitle.textContent = 'မှတ်ချက်ကြည့်ရန်';
      commentText.readOnly = true;
      btnCommentSave.style.display = 'none';
      if (hasComment){
        commentInfo.textContent = `မှတ်ချက်ရေးထားသူ: ${commentAuthor} | ရက်စွဲ: ${commentDate}`;
        commentInfo.style.display = 'block';
        if (!commentRead) btnCommentMarkRead.style.display = 'inline-block'; else btnCommentMarkRead.style.display = 'none';
      } else {
        commentInfo.textContent = 'မှတ်ချက်မရှိပါ';
        commentInfo.style.display = 'block';
        btnCommentMarkRead.style.display = 'none';
      }
    }
    commentModal.style.display = 'block';
  }
  btnCommentSave.addEventListener('click', function(){
    if (!currentCommentRowId){ alert('ကျေးဇူးပြု၍ ဒီ row ကိုအရင် save လုပ်ပါ (Save-all ကိုနှိပ်ပါ)'); return; }
    const comment = commentText.value.trim();
    const originalText = btnCommentSave.textContent;
    btnCommentSave.innerHTML = '<i class="fas fa-spinner fa-spin"></i> သိမ်းဆည်းနေသည်...';
    btnCommentSave.disabled = true;
    fetch('save_comment.php',{ method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ row_id: currentCommentRowId, comment, user_id: currentUserId, table: currentCommentTable }) })
    .then(r=>r.json())
    .then(result=>{
      if (result && result.success){
        const commentBtn = document.querySelector(`.btn-comment[data-id="${currentCommentRowId}"][data-table="${currentCommentTable}"]`);
        if (commentBtn){
          commentBtn.setAttribute('data-has-comment','1');
          commentBtn.setAttribute('data-comment-read','0');
          commentBtn.setAttribute('data-current-comment', comment);
          commentBtn.setAttribute('data-comment-author', '<?php echo $user['username']; ?>');
          commentBtn.setAttribute('data-comment-date', new Date().toISOString());
          let badge = commentBtn.querySelector('.comment-badge');
          if (!badge){ badge = document.createElement('span'); badge.className = 'comment-badge'; commentBtn.appendChild(badge); }
          badge.className = 'comment-badge badge-comment-admin';
        }
        alert('မှတ်ချက်သိမ်းဆည်းပြီး');
        commentModal.style.display = 'none';
      } else { alert('မှတ်ချက်သိမ်းရာတွင် အမှားရှိသည်'); }
    })
    .catch(()=>alert('Network error'))
    .finally(()=>{ btnCommentSave.textContent = originalText; btnCommentSave.disabled = false; });
  });
  btnCommentMarkRead.addEventListener('click', function(){
    if (!currentCommentRowId) return;
    fetch('mark_comment_read.php',{ method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ row_id: currentCommentRowId, table: currentCommentTable }) })
    .then(r=>r.json())
    .then(result=>{
      if (result && result.success){
        const commentBtn = document.querySelector(`.btn-comment[data-id="${currentCommentRowId}"][data-table="${currentCommentTable}"]`);
        if (commentBtn){ commentBtn.setAttribute('data-comment-read','1'); const badge = commentBtn.querySelector('.comment-badge'); if (badge) badge.className = 'comment-badge badge-comment-read'; }
        btnCommentMarkRead.style.display = 'none';
        alert('မှတ်ချက်ဖတ်ပြီးအမှတ်အသားပြုထားပြီး');
      } else { alert('အမှတ်အသားပြုရာတွင် အမှားရှိသည်'); }
    })
    .catch(()=>alert('Network error'));
  });
  function closeCommentModal(){ commentModal.style.display='none'; currentCommentRowId=null; commentText.value=''; }
  btnCommentCancel.addEventListener('click', closeCommentModal);
  closeBtn.addEventListener('click', closeCommentModal);
  window.addEventListener('click', function(e){ if (e.target === commentModal) closeCommentModal(); });
});
</script>
</body>
</html>
