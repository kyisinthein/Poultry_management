<?php
require_once 'database.php';

if ($_POST) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    $user = fetchOne("SELECT * FROM users WHERE username = ? AND is_active = TRUE", [$username]);
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        header("Location: chat.php");
        exit();
    } else {
        $error = "Invalid username or password!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login - Chat System</title>
    <link rel="stylesheet" href="./assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-form">
            <h2>Chat System Login</h2>
            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit">Login</button>
            </form>
            <p>Default passwords: 'password' for all users</p>
        </div>
    </div>
</body>
</html>