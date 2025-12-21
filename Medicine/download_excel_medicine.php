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
// Logic similar to medicine.php but simplified for export
$current_global_page = $page; 
// Note: In medicine.php there is complex mapping logic. 
// If the user wants "current page", we should try to respect the mapping if possible, 
// but since we are receiving the 'page' param which usually represents the 'display page' in the UI,
// we might need to look up the mapping. 
// However, the 'page' parameter passed to this script will be the value from JS `currentPage`.
// In medicine.php: 
// $current_global_page = isset($page_mapping[$current_page]) ? $page_mapping[$current_page] : $page_mapping[1];
// We need to replicate that logic or just accept that we might need the mapping.
// Let's try to fetch the mapping logic briefly.

try {
    $pages_sql = "SELECT DISTINCT page_number FROM pagination WHERE farm_id = ? ORDER BY page_number ASC";
    $pages_result = fetchAll($pages_sql, [$farm_id]);
    $global_pages = array_column($pages_result, 'page_number');
    
    $page_mapping = [];
    foreach ($global_pages as $index => $global_page) {
        $display_page = $index + 1;
        $page_mapping[$display_page] = $global_page;
    }
    
    // If we have a mapping for the requested page, use it.
    // Otherwise fall back to the page number itself (or 1).
    if (isset($page_mapping[$page])) {
        $current_global_page = $page_mapping[$page];
    } else {
        // If exact page not found in mapping, maybe it is the global page?
        // Or just default to 1.
        // Let's assume if it's not in mapping, we might default to 1 or just use the number if it matches.
        // For safety, let's use the first page if not found, or just proceed.
        // But usually the UI sends the display page.
         $current_global_page = $page_mapping[1] ?? 1;
    }

} catch (Exception $e) {
    $current_global_page = 1;
}

// Fetch medicine data
$sql = "SELECT * FROM medicine_summary WHERE page_number = ? AND farm_id = ?";
$params = [$current_global_page, $farm_id];

if ($start_date && $end_date) {
    $sql .= " AND date BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
}

$sql .= " ORDER BY date ASC";
$medicine_data = fetchAll($sql, $params);

// Helper functions for display
function fmt($n){ if ($n === null || $n === '') return '0'; $s = (string)$n; if (strpos($s, '.') !== false) { $s = rtrim(rtrim($s, '0'), '.'); } return $s === '' ? '0' : $s; }
function displayAmount($val, $unit){ $v = fmt($val); return $unit ? ($v . ' ' . $unit) : $v; }

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="ဆေးစာရင်းချုပ်(' . $farm['farm_username'] . '_p' . $page . ').xls"');
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

<div class="title">ဆေးစာရင်းချုပ် - <?php echo htmlspecialchars($farm['farm_username']); ?> - ခြံ(<?php echo $farm['farm_no']; ?>) - စာမျက်နှာ <?php echo $page; ?></div>

<?php if ($start_date && $end_date): ?>
<div style="text-align: center; margin-bottom: 15px;">ရက်စွဲ: <?php echo $start_date; ?> မှ <?php echo $end_date; ?> အထိ</div>
<?php endif; ?>

<table border="1">
    <thead>
        <tr>
            <th>ရက်သား</th>
            <th>ဆေးအမျိုးအစား</th>
            <th>အရေအတွက်</th>
            <th>အကြိမ်ရေ</th>
            <th>စုစုပေါင်း</th>
            <th>ဈေးနှုန်း</th>
            <th>ကျသင့်ငွေ</th>
            <th>မှတ်ချက်</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($medicine_data && count($medicine_data) > 0): ?>
            <?php foreach ($medicine_data as $row): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['age_group'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['medicine_name'] ?? ''); ?></td>
                <td><?php echo displayAmount($row['dose_amount'], $row['dose_unit'] ?? ''); ?></td>
                <td><?php echo fmt($row['frequency']); ?></td>
                <td><?php echo displayAmount($row['total_used'], $row['total_used_unit'] ?? ''); ?></td>
                <td><?php echo fmt($row['unit_price']); ?></td>
                <td><?php echo fmt($row['total_cost']); ?></td>
                <td><?php echo htmlspecialchars($row['comments'] ?? ''); ?></td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="8" style="text-align: center;">ဒေတာမရှိပါ</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<div style="margin-top: 20px; font-size: 12px;">
    <p>ပရင့်ထုတ်သည့်ရက်စွဲ: <?php echo date('Y-m-d H:i:s'); ?></p>
    <p>စုစုပေါင်းမှတ်တမ်း: <?php echo count($medicine_data); ?> ခု</p>
</div>

</body>
</html>
