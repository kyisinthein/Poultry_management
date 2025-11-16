<?php
require_once 'database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}


$user = fetchOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);


$users = [];
if ($_SESSION['role'] === 'admin') {
    $users = fetchAll("SELECT id, username, role, profile_pic, is_active, created_at, last_activity FROM users");
}
?>

<!DOCTYPE html>
<html lang="my">
<head>
    <meta charset="UTF-8">
    <title>usermanagement_polutrymanagement</title>
    <link rel="stylesheet" href="./assets/css/usermanagement.css">
  <link rel="stylesheet" href="./assets/css/dashboard.css">
</head>
<body>
<?php include('navbar.php'); ?>

    <div class="welcome_container">
        <div class="welcome-section">
            <h1>မင်္ဂလာပါ <?php echo htmlspecialchars($user['username']); ?></h1>
            <p>သင့်ရဲ့ dashboard မှကြိုဆိုပါတယ်..</p>
            <p>Role: <strong><?php echo htmlspecialchars($user['role']); ?></strong></p>
            <p>နောက်ဆုံးဝင်ကြည့်ချိန်: 
<?php 
    $time = strtotime($user['last_activity']);
    $hour = date('H', $time);
    $minute = date('i', $time);
    
    if ($hour < 12) {
        echo date('g', $time) . ':' . $minute . ' မနက်';
    } else {
        echo (date('g', $time) != 12 ? date('g', $time) : 12) . ':' . $minute . ' ညနေ';
    }
?>
</p>
        </div>

        <?php if ($_SESSION['role'] === 'admin'): ?>
        <div class="admin-section" id="users">
            <h2>Users Management</h2>
            <table class="users-table">
                <thead>
                    <tr>
                        <th>Profile</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Last Activity</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <img src="./assets/profile_pics/<?php echo $u['profile_pic']; ?>" 
                                 alt="Profile" class="user-avatar"
                                 onerror="this.src='./assets/profile_pics/default_avatar.jpg'">
                        </td>
                        <td><?php echo htmlspecialchars($u['username']); ?></td>
                        <td><?php echo htmlspecialchars($u['role']); ?></td>
                        <td>
                            <span class="<?php echo $u['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $u['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td><?php echo $u['created_at']; ?></td>
                        <td><?php echo $u['last_activity']; ?></td>
                        <td>
                        <button onclick="openPasswordModal(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['username']); ?>')" class="btn btn-primary btn-sm">
                            ပြုပြင်ရန်
                        </button>
                        </td>
                        
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($_SESSION['role'] === 'admin'): ?>
<!-- Password Update Modal -->
<div id="passwordModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closePasswordModal()">&times;</span>
        <h2>Update User Password</h2>
        <form id="passwordForm" method="POST" action="update_password.php">
            <input type="hidden" id="modalUserId" name="user_id">
            
            <div class="form-group">
                <label>Username:</label>
                <input type="text" id="modalUsername" class="readonly-field" readonly>
            </div>

            <div class="form-group">
                <label>Current Password:</label>
                <input type="text" id="currentPassword" class="readonly-field" readonly>
                <small style="color: #666;">Current password (read-only)</small>
            </div>

            <div class="form-group">
                <label for="new_password">New Password:</label>
                <input type="password" name="new_password" id="new_password" required placeholder="Enter new password">
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" name="confirm_password" id="confirm_password" required placeholder="Confirm new password">
            </div>

            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">Update Password</button>
                <button type="button" class="btn btn-secondary" onclick="closePasswordModal()">Cancel</button>
            </div>
        </form>
        <div id="passwordMessage" style="margin-top: 15px;"></div>
    </div>
</div>
<script>
function openPasswordModal(userId, username) {
    document.getElementById('modalUserId').value = userId;
    document.getElementById('modalUsername').value = username;
    document.getElementById('passwordModal').style.display = 'block';
    document.getElementById('passwordMessage').innerHTML = '';
    
    // Clear form fields
    document.getElementById('new_password').value = '';
    document.getElementById('confirm_password').value = '';
    
    // Fetch current password (you'll need to create this endpoint)
    fetch('get_current_password.php?user_id=' + userId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('currentPassword').value = data.password;
            }
        });
}


function closePasswordModal() {
    document.getElementById('passwordModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('passwordModal');
    if (event.target == modal) {
        closePasswordModal();
    }
}

// Handle form submission
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const messageDiv = document.getElementById('passwordMessage');
    
    // Simple password validation
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword !== confirmPassword) {
        messageDiv.innerHTML = '<div class="error">Passwords do not match!</div>';
        return;
    }
    
    if (newPassword.length < 4) {
        messageDiv.innerHTML = '<div class="error">Password must be at least 4 characters!</div>';
        return;
    }
    
    // Show loading
    messageDiv.innerHTML = '<div class="success">Updating password...</div>';
    
    fetch('update_password.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            messageDiv.innerHTML = '<div class="success">' + data.message + '</div>';
            // Clear form fields
            document.getElementById('new_password').value = '';
            document.getElementById('confirm_password').value = '';
            // Close modal after 2 seconds
            setTimeout(() => {
                closePasswordModal();
            }, 2000);
        } else {
            messageDiv.innerHTML = '<div class="error">' + data.message + '</div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        messageDiv.innerHTML = '<div class="error">Network error: ' + error.message + '</div>';
    });
});
</script>
<?php endif; ?>
</body>
</html>