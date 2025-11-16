<?php
include 'database.php';

// Fix missing pagination records for all sales data
try {
    // Get all distinct page_number and farm_id combinations from sales_summary
    $sql = "SELECT DISTINCT page_number, farm_id FROM sales_summary";
    $stmt = $pdo->query($sql);
    $sales_pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Fixing Pagination Records</h2>";
    echo "<p>Found " . count($sales_pages) . " unique page/farm combinations in sales_summary</p>";
    
    $fixed_count = 0;
    $page_types = ['summary', 'food', 'sales', 'medicine', 'grand-total'];
    
    foreach ($sales_pages as $page) {
        $page_number = $page['page_number'];
        $farm_id = $page['farm_id'];
        
        echo "<p>Checking page $page_number, farm $farm_id: ";
        
        foreach ($page_types as $type) {
            // Check if exists
            $check_sql = "SELECT COUNT(*) as count FROM pagination WHERE page_number = ? AND farm_id = ? AND page_type = ?";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([$page_number, $farm_id, $type]);
            $result = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] == 0) {
                // Create missing record
                $insert_sql = "INSERT INTO pagination (page_number, page_type, farm_id) VALUES (?, ?, ?)";
                $insert_stmt = $pdo->prepare($insert_sql);
                $insert_stmt->execute([$page_number, $type, $farm_id]);
                echo " Created $type,";
                $fixed_count++;
            } else {
                echo " $type exists,";
            }
        }
        
        echo "</p>";
    }
    
    echo "<h3>Fixed $fixed_count missing pagination records</h3>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>