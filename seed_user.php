<?php
require_once __DIR__ . '/database.php';

// Ensure table exists (run users_table.sql first via phpMyAdmin)
$exists = fetchOne("SHOW TABLES LIKE 'users'");
if (!$exists) {
    echo "Table 'users' not found. Please import users_table.sql first.\n";
    exit;
}

// Create initial super admin if missing
$name = 'owner';
$pwdPlain = 'ChangeMe123!';
$role = 'super_admin';

$found = fetchOne("SELECT id FROM users WHERE name = ?", [$name]);
if ($found) {
    echo "User '$name' already exists.\n";
    exit;
}

$hash = password_hash($pwdPlain, PASSWORD_DEFAULT);
executeQuery("INSERT INTO users (name, password, role) VALUES (?, ?, ?)", [$name, $hash, $role]);

echo "Super admin created.\n";
echo "Name: $name\n";
echo "Password: $pwdPlain\n";
echo "Login at /Poultry_management/login.php and change the password.";