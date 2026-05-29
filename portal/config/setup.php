<?php
/**
 * ONE-TIME SETUP SCRIPT
 * Run this once by visiting: yourdomain.com/portal/config/setup.php
 * DELETE THIS FILE immediately after running it.
 */

require_once 'db.php';

$pdo = getDB();

// Create users table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(120) NOT NULL,
        email       VARCHAR(200) NOT NULL UNIQUE,
        password    VARCHAR(255) NOT NULL,
        role        ENUM('admin','client') NOT NULL DEFAULT 'client',
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Create documents table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS documents (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        client_id   INT NOT NULL,
        title       VARCHAR(255) NOT NULL,
        type        ENUM('invoice','contract') NOT NULL,
        filename    VARCHAR(255) NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Create the admin account (Marco)
// Change the password below before running!
$adminEmail    = 'admin@sunnymonkeys.com';
$adminPassword = 'ChangeThisPassword123!';
$adminName     = 'Marco';

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$adminEmail]);
if (!$stmt->fetch()) {
    $hash = password_hash($adminPassword, PASSWORD_BCRYPT);
    $ins  = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
    $ins->execute([$adminName, $adminEmail, $hash]);
    echo "<p>✅ Admin account created: <strong>{$adminEmail}</strong></p>";
} else {
    echo "<p>ℹ️ Admin account already exists.</p>";
}

echo "<p>✅ Tables created successfully.</p>";
echo "<p style='color:red;'><strong>⚠️ DELETE THIS FILE NOW: portal/config/setup.php</strong></p>";
