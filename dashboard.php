<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['user'])) {
    header('Location: /Poultry_management/login.php');
    exit;
}
$user = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Dashboard</title>
</head>
<body>
  <h1>Welcome, <?php echo htmlspecialchars($user['name']); ?></h1>
  <p>Role: <?php echo htmlspecialchars($user['role']); ?></p>

  <?php if ($user['role'] === 'super_admin') { ?>
    <p>Super admin tools visible here.</p>
  <?php } else { ?>
    <p>Admin tools visible here.</p>
  <?php } ?>

  <p><a href="/Poultry_management/logout.php">Logout</a></p>
</body>
</html>