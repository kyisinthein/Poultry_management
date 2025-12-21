<?php
include('../database.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Get parameters
$farm_id = $_GET['farm_id'] ?? $_SESSION['current_farm_id'] ?? 1;
$page = $_GET['page'] ?? 1;
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

// Get farm data
$farm = fetchOne("SELECT * FROM farms WHERE id = ?", [$farm_id]);
if (!$farm) {
    die("Farm not found");
}

// Determine global page
$current_global_page = $page;
try {
    $pages_sql = "SELECT DISTINCT page_number FROM pagination WHERE farm_id = ? ORDER BY page_number ASC";
    $pages_result = fetchAll($pages_sql, [$farm_id]);
    $global_pages = array_column($pages_result, 'page_number');
    
    $page_mapping = [];
    foreach ($global_pages as $index => $global_page) {
        $display_page = $index + 1;
        $page_mapping[$display_page] = $global_page;
    }
    
    if (isset($page_mapping[$page])) {
        $current_global_page = $page_mapping[$page];
    } else {
         $current_global_page = $page_mapping[1] ?? 1;
    }

} catch (Exception $e) {
    $current_global_page = 1;
}

// Fetch feed data
$sql = "SELECT * FROM feed_summary WHERE page_number = ? AND farm_id = ?";
$params = [$current_global_page, $farm_id];

if ($start_date && $end_date) {
    $sql .= " AND date BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
}

$sql .= " ORDER BY date ASC";
$feed_data = fetchAll($sql, $params);

// Helper functions for display
function fmt($n){ if ($n === null || $n === '') return '0'; $s = (string)$n; if (strpos($s, '.') !== false) { $s = rtrim(rtrim($s, '0'), '.'); } return $s === '' ? '0' : $s; }

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="အစာစာရင်းချုပ်(' . $farm['farm_username'] . '_p' . $page . ').xls"');
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

<div class="title">အစာစာရင်းချုပ် - <?php echo htmlspecialchars($farm['farm_username']); ?> - ခြံ(<?php echo $farm['farm_no']; ?>) - စာမျက်နှာ <?php echo $page; ?></div>

<?php if ($start_date && $end_date): ?>
<div style="text-align: center; margin-bottom: 15px;">ရက်စွဲ: <?php echo $start_date; ?> မှ <?php echo $end_date; ?> အထိ</div>
<?php endif; ?>

<table border="1">
    <thead>
        <tr>
            <th>ရက်စွဲ</th>
            <th>အစာအမျိုးအစား</th>
            <th>အစာအမည်</th>
            <th>အရေအတွက်</th>
            <th>ဈေးနှုန်း</th>
            <th>ကုန်ကျငွေ</th>
            <th>မှတ်ချက်</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($feed_data && count($feed_data) > 0): ?>
            <?php foreach ($feed_data as $row): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['date'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['feed_category'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['feed_name'] ?? ''); ?></td>
                <td><?php echo fmt($row['quantity']); ?></td>
                <td><?php echo fmt($row['unit_price']); ?></td>
                <td><?php echo fmt($row['total_cost']); ?></td>
                <td><?php echo htmlspecialchars($row['comments'] ?? ''); ?></td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="7" style="text-align: center;">ဒေတာမရှိပါ</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<div style="margin-top: 20px; font-size: 12px;">
    <p>ပရင့်ထုတ်သည့်ရက်စွဲ: <?php echo date('Y-m-d H:i:s'); ?></p>
    <p>စုစုပေါင်းမှတ်တမ်း: <?php echo count($feed_data); ?> ခု</p>
</div>

</body>
</html>
