<?php
session_start();
if (isset($_SESSION['user_id']) || isset($_SESSION['admin_id'])) {
    header('Location: https://sunnymonkeys.com/portal/' . (isset($_SESSION['admin_id']) ? 'admin/index.php' : 'dashboard.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/config/db.php';
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8', DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Check admin
    $stmt = $pdo->prepare('SELECT * FROM admins WHERE username = ?');
    $stmt->execute([$email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_name'] = $admin['username'];
        header('Location: https://sunnymonkeys.com/portal/admin/index.php');
        exit;
    }

    // Check client
    $stmt = $pdo->prepare('SELECT * FROM clients WHERE email = ?');
    $stmt->execute([$email]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($client && password_verify($password, $client['password'])) {
        $_SESSION['user_id'] = $client['id'];
        $_SESSION['user_name'] = $client['name'];
        header('Location: https://sunnymonkeys.com/portal/dashboard.php');
        exit;
    }

    $error = 'Invalid email or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Client Portal — Sunny Monkeys</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: #0d0d0d; color: #fff; font-family: 'Segoe UI', sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
  .card { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 12px; padding: 48px 40px; width: 100%; max-width: 420px; }
  .logo { text-align: center; margin-bottom: 32px; }
  .logo img { width: 60px; height: 60px; object-fit: contain; border-radius: 50%; }
  .logo h1 { font-size: 1.4rem; font-weight: 600; margin-top: 12px; }
  .logo p { color: #888; font-size: 0.9rem; margin-top: 4px; }
  label { display: block; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.08em; color: #aaa; margin-bottom: 6px; text-transform: uppercase; }
  input { width: 100%; background: #111; border: 1px solid #333; border-radius: 8px; padding: 12px 14px; color: #fff; font-size: 0.95rem; margin-bottom: 20px; outline: none; transition: border-color 0.2s; }
  input:focus { border-color: #555; }
  .btn { width: 100%; background: #fff; color: #000; border: none; border-radius: 8px; padding: 13px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: background 0.2s; }
  .btn:hover { background: #e0e0e0; }
  .error { background: #2a0a0a; border: 1px solid #5a1a1a; color: #ff6b6b; padding: 10px 14px; border-radius: 8px; font-size: 0.88rem; margin-bottom: 20px; }
  .back { text-align: center; margin-top: 24px; }
  .back a { color: #666; font-size: 0.85rem; text-decoration: none; }
  .back a:hover { color: #aaa; }
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <img src="/assets/images/logo-sunny-monkeys-white.png" alt="Sunny Monkeys">
    <h1>Client Portal</h1>
    <p>Sign in to view your documents</p>
  </div>
  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST">
    <label>Email Address</label>
    <input type="email" name="email" required autofocus placeholder="you@example.com">
    <label>Password</label>
    <input type="password" name="password" required placeholder="••••••••">
    <button type="submit" class="btn">Sign In</button>
  </form>
  <div class="back"><a href="/">← Back to sunnymonkeys.com</a></div>
</div>
</body>
</html>
