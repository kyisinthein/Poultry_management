<?php
require_once 'database.php';

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    header("Location: chat.php");
    exit();
}

// Handle form submissions
if ($_POST) {
    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $_POST['role'];
        
        executeQuery("INSERT INTO users (username, password, role) VALUES (?, ?, ?)", 
                    [$username, $password, $role]);
    }
    
    if (isset($_POST['update_user'])) {
        $user_id = $_POST['user_id'];
        $username = trim($_POST['username']);
        $role = $_POST['role'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            executeQuery("UPDATE users SET username=?, password=?, role=?, is_active=? WHERE id=?", 
                        [$username, $password, $role, $is_active, $user_id]);
        } else {
            executeQuery("UPDATE users SET username=?, role=?, is_active=? WHERE id=?", 
                        [$username, $role, $is_active, $user_id]);
        }
    }
    
    if (isset($_POST['delete_user'])) {
        $user_id = $_POST['user_id'];
        executeQuery("DELETE FROM users WHERE id = ? AND role != 'admin'", [$user_id]);
    }
}

// Get all users
$users = fetchAll("SELECT * FROM users ORDER BY role, username");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Panel - User Management</title>
    <link rel="stylesheet" href="./assets/css/style.css">
</head>
<body>
    <div class="chat-container">
        <div class="chat-header">
            <h2>Admin Panel - User Management</h2>
            <div class="header-actions">
                <a href="chat.php" class="btn">Back to Chat</a>
                <a href="logout.php" class="btn logout">Logout</a>
            </div>
        </div>
        
        <div class="admin-container">
            <!-- Add User Form -->
            <div style="background: white; padding: 2rem; border-radius: 10px; margin-bottom: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                <h3>Add New User</h3>
                <form method="POST">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 1rem; align-items: end;">
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="username" required>
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" name="password" required>
                        </div>
                        <div class="form-group">
                            <label>Role</label>
                            <select name="role" required>
                                <option value="user1">User 1</option>
                                <option value="user2">User 2</option>
                                <option value="user3">User 3</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <button type="submit" name="add_user" class="btn">Add User</button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Users List -->
            <div class="user-table">
                <h3>Manage Users</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <form method="POST">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <td><?php echo $user['id']; ?></td>
                                <td>
                                    <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                </td>
                                <td>
                                    <select name="role" <?php echo $user['role'] == 'admin' ? 'disabled' : ''; ?>>
                                        <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        <option value="user1" <?php echo $user['role'] == 'user1' ? 'selected' : ''; ?>>User 1</option>
                                        <option value="user2" <?php echo $user['role'] == 'user2' ? 'selected' : ''; ?>>User 2</option>
                                        <option value="user3" <?php echo $user['role'] == 'user3' ? 'selected' : ''; ?>>User 3</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="checkbox" name="is_active" <?php echo $user['is_active'] ? 'checked' : ''; ?> <?php echo $user['role'] == 'admin' ? 'disabled' : ''; ?>>
                                    Active
                                </td>
                                <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                <td style="white-space: nowrap;">
                                    <div style="display: flex; gap: 5px;">
                                        <input type="password" name="password" placeholder="New password" style="padding: 5px;">
                                        <button type="submit" name="update_user" class="btn" style="padding: 5px 10px;">Update</button>
                                        <?php if ($user['role'] != 'admin'): ?>
                                            <button type="submit" name="delete_user" class="btn logout" style="padding: 5px 10px;" 
                                                    onclick="return confirm('Are you sure you want to delete this user?')">Delete</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </form>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>