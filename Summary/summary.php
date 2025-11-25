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
        foreach ($page_types as $t) {
            executeQuery('INSERT INTO pagination (page_number, page_type, farm_id) VALUES (?, ?, ?)', [$new_global_page, $t, $current_farm_id]);
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
        $max_page_sql = 'SELECT MAX(page_number) as max_page FROM pagination';
        $max_page_result = fetchOne($max_page_sql);
        $new_page = ($max_page_result['max_page'] ?: 0) + 1;
        $page_types = ['summary', 'food', 'sales', 'medicine', 'grand-total'];
        foreach ($page_types as $type) {
            executeQuery('INSERT INTO pagination (page_number, page_type, farm_id) VALUES (?, ?, ?)', [$new_page, $type, $current_farm_id]);
        }
        header('Location: summary.php?page=' . ($total_pages + 1) . '&farm_id=' . $current_farm_id);
        exit();
    } catch (Exception $e) {
    }
}

if (isset($_POST['delete_current_page'])) {
    try {
        $current_page_to_delete = $_POST['page'] ?? 1;
        $farm_id_to_delete = $_POST['farm_id'] ?? $current_farm_id;
        $global_page_to_delete = $_POST['global_page'] ?? $current_global_page;
        if ($current_page_to_delete == 1) {
            $_SESSION['error'] = 'စာမျက်နှာ ၁ ကိုဖျက်လို့မရပါ';
            header('Location: summary.php?page=' . $current_page_to_delete . '&farm_id=' . $farm_id_to_delete);
            exit();
        }
        if (!$global_page_to_delete) {
            $_SESSION['error'] = 'စာမျက်နှာရှာမတွေ့ပါ';
            header('Location: summary.php?page=1&farm_id=' . $farm_id_to_delete);
            exit();
        }
        $page_types = ['summary', 'food', 'sales', 'medicine', 'grand-total'];
        $deleted_count = 0;
        foreach ($page_types as $type) {
            $delete_sql = 'DELETE FROM pagination WHERE page_type = ? AND page_number = ? AND farm_id = ?';
            $stmt = $pdo->prepare($delete_sql);
            $stmt->execute([$type, $global_page_to_delete, $farm_id_to_delete]);
            $deleted_count += $stmt->rowCount();
        }
        $stmt2 = $pdo->prepare('DELETE FROM summary WHERE page_number = ? AND farm_id = ?');
        $stmt2->execute([$global_page_to_delete, $farm_id_to_delete]);
        if ($deleted_count > 0) $_SESSION['success'] = 'စာမျက်နှာ ' . $current_page_to_delete . ' ကိုဖျက်ပြီးပါပြီ';
        else $_SESSION['error'] = 'စာမျက်နှာဖျက်ရန် မအောင်မြင်ပါ';
        header('Location: summary.php?page=1&farm_id=' . $farm_id_to_delete);
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error deleting page: ' . $e->getMessage();
        header('Location: summary.php?page=' . (isset($_POST['page']) ? intval($_POST['page']) : 1) . '&farm_id=' . $current_farm_id);
        exit();
    }
}

$table_check = fetchOne("SHOW TABLES LIKE 'summary'");
if (!$table_check) {
    $create_sql = "
  CREATE TABLE IF NOT EXISTS summary (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    chicken_type ENUM('CP', 'KBZ') NOT NULL DEFAULT 'CP',
    age INT(11) DEFAULT 0,
    date DATE NOT NULL,

    company_in DECIMAL(14,2) DEFAULT 0,
    mix_in DECIMAL(14,2) DEFAULT 0,
    total_feed DECIMAL(14,2) DEFAULT 0,

    company_left DECIMAL(14,2) DEFAULT 0,
    mix_left DECIMAL(14,2) DEFAULT 0,

    daily_rate DECIMAL(14,2) DEFAULT 0,
    cumulative_rate DECIMAL(14,2) DEFAULT 0,

    weight DECIMAL(10,2) DEFAULT 0,

    dead INT(11) DEFAULT 0,
    cumulative_dead INT(11) DEFAULT 0,

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


$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;
$sql = 'SELECT * FROM summary WHERE page_number = ? AND farm_id = ?';
$params = [$current_global_page, $current_farm_id];
if ($start_date && $end_date) {
    $sql .= ' AND date BETWEEN ? AND ?';
    $params[] = $start_date;
    $params[] = $end_date;
}
$sql .= ' ORDER BY date ASC';
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
function displayAmount($val, $unit)
{
    $v = fmt($val);
    return $unit ? ($v . ' ' . $unit) : $v;
}
function extractUnitText($text)
{
    $m = [];
    if (preg_match('/\b(cc|g|kg)\b/i', $text, $m)) return strtolower($m[1]);
    return '';
}
function parseAmount($text)
{
    $value = 0;
    $unit = extractUnitText($text);
    $value = floatval(preg_replace('/[^0-9.\-]/', '', $text));
    return [$value, $unit];
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> <?php echo htmlspecialchars($current_farm['farm_username'] ?? 'Default Farm'); ?> - ခြံ(<?php echo $current_farm['farm_no'] ?? 1; ?>)</title>
    <link rel="stylesheet" href="../assets/css/sell.css">
    <link rel="stylesheet" href="../assets/css/medicine.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
    <div class="sell_container">
        <?php include('../sidebar.php'); ?>
        <div class="main-content">
            <div class="content-header">
                <h1>စာမျက်နှာ <?php echo $current_page; ?> <?php echo htmlspecialchars($current_farm['farm_username'] ?? 'Default Farm'); ?> - ခြံ(<?php echo $current_farm['farm_no'] ?? 1; ?>)</h1>
                <div class="header-actions">
                    <button class="btn btn-primary" id="addRow"><i class="fas fa-plus"></i> rowအသစ်ထပ်ယူရန်</button>
                    <button class="btn btn-secondary" id="saveAll"><i class="fas fa-save"></i> Save-all</button>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="farm_id" value="<?php echo $current_farm_id; ?>">
                        <button type="submit" name="add_new_table" class="btn btn-primary"><i class="fas fa-plus"></i> ဇယားအသစ်ထပ်ယူရန်</button>
                    </form>
                    <?php if ($current_page > 1): ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirmDeletePage();">
                            <input type="hidden" name="page" value="<?php echo $current_page; ?>">
                            <input type="hidden" name="farm_id" value="<?php echo $current_farm_id; ?>">
                            <input type="hidden" name="global_page" value="<?php echo $current_global_page; ?>">
                            <button type="submit" name="delete_current_page" class="btn btn-delete-page"><i class="fas fa-trash"></i> ဒီစာမျက်နှာကိုဖျက်ရန် (စာမျက်နှာ <?php echo $current_page; ?>)</button>
                        </form>
                    <?php endif; ?>
                    <button class="btn btn-danger" id="deleteAllData"><i class="fas fa-trash"></i> Delete-all (ဒေတာအားလုံး)</button>
                </div>
            </div>
            <div class="search-section">
                <div class="search-container">
                    <div class="user-info-bar">
                        <span>လက်ရှိအသုံးပြုသူ: <strong><?php echo htmlspecialchars($user['username']); ?></strong></span><br>
                        <span>အဆင့်: <strong><?php echo htmlspecialchars($user['role']); ?></strong></span>
                    </div>
                    <div class="search-group">
                        <label for="startDate">စတင်ရက်စွဲ</label>
                        <input type="date" id="startDate" class="date-input" value="<?php echo htmlspecialchars($start_date ?? ''); ?>">
                    </div>
                    <div class="search-group">
                        <label for="endDate">ပြီးဆုံးရက်စွဲ</label>
                        <input type="date" id="endDate" class="date-input" value="<?php echo htmlspecialchars($end_date ?? ''); ?>">
                    </div>
                    <button class="btn btn-search" id="btnSearch"><i class="fas fa-search"></i> ရှာဖွေရန်</button>
                    <button class="btn btn-clear" id="btnClear"><i class="fas fa-redo"></i> ရှင်းလင်းရန်</button>
                </div>
            </div>
            <div class="table-container">
                <?php include('../pagination.php'); ?>
                <table id="medicineTable">
                    <thead>
                        <tr style="height: 100px;">
                            <th colspan="15"><?php echo htmlspecialchars($current_farm['farm_username'] ?? 'Default Farm'); ?> - ခြံ(<?php echo $current_farm['farm_no'] ?? 1; ?>) - စာမျက်နှာ <?php echo $current_page; ?></th>
                        </tr>
                        <tr>
                            <th colspan="3"><?php
                                            $headerDate = '';
                                            if (!empty($summary_data) && !empty($summary_data[0]['date'])) {
                                                $currentDate = $summary_data[0]['date']; // first row date
                                                $headerDate = date('Y-m-d', strtotime($currentDate . ' -1 day'));
                                            }
                                            echo htmlspecialchars($headerDate);
                                            ?>
                            </th>
                            <th colspan="6" class="editable-header" data-field="chicken_type">ကြက်အမျိုးအစား CP</th>
                            <th class="editable-header" data-field="initial_count">
                                <?php
                                try {
                                    $initial_count_sql = "SELECT initial_count FROM sales_summary WHERE page_number = ? AND farm_id = ? ORDER BY date ASC LIMIT 1";
                                    $initial_count_result = fetchOne($initial_count_sql, [$current_global_page, $current_farm_id]);
                                    echo $initial_count_result ? $initial_count_result['initial_count'] : '0';
                                } catch (Exception $e) {
                                    echo '0';
                                }
                                ?>
                            </th>
                            <th colspan="2">
                                <?php
                                $headerTime = '00:00';
                                if (!empty($summary_data[0]['date'])) {
                                    $datePart = $summary_data[0]['date'];
                                    $headerTime = date('H:00', strtotime($datePart . ' 16:00:00'));
                                }
                                echo htmlspecialchars($headerTime);
                                ?>


                            </th>

                            <th colspan="6"></th>

                        </tr>
                        <tr style="height: 90px;">
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
                            <th>ပြင်ရန်</th>
                            <th>ဖျက်ရန်</th>
                            <th>မှတ်ချက်</th>

                        </tr>
                    </thead>
                    <tbody id="summaryTableBody">
                        <?php if ($summary_data && count($summary_data) > 0): ?>
                            <?php foreach ($summary_data as $row): ?>
                                <tr data-id="<?php echo $row['id']; ?>">

                                    <td class="editable" data-field="age">
                                        <?php echo htmlspecialchars($row['age'] ?? ''); ?>
                                    </td>

                                    <td class="editable" data-field="date">
                                        <?php echo htmlspecialchars(date('Y-m-d', strtotime($row['date']))); ?>
                                    </td>

                                    <td class="editable" data-field="company_in">
                                        <?php echo fmt($row['company_in']); ?>
                                    </td>

                                    <td class="editable" data-field="mix_in">
                                        <?php echo fmt($row['mix_in']); ?>
                                    </td>

                                    <td class="editable" data-field="total_feed">
                                        <?php echo fmt($row['total_feed']); ?>
                                    </td>

                                    <td class="editable" data-field="company_left">
                                        <?php echo fmt($row['company_left']); ?>
                                    </td>

                                    <td class="editable" data-field="mix_left">
                                        <?php echo fmt($row['mix_left']); ?>
                                    </td>

                                    <td class="editable" data-field="daily_rate">
                                        <?php echo fmt($row['daily_rate']); ?>
                                    </td>

                                    <td class="editable" data-field="cumulative_rate">
                                        <?php echo fmt($row['cumulative_rate']); ?>
                                    </td>

                                    <td class="editable" data-field="weight">
                                        <?php echo fmt($row['weight']); ?>
                                    </td>

                                    <td class="editable" data-field="dead">
                                        <?php echo fmt($row['dead']); ?>
                                    </td>

                                    <td class="editable" data-field="cumulative_dead">
                                        <?php echo fmt($row['cumulative_dead']); ?>
                                    </td>

                                    <td><button class="save-btn saved">သိမ်းပြီး</button></td>
                                    <td><button class="btn-delete" data-id="<?php echo $row['id']; ?>">ဖျက်ရန်</button></td>
                                    <td class="comment-cell">
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
                                                if ($user['role'] === 'admin') {
                                                    $badge_class = 'badge-comment-admin';
                                                } else {
                                                    $badge_class = !empty($row['comment_read']) ? 'badge-comment-read' : 'badge-comment-unread';
                                                }
                                            }
                                            ?>
                                            <button class="btn-comment" data-id="<?php echo $row['id']; ?>"
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
                            <tr>
                                <td colspan="15" style="text-align:center;padding:20px;">ဒေတာမရှိပါ။ row အသစ်ထပ်ယူပါ။</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot style="height: 80px;">
                        <tr>
                            <th colspan="4" style="font-weight: 400;">စုစုပေါင်း</th>
                            <th id="sumTotalFeed">0</th>
                            <th></th>
                            <th></th>
                            <th id="sumDailyRate">0</th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th id="sumCumulativeDead">0</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    <script src="../assets/js/sales_manager.js"></script>
    <script>
        const currentFarmId = <?php echo $current_farm_id; ?>;
        const currentPage = <?php echo $current_page; ?>;
        const currentGlobalPage = <?php echo $current_global_page; ?>;
        let startDate = <?php echo json_encode($start_date ?? null); ?>;
        let endDate = <?php echo json_encode($end_date ?? null); ?>;

        let prev_total_feed = 0;
        let prev_cumulative_rate = 0;
        let prev_cumulative_dead = 0;

        function recalcRow(row) {
            const companyIn = parseFloat(row.querySelector('[data-field="company_in"]').textContent) || 0;
            const mixIn = parseFloat(row.querySelector('[data-field="mix_in"]').textContent) || 0;
            const companyLeft = parseFloat(row.querySelector('[data-field="company_left"]').textContent) || 0;
            const mixLeft = parseFloat(row.querySelector('[data-field="mix_left"]').textContent) || 0;
            const weight = parseFloat(row.querySelector('[data-field="weight"]').textContent) || 0;
            const dead = parseInt(row.querySelector('[data-field="dead"]').textContent) || 0;

            // Total feed
            const totalFeed = companyIn + mixIn + prev_total_feed;
            prev_total_feed = totalFeed;
            row.querySelector('[data-field="total_feed"]').textContent = totalFeed.toFixed(2);

            // Cumulative eat rate
            const cumulativeRate = totalFeed - companyLeft - mixLeft;
            row.querySelector('[data-field="cumulative_rate"]').textContent = cumulativeRate.toFixed(2);

            // Daily eat rate
            const dailyRate = cumulativeRate - prev_cumulative_rate;
            prev_cumulative_rate = cumulativeRate;
            row.querySelector('[data-field="daily_rate"]').textContent = dailyRate.toFixed(2);

            // Cumulative dead
            const cumulativeDead = dead + prev_cumulative_dead;
            prev_cumulative_dead = cumulativeDead;
            row.querySelector('[data-field="cumulative_dead"]').textContent = cumulativeDead;

            // Weight
            row.querySelector('[data-field="weight"]').textContent = weight.toFixed(2);
        }

        function recalcTotals() {
            let sumTotalFeed = 0;
            let sumDailyRate = 0;
            let sumCumulativeDead = 0;
            let sumWeight = 0;

            document.querySelectorAll('#summaryTableBody tr').forEach(row => {
                sumTotalFeed += parseFloat(row.querySelector('[data-field="total_feed"]').textContent) || 0;
                sumDailyRate += parseFloat(row.querySelector('[data-field="daily_rate"]').textContent) || 0;
                sumCumulativeDead += parseInt(row.querySelector('[data-field="cumulative_dead"]').textContent) || 0;
                sumWeight += parseFloat(row.querySelector('[data-field="weight"]').textContent) || 0;
            });

            document.getElementById('sumTotalFeed').textContent = sumTotalFeed.toFixed(2);
            document.getElementById('sumDailyRate').textContent = sumDailyRate.toFixed(2);
            document.getElementById('sumCumulativeDead').textContent = sumCumulativeDead;
            document.getElementById('sumWeight').textContent = sumWeight.toFixed(2);
        }

        // Recalculate everything
        function recalcAll() {
            prev_total_feed = 0;
            prev_cumulative_rate = 0;
            prev_cumulative_dead = 0;

            document.querySelectorAll('#summaryTableBody tr').forEach(row => {
                recalcRow(row);
            });

            recalcTotals();
        }


        function startEditing(cell) {
            if (cell.querySelector('input')) return;
            const field = cell.getAttribute('data-field');
            const val = cell.textContent.trim();
            let input = `<input type="text" class="edit-input" value="${val}">`;
            cell.innerHTML = input;
            const el = cell.querySelector('input');
            el.focus();
            el.select();
            el.addEventListener('blur', () => finishEditing(cell));
            el.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') finishEditing(cell);
                if (e.key === 'Escape') cancelEditing(cell);
            });
        }

        function markRowPending(row) {
            const btn = row.querySelector('.save-btn');
            if (!btn) return;
            btn.textContent = 'သိမ်းရန်';
            btn.classList.add('pending');
            btn.classList.remove('saved');
        }

        function markRowSaved(row) {
            const btn = row.querySelector('.save-btn');
            if (!btn) return;
            btn.textContent = 'သိမ်းပြီး';
            btn.classList.remove('pending');
            btn.classList.add('saved');
        }

        function finishEditing(cell) {
            const el = cell.querySelector('input');
            const val = el ? el.value : '';
            cell.textContent = val;
            const row = cell.closest('tr');
            recalcRow(row);
            recalcTotals();
            markRowPending(row);
        }

        function cancelEditing(cell) {
            const el = cell.querySelector('input');
            cell.textContent = el ? el.defaultValue : cell.textContent;
        }

        function getRowData(row) {
            const data = {};

            row.querySelectorAll('[data-field]').forEach(c => {
                const f = c.getAttribute('data-field');
                let txt = c.textContent.trim();

                const numericFields = [
                    'company_in', 'mix_in', 'total_feed',
                    'company_left', 'mix_left', 'daily_rate',
                    'cumulative_rate', 'weight', 'dead', 'cumulative_dead'
                ];

                if (numericFields.includes(f)) {
                    data[f] = parseFloat(txt) || 0;
                } else {
                    data[f] = txt;
                }
            });

            data.id = row.getAttribute('data-id') || null;
            data.page_number = currentGlobalPage;
            data.farm_id = currentFarmId;
            data.timestamp = new Date().toISOString();

            return data;
        }


        function sendRow(row) {
            const payload = getRowData(row);
            return fetch('save_summary.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            }).then(r => r.json());
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('summaryTableBody').addEventListener('dblclick', (e) => {
                const cell = e.target.closest('td.editable');
                if (cell) startEditing(cell);
            });
            document.getElementById('summaryTableBody').addEventListener('click', (e) => {
                const btnSave = e.target.closest('.save-btn');
                if (btnSave) {
                    const row = btnSave.closest('tr');
                    sendRow(row).then(res => {
                        if (res && res.success) {
                            if (res.id) row.setAttribute('data-id', res.id);
                            markRowSaved(row);
                            alert('အောင်မြင်စွာသိမ်းဆည်းပြီး');
                        } else {
                            alert('သိမ်းရာတွင် အမှားရှိသည်');
                        }
                    }).catch(() => alert('Network error'));
                }
                const btnDel = e.target.closest('.btn-delete');
                if (btnDel) {
                    const row = btnDel.closest('tr');
                    const id = row.getAttribute('data-id');
                    if (!id) {
                        row.remove();
                        recalcTotals();
                        return;
                    }
                    if (!confirm('ဤအချက်အလက်ကိုဖျက်မှာသေချာပါသလား?')) return;
                    fetch('delete_summary.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                id
                            })
                        })
                        .then(r => {
                            console.log(r.status, r.statusText);
                            return r.text();
                        })
                        .then(txt => {
                            console.log('Raw response:', txt);
                            return JSON.parse(txt);
                        })
                        .then(res => {
                            if (res.success) {
                                row.remove();
                                // recalcTotals();
                                alert('ဖျက်ပြီးပါပြီ');
                            } else {
                                alert('ဖျက်ရာတွင် အမှားရှိသည်');
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            alert('Network error');
                        });


                }
            });
            document.getElementById('addRow').addEventListener('click', () => {
                const tbody = document.getElementById('summaryTableBody');
                const tr = document.createElement('tr');

                const now = new Date();
                const formattedDate = now.getFullYear() + "-" +
                    String(now.getMonth() + 1).padStart(2, '0') + "-" +
                    String(now.getDate()).padStart(2, '0') + " " +
                    String(now.getHours()).padStart(2, '0') + ":" +
                    String(now.getMinutes()).padStart(2, '0');

                tr.innerHTML = `
        <td class="editable" data-field="age"></td>
        <td class="editable" data-field="date">${formattedDate}</td>
        <td class="editable" data-field="company_in">0</td>
        <td class="editable" data-field="mix_in">0</td>
        <td class="editable" data-field="total_feed">0</td>
        <td class="editable" data-field="company_left">0</td>
        <td class="editable" data-field="mix_left">0</td>
        <td class="editable" data-field="daily_rate">0</td>
        <td class="editable" data-field="cumulative_rate">0</td>
        <td class="editable" data-field="weight">0</td>
        <td class="editable" data-field="dead">0</td>
        <td class="editable" data-field="cumulative_dead">0</td>

        <td><button class="save-btn pending">သိမ်းရန်</button></td>
        <td><button class="btn-delete">ဖျက်ရန်</button></td>

        <td class="comment-cell">
            <div class="comment-container">
                <button class="btn-comment" data-id="">
                    <i class="fa-regular fa-comment"></i>
                </button>
            </div>
        </td>
    `;

                tbody.appendChild(tr);
                if (typeof recalcSummaryTotals === "function") {
                    recalcSummaryTotals();
                }
            });

            document.getElementById('saveAll').addEventListener('click', () => {
                const rows = Array.from(document.querySelectorAll('#summaryTableBody tr'));
                const payload = rows.map(getRowData);
                fetch('save_bulk_summary.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        items: payload,
                        farm_id: currentFarmId,
                        page_number: currentGlobalPage
                    })
                }).then(r => r.json()).then(res => {
                    if (res && res.success) {
                        alert('ဒေတာအားလုံးသိမ်းပြီး');
                        location.href = `summary.php?page=${currentPage}&farm_id=${currentFarmId}${startDate&&endDate?`&start_date=${startDate}&end_date=${endDate}`:''}`;
                    } else {
                        alert(res && res.error ? ('သိမ်းရာတွင် အမှားရှိသည်: ' + res.error) : 'သိမ်းရာတွင် အမှားရှိသည်');
                    }
                }).catch(() => alert('Network error'));
            });
            document.querySelectorAll('#summaryTableBody tr').forEach(recalcRow);
            recalcTotals();

            const searchBtn = document.getElementById('btnSearch');
            const clearBtn = document.getElementById('btnClear');
            const startInput = document.getElementById('startDate');
            const endInput = document.getElementById('endDate');
            searchBtn.addEventListener('click', () => {
                const s = startInput.value;
                const e = endInput.value;
                if (s && e) {
                    window.location.href = `summary.php?page=${currentPage}&farm_id=${currentFarmId}&start_date=${s}&end_date=${e}`;
                } else {
                    alert('ကျေးဇူးပြု၍ ရက်စွဲနှစ်ခုလုံးထည့်ပါ');
                }
            });
            clearBtn.addEventListener('click', () => {
                startInput.value = '';
                endInput.value = '';
                window.location.href = `summary.php?page=${currentPage}&farm_id=${currentFarmId}`;
            });

            const pageLinks = document.querySelectorAll('.page-btn');
            pageLinks.forEach(link => {
                if (link.href) {
                    const url = new URL(link.href);
                    url.searchParams.set('farm_id', currentFarmId);
                    if (startDate && endDate) {
                        url.searchParams.set('start_date', startDate);
                        url.searchParams.set('end_date', endDate);
                    }
                    link.href = url.toString();
                }
            });

            const deleteAllBtn = document.getElementById('deleteAllData');
            deleteAllBtn.addEventListener('click', () => {
                if (!confirm('ဤစာမျက်နှာရှိ ဒေတာအားလုံးကိုဖျက်မှာသေချာပါသလား?')) return;
                fetch('delete_all_summary.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        page_number: currentPage,
                        farm_id: currentFarmId
                    })
                }).then(r => r.json()).then(res => {
                    if (res && res.success) {
                        alert('ဒေတာအားလုံးဖျက်ပြီးပါပြီ');
                        location.href = `summary.php?page=${currentPage}&farm_id=${currentFarmId}`;
                    } else {
                        alert('ဖျက်ရာတွင် အမှားရှိသည်');
                    }
                }).catch(() => alert('Network error'));
            });

            const pagWrap = document.querySelector('.pagination-wrap');
            if (pagWrap) {
                const quick = document.createElement('div');
                quick.className = 'quick-actions';
                quick.innerHTML = `
      <button class="btn btn-primary" id="addRowQuick"><i class="fas fa-plus"></i> .</button>
      <button class="btn btn-secondary" id="saveAllQuick"><i class="fas fa-save"></i>.</button>
    `;
                pagWrap.insertBefore(quick, pagWrap.firstChild);
                const addQuick = document.getElementById('addRowQuick');
                const saveQuick = document.getElementById('saveAllQuick');
                addQuick.addEventListener('click', () => {
                    const original = document.getElementById('addRow');
                    if (original) original.click();
                });
                saveQuick.addEventListener('click', () => {
                    const original = document.getElementById('saveAll');
                    if (original) original.click();
                });
            }
        });
    </script>
    <script>
        const searchBtn = document.getElementById('btnSearch');
        const clearBtn = document.getElementById('btnClear');
        const startInput = document.getElementById('startDate');
        const endInput = document.getElementById('endDate');

        searchBtn.addEventListener('click', () => {
            const s = startInput.value;
            const e = endInput.value;
            if (s && e) {
                window.location.href = `summary.php?page=${currentPage}&farm_id=${currentFarmId}&start_date=${s}&end_date=${e}`;
            } else {
                alert('ကျေးဇူးပြု၍ ရက်စွဲနှစ်ခုလုံးထည့်ပါ');
            }
        });

        clearBtn.addEventListener('click', () => {
            startInput.value = '';
            endInput.value = '';
            window.location.href = `summary.php?page=${currentPage}&farm_id=${currentFarmId}`;
        });

        const pageLinks = document.querySelectorAll('.page-btn');
        pageLinks.forEach(link => {
            if (link.href) {
                const url = new URL(link.href);
                url.searchParams.set('farm_id', currentFarmId);
                if (startDate && endDate) {
                    url.searchParams.set('start_date', startDate);
                    url.searchParams.set('end_date', endDate);
                }
                link.href = url.toString();
            }
        });

        const deleteAllBtn = document.getElementById('deleteAllData');
        deleteAllBtn.addEventListener('click', () => {
            if (!confirm('ဤစာမျက်နှာရှိ ဒေတာအားလုံးကိုဖျက်မှာသေချာပါသလား?')) return;

            fetch('delete_all_summary.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        page_number: currentPage,
                        farm_id: currentFarmId
                    })
                })
                .then(r => r.json())
                .then(res => {
                    if (res && res.success) {
                        alert('ဒေတာအားလုံးဖျက်ပြီးပါပြီ');
                        location.href = `summary.php?page=${currentPage}&farm_id=${currentFarmId}`;
                    } else {
                        alert('ဖျက်ရာတွင် အမှားရှိသည်');
                    }
                })
                .catch(() => alert('Network error'));
        });
    </script>

    <script>
        (function() {
            const root = document.querySelector('.sell_container');
            const btn = document.getElementById('toggleFullscreen');
            if (!btn || !root) return;

            function setState(active) {
                root.classList.toggle('fullscreen', active);
                document.body.classList.toggle('fullscreen-mode', active);
                const icon = btn.querySelector('i');
                if (active) {
                    icon.classList.remove('fa-expand');
                    icon.classList.add('fa-compress');
                    btn.title = 'ပုံမှန်အရွယ်သို့ပြန်ရန်';
                    try {
                        localStorage.setItem('medicineFullscreen', '1');
                    } catch (e) {}
                } else {
                    icon.classList.remove('fa-compress');
                    icon.classList.add('fa-expand');
                    btn.title = 'ကြည့်ရန်ကျယ်';
                    try {
                        localStorage.setItem('medicineFullscreen', '0');
                    } catch (e) {}
                }
            }
            btn.addEventListener('click', function() {
                const active = !root.classList.contains('fullscreen');
                setState(active);
            });
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && root.classList.contains('fullscreen')) setState(false);
            });
            try {
                const stored = localStorage.getItem('medicineFullscreen');
                if (stored === '1') setState(true);
            } catch (e) {}
        })();
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
        document.addEventListener('DOMContentLoaded', function() {
            const commentModal = document.getElementById('commentModal');
            const commentText = document.getElementById('commentText');
            const commentModalTitle = document.getElementById('commentModalTitle');
            const commentInfo = document.getElementById('commentInfo');
            const btnCommentSave = document.getElementById('btnCommentSave');
            const btnCommentMarkRead = document.getElementById('btnCommentMarkRead');
            const btnCommentCancel = document.getElementById('btnCommentCancel');
            const closeBtn = document.querySelector('.comment-modal-close');
            let currentCommentRowId = null;
            const currentUserRole = '<?php echo $user["role"]; ?>';
            const currentUserId = <?php echo $user["id"]; ?>;
            document.addEventListener('click', function(e) {
                if (e.target.closest('.btn-comment')) {
                    const btn = e.target.closest('.btn-comment');
                    currentCommentRowId = btn.getAttribute('data-id') || '';
                    const hasComment = (btn.getAttribute('data-has-comment') || '0') === '1';
                    const commentRead = (btn.getAttribute('data-comment-read') || '0') === '1';
                    const currentComment = btn.getAttribute('data-current-comment') || '';
                    const commentAuthor = btn.getAttribute('data-comment-author') || '';
                    const commentDate = btn.getAttribute('data-comment-date') || '';
                    openCommentModal(currentCommentRowId, hasComment, commentRead, currentComment, commentAuthor, commentDate);
                }
            });

            function openCommentModal(rowId, hasComment, commentRead, currentComment, commentAuthor, commentDate) {
                currentCommentRowId = rowId;
                commentText.value = currentComment;
                if (currentUserRole === 'admin') {
                    commentModalTitle.textContent = 'မှတ်ချက်ပေးရန်';
                    commentText.readOnly = false;
                    btnCommentSave.style.display = 'inline-block';
                    btnCommentMarkRead.style.display = 'none';
                    if (hasComment) {
                        commentInfo.textContent = `မှတ်ချက်ရေးထားသူ: ${commentAuthor} | ရက်စွဲ: ${commentDate}`;
                        commentInfo.style.display = 'block';
                    } else {
                        commentInfo.style.display = 'none';
                    }
                } else {
                    commentModalTitle.textContent = 'မှတ်ချက်ကြည့်ရန်';
                    commentText.readOnly = true;
                    btnCommentSave.style.display = 'none';
                    if (hasComment) {
                        commentInfo.textContent = `မှတ်ချက်ရေးထားသူ: ${commentAuthor} | ရက်စွဲ: ${commentDate}`;
                        commentInfo.style.display = 'block';
                        if (!commentRead) btnCommentMarkRead.style.display = 'inline-block';
                        else btnCommentMarkRead.style.display = 'none';
                    } else {
                        commentInfo.textContent = 'မှတ်ချက်မရှိပါ';
                        commentInfo.style.display = 'block';
                        btnCommentMarkRead.style.display = 'none';
                    }
                }
                commentModal.style.display = 'block';
            }
            btnCommentSave.addEventListener('click', function() {
                if (!currentCommentRowId) {
                    alert('ကျေးဇူးပြု၍ ဒီ row ကိုအရင် save လုပ်ပါ (Save-all ကိုနှိပ်ပါ)');
                    return;
                }
                const comment = commentText.value.trim();
                const originalText = btnCommentSave.textContent;
                btnCommentSave.innerHTML = '<i class="fas fa-spinner fa-spin"></i> သိမ်းဆည်းနေသည်...';
                btnCommentSave.disabled = true;
                fetch('save_comment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        row_id: currentCommentRowId,
                        comment,
                        user_id: currentUserId
                    })
                }).then(r => r.json()).then(result => {
                    if (result && result.success) {
                        const commentBtn = document.querySelector(`.btn-comment[data-id="${currentCommentRowId}"]`);
                        if (commentBtn) {
                            commentBtn.setAttribute('data-has-comment', '1');
                            commentBtn.setAttribute('data-comment-read', '0');
                            commentBtn.setAttribute('data-current-comment', comment);
                            commentBtn.setAttribute('data-comment-author', '<?php echo $user['username']; ?>');
                            commentBtn.setAttribute('data-comment-date', new Date().toISOString());
                            let badge = commentBtn.querySelector('.comment-badge');
                            if (!badge) {
                                badge = document.createElement('span');
                                badge.className = 'comment-badge';
                                commentBtn.appendChild(badge);
                            }
                            badge.className = 'comment-badge badge-comment-admin';
                        }
                        alert('မှတ်ချက်သိမ်းဆည်းပြီး');
                        commentModal.style.display = 'none';
                    } else {
                        alert('မှတ်ချက်သိမ်းရာတွင် အမှားရှိသည်');
                    }
                }).catch(() => alert('Network error')).finally(() => {
                    btnCommentSave.textContent = originalText;
                    btnCommentSave.disabled = false;
                });
            });
            btnCommentMarkRead.addEventListener('click', function() {
                if (!currentCommentRowId) return;
                fetch('mark_comment_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        row_id: currentCommentRowId
                    })
                }).then(r => r.json()).then(result => {
                    if (result && result.success) {
                        const commentBtn = document.querySelector(`.btn-comment[data-id="${currentCommentRowId}"]`);
                        if (commentBtn) {
                            commentBtn.setAttribute('data-comment-read', '1');
                            const badge = commentBtn.querySelector('.comment-badge');
                            if (badge) badge.className = 'comment-badge badge-comment-read';
                        }
                        btnCommentMarkRead.style.display = 'none';
                        alert('မှတ်ချက်ဖတ်ပြီးအမှတ်အသားပြုထားပြီး');
                    } else {
                        alert('အမှတ်အသားပြုရာတွင် အမှားရှိသည်');
                    }
                }).catch(() => alert('Network error'));
            });

            function closeCommentModal() {
                commentModal.style.display = 'none';
                currentCommentRowId = null;
                commentText.value = '';
            }
            btnCommentCancel.addEventListener('click', closeCommentModal);
            closeBtn.addEventListener('click', closeCommentModal);
            window.addEventListener('click', function(e) {
                if (e.target === commentModal) closeCommentModal();
            });
        });
    </script>
</body>

</html>