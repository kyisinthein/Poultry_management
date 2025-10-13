<?php
require_once __DIR__ . '/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = trim($_POST['role'] ?? '');

    // Validate role options
    $allowedRoles = ['super_admin', 'admin'];
    if (!in_array($role, $allowedRoles, true)) {
        $error = 'Please select a valid role.';
    }

    if (!$error && ($name === '' || $password === '')) {
        $error = 'Name and password are required.';
    }

    if (!$error) {
        // Find user by name AND role
        $user = fetchOne(
            "SELECT id, name, password, role FROM users WHERE name = ? AND role = ? LIMIT 1",
            [$name, $role]
        );

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'role' => $user['role'],
            ];
            header('Location: /Poultry_management/dashboard.php');
            exit;
        } else {
            $error = 'Invalid name, password, or role.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Poultry Management Login</title>
</head>
<body>
  <h1>Poultry Management Login</h1>
  <?php if ($error) { echo "<p style='color:red'>" . htmlspecialchars($error) . "</p>"; } ?>
  <form method="post">
    <label>
      Name
      <input type="text" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
    </label><br>
    <label>
      Password
      <input type="password" name="password" required>
    </label><br>
    <label>
      Role
      <select name="role" required>
        <?php $selectedRole = $_POST['role'] ?? ''; ?>
        <option value="" disabled <?php echo $selectedRole === '' ? 'selected' : ''; ?>>Select role</option>
        <option value="super_admin" <?php echo $selectedRole === 'super_admin' ? 'selected' : ''; ?>>Owner (Super Admin)</option>
        <option value="admin" <?php echo $selectedRole === 'admin' ? 'selected' : ''; ?>>Manager (Admin)</option>
      </select>
    </label><br>
    <button type="submit">Login</button>
  </form>
</body>
</html>