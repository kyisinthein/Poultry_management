<?php
require_once 'database.php';

if ($_POST) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $selected_role = trim($_POST['role']);
    
    $user = fetchOne("SELECT * FROM users WHERE username = ? AND is_active = TRUE", [$username]);
    
    if ($user && $password === $user['password']) {
      
        if ($user['role'] === $selected_role) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
          
            if ($selected_role === 'admin') {
                header("Location: dashboard.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();
        } else {
            $error = "ရွေးချယ်ထားသော role သည် သင့်အကောင့် role နှင့် မကိုက်ညီပါ!";
        }
    } else {
        $error = "အသုံးပြုသူအမည် သို့မဟုတ် လျှို့ဝှက်နံပါတ် မှားယွင်းနေပါသည်!";
    }
}
?>

<!DOCTYPE html>
<html lang="my">
<head>
    <meta charset="UTF-8">
    <title>PoultryLogin</title>
    <link rel="stylesheet" href="./assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-form">
            <h2>Login ဝင်ရန်</h2>
            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="text" name="username" placeholder="အသုံးပြုသူအမည်" required>
                <input type="password" name="password" placeholder="လျှို့ဝှက်နံပါတ်" required>
                
                <div class="role-selection">
                    <span class="role-title">Role ရွေးချယ်ပါ</span>
                    <label>
                        <input type="radio" name="role" value="user" checked> User
                    </label>
                    <label>
                        <input type="radio" name="role" value="admin"> Admin
                    </label>
                </div>
                
                <button type="submit">Loginဝင်မည်</button>
            </form>
        </div>
    </div>
</body>
</html>