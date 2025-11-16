<?php
include('database.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get current user data
$user = fetchOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);

// Handle delete single record - ADMIN ONLY
if (isset($_POST['delete_record'])) {
    // Only admin can delete records
    if ($_SESSION['role'] !== 'admin') {
        $_SESSION['error'] = "မှတ်တမ်းဖျက်ရန် ခွင့်ပြုချက်မရှိပါ။";
        header("Location: history.php");
        exit();
    }
    
    $record_id = intval($_POST['record_id']);
    
    try {
        $delete_sql = "DELETE FROM history_logs WHERE id = ?";
        $stmt = $pdo->prepare($delete_sql);
        $stmt->execute([$record_id]);
        
        $_SESSION['success'] = "မှတ်တမ်းအောင်မြင်စွာဖျက်ပြီးပါပြီ။";
        header("Location: history.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "မှတ်တမ်းဖျက်ရာတွင်အမှားတစ်ခုဖြစ်သည်: " . $e->getMessage();
        header("Location: history.php");
        exit();
    }
}

// Handle delete all records - ADMIN ONLY
if (isset($_POST['delete_all_records'])) {
    // Only admin can delete all records
    if ($_SESSION['role'] !== 'admin') {
        $_SESSION['error'] = "မှတ်တမ်းအားလုံးဖျက်ရန် ခွင့်ပြုချက်မရှိပါ။";
        header("Location: history.php");
        exit();
    }
    
    try {
        $delete_sql = "DELETE FROM history_logs";
        $stmt = $pdo->prepare($delete_sql);
        $stmt->execute();
        
        $_SESSION['success'] = "မှတ်တမ်းအားလုံးအောင်မြင်စွာဖျက်ပြီးပါပြီ။";
        header("Location: history.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "မှတ်တမ်းအားလုံးဖျက်ရာတွင်အမှားတစ်ခုဖြစ်သည်: " . $e->getMessage();
        header("Location: history.php");
        exit();
    }
}

// Pagination
$records_per_page = 20;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $records_per_page;

// Get total records count
$count_sql = "SELECT COUNT(*) as total FROM history_logs";
$total_records = fetchOne($count_sql)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get history logs
$sql = "SELECT h.*, u.username, f.farm_no, f.farm_username 
        FROM history_logs h 
        LEFT JOIN users u ON h.user_id = u.id 
        LEFT JOIN farms f ON h.farm_id = f.id 
        ORDER BY h.created_at DESC 
        LIMIT $records_per_page OFFSET $offset";
        
$history_logs = fetchAll($sql);
?>
<!DOCTYPE html>
<html lang="my">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>လုပ်ဆောင်မှုမှတ်တမ်း</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="./assets/css/history.css">
    <style>
        /* Additional styles for navbar integration */
        body {
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        
        .history_container {
            margin-left: 250px; /* Adjust based on your sidebar width */
            padding: 20px;
            min-height: 100vh;
        }
        
        @media (max-width: 768px) {
            .history_container {
                margin-left: 0;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <?php include('navbar.php'); ?>
    
    <div class="history_container">
        <!-- Header Section -->
        <div class="header-section">
            <div class="header-content">
                <div class="header-title">
                    <i class="fas fa-history"></i>
                    <h1>လုပ်ဆောင်မှုမှတ်တမ်းများ</h1>
                </div>
                <div class="header-actions">
                    <a href="sell.php" class="btn btn-back">
                        <i class="fas fa-arrow-left"></i> နောက်သို့
                    </a>
                    <?php if ($total_records > 0 && $_SESSION['role'] === 'admin'): ?>
                    <form method="POST" style="display: inline;" onsubmit="return confirmDeleteAll()">
                        <button type="submit" name="delete_all_records" class="btn btn-danger">
                            <i class="fas fa-trash"></i> မှတ်တမ်းအားလုံးဖျက်ရန်
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Stats Section -->
        <div class="stats-section">
            <div class="stats-info">
                <div class="stat-item">
                    <i class="fas fa-database"></i>
                    <strong>စုစုပေါင်းမှတ်တမ်းများ: <?php echo $total_records; ?></strong>
                </div>
                <div class="stat-item">
                    <i class="fas fa-file-alt"></i>
                    <strong>စာမျက်နှာ: <?php echo $current_page; ?> / <?php echo $total_pages; ?></strong>
                </div>
                <div class="stat-item">
                    <i class="fas fa-user-shield"></i>
                    <strong>အဆင့်: <?php echo htmlspecialchars($_SESSION['role']); ?></strong>
                </div>
            </div>
        </div>

        <!-- Content Section -->
        <div class="content-section">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <?php if ($history_logs): ?>
                <div class="history-table-container">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>ရက်စွဲ/အချိန်</th>
                                <th>အသုံးပြုသူ</th>
                                <th>ခြံ</th>
                                <th>စာမျက်နှာ</th>
                                <th>လုပ်ဆောင်ချက်</th>
                                <th>ဇယား</th>
                                <th>မှတ်တမ်း ID</th>
                                <th>ဖော်ပြချက်</th>
                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                <th>လုပ်ဆောင်ချက်များ</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history_logs as $log): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600; color: var(--text-color);">
                                            <?php echo date('Y-m-d', strtotime($log['created_at'])); ?>
                                        </div>
                                        <div style="font-size: 0.85rem; color: var(--gray-medium);">
                                            <?php echo date('H:i:s', strtotime($log['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <i class="fas fa-user-circle" style="color: var(--blue-color);"></i>
                                            <span style="font-weight: 500;"><?php echo htmlspecialchars($log['username']); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--dark-blue);">
                                            ခြံ <?php echo $log['farm_no']; ?>
                                        </div>
                                        <div style="font-size: 0.85rem; color: var(--gray-medium);">
                                            <?php echo htmlspecialchars($log['farm_username']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="font-weight: 700; font-size: 1.1rem; color: var(--dark-blue);">
                                            <?php echo $log['page_number']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $badge_class = [
                                            'INSERT' => 'badge-insert',
                                            'UPDATE' => 'badge-update', 
                                            'DELETE' => 'badge-delete',
                                            'VIEW' => 'badge-info',
                                            'SEARCH' => 'badge-info',
                                            'EDIT_CLICK' => 'badge-warning',
                                            'DELETE_ATTEMPT' => 'badge-warning',
                                            'DELETE_CLICK' => 'badge-warning',
                                            'ADD_COMMENT' => 'badge-info',
                                            'VIEW_COMMENT' => 'badge-info'
                                        ][$log['action_type']] ?? 'badge-secondary';
                                        
                                        $action_icon = [
                                            'INSERT' => 'plus',
                                            'UPDATE' => 'edit',
                                            'DELETE' => 'trash',
                                            'VIEW' => 'eye',
                                            'SEARCH' => 'search',
                                            'EDIT_CLICK' => 'edit',
                                            'DELETE_ATTEMPT' => 'trash',
                                            'DELETE_CLICK' => 'trash',
                                            'ADD_COMMENT' => 'comment',
                                            'VIEW_COMMENT' => 'comment'
                                        ][$log['action_type']] ?? 'info';
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>">
                                            <i class="fas fa-<?php echo $action_icon; ?>"></i>
                                            <?php echo $log['action_type']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <code style="background: var(--light-blue); padding: 4px 8px; border-radius: 6px; font-weight: 500;">
                                            <?php echo htmlspecialchars($log['table_name']); ?>
                                        </code>
                                    </td>
                                    <td>
                                        <strong style="color: var(--dark-blue);">#<?php echo $log['record_id']; ?></strong>
                                    </td>
                                    <td style="max-width: 300px;">
                                        <span style="word-wrap: break-word; line-height: 1.4;">
                                            <?php echo htmlspecialchars($log['description']); ?>
                                        </span>
                                    </td>
                                    <?php if ($_SESSION['role'] === 'admin'): ?>
                                    <td>
                                        <div class="action-cell">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="record_id" value="<?php echo $log['id']; ?>">
                                                <button type="submit" name="delete_record" class="btn-icon btn-icon-danger" 
                                                        onclick="return confirm('မှတ်တမ်း #<?php echo $log['id']; ?> ကိုဖျက်မှာသေချာပါသလား?')"
                                                        title="မှတ်တမ်းဖျက်ရန်">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>" class="<?php echo $i == $current_page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>မှတ်တမ်းများမတွေ့ရှိပါ</h3>
                    <p>လုပ်ဆောင်မှုမှတ်တမ်းများ မရှိသေးပါ။ sell.php တွင် ဒေတာများထည့်သွင်းပါက ဤနေရာတွင်ပြသမည်။</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function confirmDeleteAll() {
            return confirm('⚠️ သတိပြုပါ!\n\nမှတ်တမ်းအားလုံးကိုဖျက်မှာသေချာပါသလား?\n\nဤလုပ်ဆောင်ချက်ကိုပြန်လည်ရယူ၍မရပါ။\n\nဆက်လုပ်မှာသေချာပါသလား?');
        }
    </script>
</body>
</html>