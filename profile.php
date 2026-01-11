<?php
require_once 'database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if admin is viewing another user's profile
$edit_user_id = isset($_GET['user_id']) ? $_GET['user_id'] : $_SESSION['user_id'];
$is_editing_other = ($edit_user_id != $_SESSION['user_id']);
$current_user_role = $_SESSION['role'];

// Check permissions
if ($is_editing_other && $current_user_role !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

// Get user data
$user = fetchOne("SELECT * FROM users WHERE id = ?", [$edit_user_id]);

if (!$user) {
    header("Location: dashboard.php");
    exit();
}

// Handle form submission
if ($_POST) {
    $username = trim($_POST['username']);
    
    // Check if username already exists (excluding current user)
    $existing_user = fetchOne("SELECT id FROM users WHERE username = ? AND id != ?", [$username, $edit_user_id]);
    
    if ($existing_user) {
        $error = "Username already exists!";
    } else {
        // Handle profile picture upload
        $profile_pic = $user['profile_pic'];
        
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = './assets/profile_pics/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
            $new_filename = uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            // Check file type
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            if (in_array(strtolower($file_extension), $allowed_types)) {
                if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_path)) {
                    // Delete old profile picture if not default
                    if ($profile_pic !== 'default_avatar.jpg') {
                        @unlink($upload_dir . $profile_pic);
                    }
                    $profile_pic = $new_filename;
                }
            }
        }
        
        // Update user data
        executeQuery(
            "UPDATE users SET username = ?, profile_pic = ? WHERE id = ?",
            [$username, $profile_pic, $edit_user_id]
        );
        
        $_SESSION['success'] = "Profile ကိုအောင်မြင်စွာပြင်ဆင်ပြီးဖြစ်ပါသည်!";
        header("Location: profile.php" . ($is_editing_other ? "?user_id=" . $edit_user_id : ""));
        exit();
    }
}

// Get the actual password from database
$user_data = fetchOne("SELECT password FROM users WHERE id = ?", [$edit_user_id]);
$password_display = $user_data ? $user_data['password'] : "";
?>

<!DOCTYPE html>
<html lang="my">
<head>
    <meta charset="UTF-8">
    <title>Profile_polutrymanagement</title>
    <link rel="stylesheet" href="./assets/css/profile.css">
    <link rel="stylesheet" href="./assets/css/dashboard.css">
</head>
<body>
<?php include('navbar.php'); ?>
    <div class="profile_container">
        <div class="profile-form">
            <h2><?php echo $is_editing_other ? 'Edit User Profile' : 'Profile ပြင်ဆင်ရန်'; ?></h2>
            
            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="profile-pic-container">
                    <img src="./assets/profile_pics/<?php echo $user['profile_pic']; ?>" 
                         alt="Profile Preview" class="profile-pic-preview" id="profilePreview"
                         onerror="this.src='./assets/profile_pics/default_avatar.jpg'">
                    <br>
                    <div class="file-input-container">
                        <input type="file" name="profile_pic" id="profile_pic" accept="image/*">
                        <label for="profile_pic" class="file-input-label">ပုံရွေးရန်</label>
                        <span class="file-name" id="fileName">ပုံမရွေးရသေးပါ</span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Username:</label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Role:</label>
                    <input type="text" value="<?php echo htmlspecialchars($user['role']); ?>" class="readonly-field" readonly>
                </div>

                   
            <div class="form-group">
                <label>Password:</label>
                <div class="password-container">
                    <input type="password" value="<?php echo htmlspecialchars($password_display); ?>" 
                        class="readonly-field password-field" id="passwordField" readonly>
                    <button type="button" class="password-toggle" onclick="togglePassword()">
                        ပြရန်
                    </button>
                </div>
                <div class="password-note">
                    <?php if ($current_user_role === 'admin'): ?>
                        Password can be changed in Users Management
                    <?php else: ?>
                        Password cannot be changed by user. Please contact admin.
                    <?php endif; ?>
                </div>
            </div>

                <div class="form-group">
                    <label>Created At:</label>
                    <input type="text" value="<?php echo $user['created_at']; ?>" class="readonly-field" readonly>
                </div>

                <div class="form-group">
                    <label>Last Activity:</label>
                    <input type="text" value="<?php echo $user['last_activity']; ?>" class="readonly-field" readonly>
                </div>

                <button type="submit" class="btn btn-primary">ပြုပြင်ပါ</button>
                <a href="dashboard.php" class="btn btn-secondary">မလုပ်တော့ပါ</a>
            </form>
        </div>
    </div>

    <script>
        // Preview profile picture before upload
        document.getElementById('profile_pic').addEventListener('change', function(e) {
            const fileInput = e.target;
            const fileName = document.getElementById('fileName');
            const profilePreview = document.getElementById('profilePreview');
            
            if (fileInput.files && fileInput.files[0]) {
                fileName.textContent = fileInput.files[0].name;
                
                var reader = new FileReader();
                reader.onload = function(e) {
                    profilePreview.src = e.target.result;
                }
                reader.readAsDataURL(fileInput.files[0]);
            } else {
                fileName.textContent = 'ပုံမရွေးရသေးပါ';
            }
        });

        // Toggle password visibility
        function togglePassword() {
            const passwordField = document.getElementById('passwordField');
            const toggleButton = document.querySelector('.password-toggle');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleButton.textContent = 'ဝှက်ရန်';
            } else {
                passwordField.type = 'password';
                toggleButton.textContent = 'ပြရန်';
            }
        }

        // Show actual file name when page loads if file is already selected
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('profile_pic');
            const fileName = document.getElementById('fileName');
            
            // This would need additional logic if u want to show the current file name
            // For now, it shows the default message
        });
    </script>
</body>
</html>