<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: https://sunnymonkeys.com/portal/login.php'); exit; }
require_once __DIR__ . '/../config/db.php';
$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8', DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$name || !$email || !$password) {
        $error = 'Name, email, and password are required.';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO clients (name, email, company, password) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $email, $company, $hash]);
            $success = "Client \"$name\" created successfully.";
        } catch (Exception $e) {
            $error = 'Email already exists or database error.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Client — Sunny Monkeys</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: #0d0d0d; color: #fff; font-family: 'Segoe UI', sans-serif; min-height: 100vh; }
  header { background: #111; border-bottom: 1px solid #222; padding: 16px 32px; display: flex; align-items: center; justify-content: space-between; }
  header .brand { display: flex; align-items: center; gap: 12px; }
  header img { width: 36px; height: 36px; object-fit: contain; border-radius: 50%; }
  header h1 { font-size: 1rem; font-weight: 600; }
  header nav a { color: #aaa; font-size: 0.88rem; text-decoration: none; margin-left: 20px; }
  header nav a:hover { color: #fff; }
  .container { max-width: 520px; margin: 0 auto; padding: 48px 24px; }
  h2 { font-size: 1.4rem; font-weight: 600; margin-bottom: 28px; }
  label { display: block; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.08em; color: #aaa; margin-bottom: 6px; text-transform: uppercase; }
  input { width: 100%; background: #111; border: 1px solid #333; border-radius: 8px; padding: 12px 14px; color: #fff; font-size: 0.95rem; margin-bottom: 20px; outline: none; }
  input:focus { border-color: #555; }
  .btn { background: #fff; color: #000; border: none; border-radius: 8px; padding: 12px 24px; font-size: 0.95rem; font-weight: 600; cursor: pointer; }
  .btn:hover { background: #e0e0e0; }
  .back { color: #666; font-size: 0.85rem; text-decoration: none; display: inline-block; margin-bottom: 24px; }
  .success { background: #0a2a0a; border: 1px solid #1a5a1a; color: #6fcf6f; padding: 10px 14px; border-radius: 8px; margin-bottom: 20px; font-size: 0.88rem; }
  .error { background: #2a0a0a; border: 1px solid #5a1a1a; color: #ff6b6b; padding: 10px 14px; border-radius: 8px; margin-bottom: 20px; font-size: 0.88rem; }
</style>
</head>
<body>
<header>
  <div class="brand">
    <img src="/assets/images/logo-sunny-monkeys-white.png" alt="Sunny Monkeys">
    <h1>Admin Portal</h1>
  </div>
  <nav>
    <a href="index.php">← Clients</a>
    <a href="../logout.php">Sign out</a>
  </nav>
</header>
<div class="container">
  <h2>Add New Client</h2>
  <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="POST">
    <label>Full Name</label>
    <input type="text" name="name" required placeholder="Jane Smith">
    <label>Email Address</label>
    <input type="email" name="email" required placeholder="jane@company.com">
    <label>Company (optional)</label>
    <input type="text" name="company" placeholder="Acme Inc.">
    <label>Temporary Password</label>
    <input type="text" name="password" required placeholder="They can change this later">
    <button type="submit" class="btn">Create Client</button>
  </form>
</div>
</body>
</html>
