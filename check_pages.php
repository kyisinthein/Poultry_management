<?php
include 'database.php';

echo "<h2>Pagination Verification</h2>";

// Check available pages for each file type
$page_types = ['summary', 'food', 'sales', 'medicine', 'grand-total'];

foreach ($page_types as $type) {
    $sql = "SELECT page_number FROM pagination WHERE page_type = ? ORDER BY page_number";
    $pages = fetchAll($sql, [$type]);
    $page_numbers = array_column($pages, 'page_number');
    
    echo "<h3>$type.php - Available Pages: " . implode(', ', $page_numbers) . "</h3>";
    
    // Check if data exists for each page
    if ($type == 'sales') {
        foreach ($page_numbers as $page) {
            $data_sql = "SELECT COUNT(*) as count FROM sales_summary WHERE page_number = ?";
            $count = fetchOne($data_sql, [$page]);
            echo "Page $page: {$count['count']} records<br>";
        }
    }
}
?>