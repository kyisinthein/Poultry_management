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

$sql = "SELECT * FROM grand_total WHERE page_number = ? AND farm_id = ?";
$params = [$current_global_page, $farm_id];
if ($start_date && $end_date) {
    $sql .= " AND date BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
}
$sql .= " ORDER BY id ASC";
$grand_total_data = fetchAll($sql, $params);

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
header('Content-Disposition: attachment; filename="GrandTotal(' . $farm['farm_username'] . '_p' . $page . ').xls"');
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

<div class="title">Grand Total - <?php echo htmlspecialchars($farm['farm_username']); ?> - ခြံ(<?php echo $farm['farm_no']; ?>) - စာမျက်နှာ <?php echo $page; ?></div>

<?php if ($start_date && $end_date): ?>
<div style="text-align: center; margin-bottom: 15px;">ရက်စွဲ: <?php echo $start_date; ?> မှ <?php echo $end_date; ?> အထိ</div>
<?php endif; ?>

<table border="1">
    <thead>
        <tr>
            <th>စဉ်</th>
            <th>အမည်</th>
            <th>အမျိုးအစား</th>
            <th>အကောင်ရေ</th>
            <th>ရောင်း</th>
            <th>သေ</th>
            <th>ကြက် ပို/လို</th>
            <th>ကုန်ချိန်</th>
            <th>အစာချိန်</th>
            <th>ကုမ္ပဏီ</th>
            <th>စပ်</th>
            <th>အစာအိတ်</th>
            <th>ကျွေးပြီး အိတ်</th>
            <th>အစာ ပို/လို</th>
            <th>ဆေး</th>
            <th>အစာ</th>
            <th>အထွေထွေစရိတ်</th>
            <th>ဝိတ်တန်း</th>
            <th>အသေရာခိုင်နှုန်း</th>
            <th>FCR</th>
            <th>TFCR</th>
            <th>မှတ်ချက်</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($grand_total_data && count($grand_total_data) > 0): ?>
            <?php $i = 1; ?>
            <?php foreach ($grand_total_data as $row): ?>
            <tr>
                <td><?php echo $i++; ?></td>
                <td><?php echo htmlspecialchars($row['name'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['type'] ?? ''); ?></td>
                <td><?php echo fmt($row['quantity']); ?></td>
                <td><?php echo fmt($row['sold']); ?></td>
                <td><?php echo fmt($row['dead']); ?></td>
                <td><?php echo htmlspecialchars($row['excess_deficit'] ?? ''); ?></td>
                <td><?php echo fmt($row['finished_weight']); ?></td>
                <td><?php echo fmt($row['feed_weight']); ?></td>
                <td><?php echo htmlspecialchars($row['company'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['mixed'] ?? ''); ?></td>
                <td><?php echo fmt($row['feed_bag']); ?></td>
                <td><?php echo fmt($row['used_feed_bags']); ?></td>
                <td><?php echo fmt($row['feed_balance']); ?></td>
                <td><?php echo fmt($row['medicine']); ?></td>
                <td><?php echo fmt($row['feed']); ?></td>
                <td><?php echo fmt($row['other_cost']); ?></td>
                <td><?php echo fmt($row['avg_weight']); ?></td>
                <td><?php echo fmt($row['mortality_rate']); ?></td>
                <td><?php echo fmt($row['fcr']); ?></td>
                <td><?php echo fmt($row['tfcr']); ?></td>
                <td><?php echo htmlspecialchars($row['comments'] ?? ''); ?></td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="22" style="text-align: center;">ဒေတာမရှိပါ</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<div style="margin-top: 20px; font-size: 12px;">
    <p>ပရင့်ထုတ်သည့်ရက်စွဲ: <?php echo date('Y-m-d H:i:s'); ?></p>
    <p>စုစုပေါင်းမှတ်တမ်း: <?php echo count($grand_total_data); ?> ခု</p>
</div>

</body>
</html>
