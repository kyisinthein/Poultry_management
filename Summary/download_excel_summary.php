<?php
include('../database.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$farm_id = $_GET['farm_id'] ?? $_SESSION['current_farm_id'] ?? 1;
$page = $_GET['page'] ?? 1;
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

$farm = fetchOne("SELECT * FROM farms WHERE id = ?", [$farm_id]);
if (!$farm) {
    die("Farm not found");
}

$current_global_page = 1;
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

$sql = "SELECT * FROM summary WHERE page_number = ? AND farm_id = ?";
$params = [$current_global_page, $farm_id];
if ($start_date && $end_date) {
    $sql .= " AND date BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
}
$sql .= " ORDER BY date ASC";
$summary_data = fetchAll($sql, $params);

function fmt($n)
{
    if ($n === null || $n === '') return '0';
    $s = (string)$n;
    if (strpos($s, '.') !== false) {
        $s = rtrim(rtrim($s, '0'), '.');
    }
    return $s === '' ? '0' : $s;
}

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="Summary(' . $farm['farm_username'] . '_p' . $page . ').xls"');
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

<div class="title">စာမျက်နှာ <?php echo $page; ?> - <?php echo htmlspecialchars($farm['farm_username']); ?> - ခြံ(<?php echo $farm['farm_no']; ?>)</div>

<?php if ($start_date && $end_date): ?>
<div style="text-align: center; margin-bottom: 15px;">ရက်စွဲ: <?php echo $start_date; ?> မှ <?php echo $end_date; ?> အထိ</div>
<?php endif; ?>

<table border="1">
    <thead>
        <tr>
            <th>အသက်</th>
            <th>ရက်စွဲ</th>
            <th>ကုမ္ပဏီဝင်</th>
            <th>စပ်စာဝင်</th>
            <th>အစာပေါင်း</th>
            <th>ကုမ္ပဏီကျန်</th>
            <th>စပ်စာကျန်</th>
            <th>နေ့စဉ်အစာစားနှုန်း</th>
            <th>စုစုပေါင်းအစာစားနှုန်း</th>
            <th>အလေးချိန်</th>
            <th>အသေ</th>
            <th>စုစုပေါင်းအသေ</th>
            <th>မှတ်ချက်</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($summary_data && count($summary_data) > 0): ?>
            <?php foreach ($summary_data as $row): ?>
            <tr>
                <td><?php echo fmt($row['age']); ?></td>
                <td><?php echo htmlspecialchars($row['date'] ?? ''); ?></td>
                <td><?php echo fmt($row['company_in']); ?></td>
                <td><?php echo fmt($row['mix_in']); ?></td>
                <td><?php echo fmt($row['total_feed']); ?></td>
                <td><?php echo fmt($row['company_left']); ?></td>
                <td><?php echo fmt($row['mix_left']); ?></td>
                <td><?php echo fmt($row['daily_rate']); ?></td>
                <td><?php echo fmt($row['cumulative_rate']); ?></td>
                <td><?php echo fmt($row['weight']); ?></td>
                <td><?php echo fmt($row['dead']); ?></td>
                <td><?php echo fmt($row['cumulative_dead']); ?></td>
                <td><?php echo htmlspecialchars($row['comments'] ?? ''); ?></td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="13" style="text-align: center;">ဒေတာမရှိပါ</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<div style="margin-top: 20px; font-size: 12px;">
    <p>ပရင့်ထုတ်သည့်ရက်စွဲ: <?php echo date('Y-m-d H:i:s'); ?></p>
    <p>စုစုပေါင်းမှတ်တမ်း: <?php echo count($summary_data); ?> ခု</p>
</div>

</body>
</html>
