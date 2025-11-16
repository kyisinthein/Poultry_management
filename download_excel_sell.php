<?php
include('database.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get parameters
$farm_id = $_GET['farm_id'] ?? $_SESSION['current_farm_id'] ?? 1;
$page = $_GET['page'] ?? 1;
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

// Debug: Log parameters
error_log("Download Excel - Farm ID: $farm_id, Page: $page, Start: $start_date, End: $end_date");

// Get farm data
$farm = fetchOne("SELECT * FROM farms WHERE id = ?", [$farm_id]);
if (!$farm) {
    die("Farm not found");
}

// SIMPLIFIED: Directly use the page number from URL without complex mapping
$current_global_page = $page;

// Debug: Check what pages exist for this farm
$pages_check_sql = "SELECT DISTINCT page_number FROM pagination WHERE farm_id = ? ORDER BY page_number";
$existing_pages = fetchAll($pages_check_sql, [$farm_id]);
error_log("Existing pages for farm $farm_id: " . implode(', ', array_column($existing_pages, 'page_number')));

// Fetch sales data - SIMPLIFIED QUERY
$sales_sql = "SELECT * FROM sales_summary WHERE farm_id = ?";
$params = [$farm_id];

// Add page filter if provided
if ($page) {
    $sales_sql .= " AND page_number = ?";
    $params[] = $current_global_page;
}

// Add date filter if provided
if ($start_date && $end_date) {
    $sales_sql .= " AND date BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
}

$sales_sql .= " ORDER BY date ASC";

// Debug the final query
error_log("Final SQL: $sales_sql");
error_log("Params: " . implode(', ', $params));

$sales_data = fetchAll($sales_sql, $params);

// Debug: Check how many rows found
error_log("Rows found: " . count($sales_data));

// If no data found with page filter, try without page filter
if (empty($sales_data)) {
    error_log("No data found with page filter, trying without page filter");
    $sales_sql = "SELECT * FROM sales_summary WHERE farm_id = ?";
    $params = [$farm_id];
    
    if ($start_date && $end_date) {
        $sales_sql .= " AND date BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
    }
    
    $sales_sql .= " ORDER BY date ASC";
    $sales_data = fetchAll($sales_sql, $params);
    error_log("Rows found without page filter: " . count($sales_data));
}

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="sales_summary_' . $farm['farm_username'] . '_page_' . $page . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

?>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta charset="UTF-8">
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid black; padding: 8px; text-align: center; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .title { font-size: 18px; font-weight: bold; text-align: center; margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="title">အရောင်းစာရင်းချုပ် - <?php echo htmlspecialchars($farm['farm_username']); ?> - ခြံ(<?php echo $farm['farm_no']; ?>) - စာမျက်နှာ <?php echo $page; ?></div>

<?php if ($start_date && $end_date): ?>
<div style="text-align: center; margin-bottom: 15px;">ရက်စွဲ: <?php echo $start_date; ?> မှ <?php echo $end_date; ?> အထိ</div>
<?php endif; ?>

<table border="1">
    <thead>
        <tr>
            <th>ရက်စွဲ</th>
            <th>ရောင်းကောင်</th>
            <th>အလေးချိန်</th>
            <th>စုစုပေါင်းရောင်းကောင်</th>
            <th>စုစုပေါင်းအလေးချိန်</th>
            <th>အလေးချိန်</th>
            <th>အသေပေါင်း</th>
            <th>အသေရာခိုင်နှုန်း</th>
            <th>စုစုပေါင်းရောင်းကောင်</th>
            <th>အပို/အလို</th>
            <th>21to30</th>
            <th>31to36</th>
            <th>37toend</th>
            <th>ကြက်သားအလေးချိန်စုစုပေါင်း</th>
            <th>စုစုပေါင်းအစာစားနှုန်း</th>
            <th>ကြက်စာအလေးချိန်စုစုပေါင်း</th>
            <th>အလေးချိန်</th>
            <th>FCR</th>
            <th>မှတ်ချက်</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($sales_data && count($sales_data) > 0): ?>
            <?php foreach ($sales_data as $row): ?>
            <tr>
                <td><?php echo $row['date'] ?: ''; ?></td>
                <td><?php echo $row['sold_count'] ?: '0'; ?></td>
                <td><?php echo $row['weight_per_chicken'] ?: '0'; ?></td>
                <td><?php echo $row['total_sold_count'] ?: '0'; ?></td>
                <td><?php echo $row['total_weight'] ?: '0'; ?></td>
                <td><?php echo $row['daily_weight'] ?: '0'; ?></td>
                <td><?php echo $row['dead_count'] ?: '0'; ?></td>
                <td><?php echo $row['mortality_rate'] ?: '0'; ?></td>
                <td><?php echo $row['cumulative_sold_count'] ?: '0'; ?></td>
                <td><?php echo $row['surplus_deficit'] ?: '0'; ?></td>
                <td><?php echo $row['weight_21to30'] ?: '0'; ?></td>
                <td><?php echo $row['weight_31to36'] ?: '0'; ?></td>
                <td><?php echo $row['weight_37to_end'] ?: '0'; ?></td>
                <td><?php echo $row['total_chicken_weight'] ?: '0'; ?></td>
                <td><?php echo $row['total_feed_consumption_rate'] ?: '0'; ?></td>
                <td><?php echo $row['total_feed_weight'] ?: '0'; ?></td>
                <td><?php echo $row['final_weight'] ?: '0'; ?></td>
                <td><?php echo $row['fcr'] ?: '0'; ?></td>
                <td><?php echo htmlspecialchars($row['comments'] ?? ''); ?></td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="19" style="text-align: center;">ဒေတာမရှိပါ</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<div style="margin-top: 20px; font-size: 12px;">
    <p>ပရင့်ထုတ်သည့်ရက်စွဲ: <?php echo date('Y-m-d H:i:s'); ?></p>
    <p>စုစုပေါင်းမှတ်တမ်း: <?php echo count($sales_data); ?> ခု</p>
</div>

</body>
</html>