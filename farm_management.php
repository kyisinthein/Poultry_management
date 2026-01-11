<?php
require_once 'database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$current_username = $_SESSION['username'];
$current_role = $_SESSION['role'];

// Handle farm selection
$selected_farm = isset($_GET['farm_id']) ? intval($_GET['farm_id']) : 1;

// Check if user is owner (admin)
$is_owner = ($current_role == 'admin');
?>

<!DOCTYPE html>
<html lang="my">
<head>
    <title>Farm Management - Poultry System</title>
    <link rel="stylesheet" href="./assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #228B22;
            --primary-light: #32CD32;
            --primary-dark: #006400;
            --secondary: #556B2F;
            --accent: #8FBC8F;
            --light: #f8f9fa;
            --dark: #2b2d42;
            --gray: #adb5bd;
            --gray-light: #e9ecef;
            --border-radius: 8px;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        .farm-management-container {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .farm-selector {
            background: white;
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            box-shadow: var(--shadow);
        }
        
        .farm-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }
        
        .farm-btn {
            padding: 10px 20px;
            border: 2px solid var(--gray-light);
            border-radius: var(--border-radius);
            background: var(--light);
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            font-weight: 500;
        }
        
        .farm-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .farm-btn:hover {
            background: var(--accent);
            color: white;
        }
        
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .notification-check {
            position: absolute;
            top: -8px;
            right: -8px;
            color: var(--primary);
            font-size: 16px;
        }
        
        .table-container {
            background: white;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .table-header {
            background: var(--primary);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-header h3 {
            margin: 0;
            font-size: 1.2em;
            font-weight: 600;
        }
        
        .table-actions {
            display: flex;
            gap: 10px;
        }
        
        .table-action {
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: var(--border-radius);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: var(--transition);
        }
        
        .table-action:hover {
            background: rgba(255,255,255,0.3);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--gray-light);
            white-space: nowrap;
        }
        
        th {
            background: var(--accent);
            color: white;
            font-weight: 600;
        }
        
        tr:hover {
            background: var(--gray-light);
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8em;
        }
        
        /* Comments Section */
        .comments-section {
            background: white;
            border-radius: var(--border-radius);
            margin-top: 30px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .comments-header {
            background: var(--secondary);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .comments-list {
            max-height: 300px;
            overflow-y: auto;
            padding: 15px;
        }
        
        .comment-item {
            padding: 10px;
            border-bottom: 1px solid var(--gray-light);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .comment-item:last-child {
            border-bottom: none;
        }
        
        .comment-text {
            flex: 1;
        }
        
        .comment-meta {
            font-size: 0.8em;
            color: var(--gray);
            margin-top: 5px;
        }
        
        .comment-actions {
            display: flex;
            gap: 5px;
        }
        
        .comment-form {
            padding: 15px;
            border-top: 1px solid var(--gray-light);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--gray);
            border-radius: var(--border-radius);
            font-size: 1em;
        }
        
        textarea.form-control {
            min-height: 80px;
            resize: vertical;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: white;
            border-radius: var(--border-radius);
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--gray-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--primary);
            color: white;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 1.3em;
        }
        
        .close-modal {
            background: none;
            border: none;
            color: white;
            font-size: 1.5em;
            cursor: pointer;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid var(--gray-light);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid var(--gray);
            color: var(--dark);
        }
        
        .btn-outline:hover {
            background: var(--gray-light);
        }
        
        .btn.logout {
            background: #dc3545;
            color: white;
        }
        
        .btn.logout:hover {
            background: #c82333;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        @media (max-width: 768px) {
            .table-container {
                overflow-x: auto;
            }
            
            table {
                min-width: 800px;
            }
            
            .farm-buttons {
                justify-content: center;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <div class="chat-header">
            <h2>Farm Management System - <?php echo $current_username; ?> (<?php echo $current_role; ?>)</h2>
            <div class="header-actions">
                <a href="chat.php" class="btn">Back to Chat</a>
                <?php if ($current_role == 'admin'): ?>
                    <a href="admin.php" class="btn">Manage Users</a>
                <?php endif; ?>
                <a href="logout.php" class="btn logout">Logout</a>
            </div>
        </div>
        
        <div class="farm-management-container">
            <!-- Farm Selector -->
            <div class="farm-selector">
                <h3>Select Farm (ခြံရွေးချယ်ရန်)</h3>
                <div class="farm-buttons" id="farmButtons">
                    <?php for($i = 1; $i <= 30; $i++): ?>
                        <button class="farm-btn <?php echo $i == $selected_farm ? 'active' : ''; ?>" 
                                onclick="selectFarm(<?php echo $i; ?>)">
                            Farm <?php echo $i; ?> (ခြံ <?php echo $i; ?>)
                            <span class="notification-badge" id="badge-<?php echo $i; ?>" style="display: none;"></span>
                            <span class="notification-check" id="check-<?php echo $i; ?>" style="display: none;">✓</span>
                        </button>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Table 1: Person in Charge -->
            <div class="table-container">
                <div class="table-header">
                    <h3>ကြက်ခြံကို တာဝန်ယူရတဲ့သူ</h3>
                    <div class="table-actions">
                        <button class="table-action" onclick="openModal('person')">
                            <i class="fas fa-plus"></i> Add Record
                        </button>
                    </div>
                </div>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>အသက်</th>
                                <th>ရက်စွဲ</th>
                                <th>ကုမ္ပဏီဝင်စာ</th>
                                <th>စပ်စာဝင်</th>
                                <th>အစာပေါင်း</th>
                                <th>ကုမ္ပဏီလက်ကျန်</th>
                                <th>စပ်စာကျန်</th>
                                <th>နေ့စဉ်အစာစားနှုန်း</th>
                                <th>စုစုပေါင်းအစာစားနှုန်း</th>
                                <th>အလေးချိန်</th>
                                <th>အသေ</th>
                                <th>စုစုပေါင်းအသေ</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="personTableBody">
                            <!-- Data will be loaded by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Table 2: Feed Summary -->
            <div class="table-container">
                <div class="table-header">
                    <h3>အစာစာရင်းချုပ်</h3>
                    <div class="table-actions">
                        <button class="table-action" onclick="openModal('feed')">
                            <i class="fas fa-plus"></i> Add Record
                        </button>
                    </div>
                </div>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>အစာအမျိုးအစား</th>
                                <th>အစာအမည်</th>
                                <th>အရေအတွက်</th>
                                <th>ဈေးနှုန်း</th>
                                <th>ကျသင့်ငွေ</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="feedTableBody">
                            <!-- Data will be loaded by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Table 3: Sales Summary -->
            <div class="table-container">
                <div class="table-header">
                    <h3>အရောင်းစာရင်းချုပ်</h3>
                    <div class="table-actions">
                        <button class="table-action" onclick="openModal('sales')">
                            <i class="fas fa-plus"></i> Add Record
                        </button>
                    </div>
                </div>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>ရက်စွဲ</th>
                                <th>ရောင်းကောင်</th>
                                <th>အလေးချိန်</th>
                                <th>စုစုပေါင်းရောင်းကောင်</th>
                                <th>စုစုပေါင်းအလေးချိန်</th>
                                <th>အသေပေါင်း</th>
                                <th>အသေရာခိုင်နှုန်း</th>
                                <th>အပို/အလို</th>
                                <th>21 to 30</th>
                                <th>31 to 36</th>
                                <th>37 to end</th>
                                <th>ကြက်အလေးချိန်စုစုပေါင်း</th>
                                <th>စုစုပေါင်းအစာစားနှုန်း</th>
                                <th>ကြက်စာအလေးချိန်စုစုပေါင်း</th>
                                <th>FCR</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="salesTableBody">
                            <!-- Data will be loaded by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Table 4: Medicine List -->
            <div class="table-container">
                <div class="table-header">
                    <h3>ဆေးစာရင်းချုပ်</h3>
                    <div class="table-actions">
                        <button class="table-action" onclick="openModal('medicine')">
                            <i class="fas fa-plus"></i> Add Record
                        </button>
                    </div>
                </div>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>ရက်သား</th>
                                <th>ဆေးအမျိုးအစား</th>
                                <th>အရေအတွက်</th>
                                <th>အကြိမ်ရေ</th>
                                <th>စုစုပေါင်း</th>
                                <th>ဈေးနှုန်း</th>
                                <th>ကျသင့်ငွေ</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="medicineTableBody">
                            <!-- Data will be loaded by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Table 5: Grand Total For All Farm Audits -->
            <div class="table-container">
                <div class="table-header">
                    <h3>ခြံအားလုံးအတွက် စာရင်းစစ်ခြင်းစုစုပေါင်း</h3>
                    <div class="table-actions">
                        <button class="table-action" onclick="openModal('audit')">
                            <i class="fas fa-plus"></i> Add Record
                        </button>
                    </div>
                </div>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>စဉ်</th>
                                <th>အမည်</th>
                                <th>အမျိုးအစား</th>
                                <th>အကောင်ရေ</th>
                                <th>အလေးချိန်</th>
                                <th>ရောင်း</th>
                                <th>သေ</th>
                                <th>ပို/လို</th>
                                <th>ကုန်ချိန်</th>
                                <th>အစာချိန်</th>
                                <th>ကုမ္ပဏီ</th>
                                <th>စပ်</th>
                                <th>အစာအိပ်</th>
                                <th>ဆေး</th>
                                <th>အစာ</th>
                                <th>မီးသွေး</th>
                                <th>ဖွဲ</th>
                                <th>ထုံး</th>
                                <th>TFCR</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="auditTableBody">
                            <!-- Data will be loaded by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Comments Section (Only for Owner) -->
            <?php if ($is_owner): ?>
            <div class="comments-section">
                <div class="comments-header">
                    <h3>Farm Comments (ခြံအကြောင်းမှတ်ချက်များ)</h3>
                    <span id="commentsCount">0 comments</span>
                </div>
                <div class="comments-list" id="commentsList">
                    <!-- Comments will be loaded here -->
                </div>
                <div class="comment-form">
                    <form id="commentForm">
                        <div class="form-group">
                            <label for="commentText">Add Comment (မှတ်ချက်ထည့်ရန်):</label>
                            <textarea class="form-control" id="commentText" name="comment_text" 
                                      placeholder="Enter your comment here... (e.g., စားစရာ များလွန်းတယ်၊ ကာကွယ်ဆေး ထိုးဖို့လိုတယ်)" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Submit Comment
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal for adding/editing records -->
    <div class="modal" id="recordModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add Record</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="recordForm">
                    <!-- Form fields will be generated by JavaScript -->
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal()">Cancel</button>
                <button class="btn btn-primary" id="saveRecordBtn">Save Record</button>
            </div>
        </div>
    </div>

    <script src="./assets/js/farm_management.js"></script>
</body>
</html>