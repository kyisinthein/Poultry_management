<?php
// pagination.php - ADD PAGE MAPPING TO JAVASCRIPT

$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$current_farm_id = $_GET['farm_id'] ?? $_SESSION['current_farm_id'] ?? 1;

// Get all available pages from database FOR CURRENT FARM ONLY
try {
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

// If current display page doesn't exist, go to page 1
if (!in_array($current_page, $display_pages)) {
    $current_page = 1;
    $current_global_page = $page_mapping[1];
}

// Store mapping in session for use in other parts of the application
$_SESSION['page_mapping'][$current_farm_id] = $page_mapping;

// Calculate limited page numbers to display (max 5 pages)
$max_display_pages = 5;
$start_page = max(1, $current_page - floor($max_display_pages / 2));
$end_page = min($total_pages, $start_page + $max_display_pages - 1);

if ($end_page - $start_page < $max_display_pages - 1) {
    $start_page = max(1, $end_page - $max_display_pages + 1);
}

$display_range = [];
for ($i = $start_page; $i <= $end_page; $i++) {
    $display_range[] = $i;
}
?>

<!-- ADD THIS JAVASCRIPT SECTION -->
<script>
// Page mapping for current farm
window.pageMapping = <?php echo json_encode($page_mapping); ?>;
window.currentGlobalPage = <?php echo $current_global_page; ?>;
window.currentDisplayPage = <?php echo $current_page; ?>;
</script>

<div class="pagination-container sticky-top">
    <div class="pagination-info">
        <span>စာမျက်နှာ <?php echo $current_page; ?> / <?php echo $total_pages; ?></span>
    </div>
    
    <div class="pagination-wrap">
      <div class="pagination">
        <!-- First Page -->
        <?php if ($current_page > 1): ?>
            <a href="?page=1&farm_id=<?php echo $current_farm_id; ?>" class="page-btn first">
                <i class="fas fa-angle-double-left"></i>
            </a>
        <?php else: ?>
            <span class="page-btn first disabled">
                <i class="fas fa-angle-double-left"></i>
            </span>
        <?php endif; ?>
        
        <!-- Previous Page -->
        <?php if ($current_page > 1): ?>
            <a href="?page=<?php echo $current_page - 1; ?>&farm_id=<?php echo $current_farm_id; ?>" class="page-btn prev">
                <i class="fas fa-angle-left"></i>
            </a>
        <?php else: ?>
            <span class="page-btn prev disabled">
                <i class="fas fa-angle-left"></i>
            </span>
        <?php endif; ?>
        
        <!-- Page Numbers -->
        <?php foreach ($display_range as $display_num): ?>
            <?php if ($display_num == $current_page): ?>
                <span class="page-btn active"><?php echo $display_num; ?></span>
            <?php else: ?>
                <a href="?page=<?php echo $display_num; ?>&farm_id=<?php echo $current_farm_id; ?>" class="page-btn">
                    <?php echo $display_num; ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
        
        <!-- Next Page -->
        <?php if ($current_page < $total_pages): ?>
            <a href="?page=<?php echo $current_page + 1; ?>&farm_id=<?php echo $current_farm_id; ?>" class="page-btn next">
                <i class="fas fa-angle-right"></i>
            </a>
        <?php else: ?>
            <span class="page-btn next disabled">
                <i class="fas fa-angle-right"></i>
            </span>
        <?php endif; ?>
        
        <!-- Last Page -->
        <?php if ($current_page < $total_pages): ?>
            <a href="?page=<?php echo $total_pages; ?>&farm_id=<?php echo $current_farm_id; ?>" class="page-btn last">
                <i class="fas fa-angle-double-right"></i>
            </a>
        <?php else: ?>
            <span class="page-btn last disabled">
                <i class="fas fa-angle-double-right"></i>
            </span>
        <?php endif; ?>
      </div>
      <button class="page-btn btn-icon" id="toggleFullscreen" title="ကြည့်ရန်ကျယ်">
          <i class="fas fa-expand"></i>
      </button>
    </div>
</div>

<style>
.pagination {
    display: flex;
    align-items: center;
    gap: 5px;
}

.page-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    text-decoration: none;
    color: #333;
    background: white;
    min-width: 40px;
    transition: all 0.3s ease;
}
.pagination-wrap { display: flex; align-items: center; gap: 8px; }
.pagination-wrap .btn-icon { border-color: #007bff; color: #007bff; }
.pagination-wrap .btn-icon:hover { background: #007bff; color: white; }

.page-btn:hover {
    background: #f0f0f0;
    border-color: #007bff;
}

.page-btn.active {
    background: #007bff;
    color: white;
    border-color: #007bff;
}

.page-btn.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: #f8f9fa;
}

.page-ellipsis {
    padding: 8px 8px;
    color: #6c757d;
}
</style>