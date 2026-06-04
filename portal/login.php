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

    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';

    // Check admin
    $stmt = $pdo->prepare('SELECT * FROM admins WHERE username = ?');
    $stmt->execute([$email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id']   = $admin['id'];
        $_SESSION['admin_name'] = $admin['username'];
        header('Location: https://sunnymonkeys.com/portal/admin/index.php');
        exit;
    }

    // Check client
    $stmt = $pdo->prepare('SELECT * FROM clients WHERE email = ?');
    $stmt->execute([$email]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($client && password_verify($password, $client['password'])) {
        $_SESSION['user_id']   = $client['id'];
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
    <link rel="shortcut icon" type="image/x-icon" href="/assets/images/fav.png">
    <title>Client Login — Sunny Monkeys LLC</title>
    <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { background: #111; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', sans-serif; }
    .login-wrap { width: 100%; max-width: 420px; padding: 20px; }
    .login-logo { text-align: center; margin-bottom: 40px; }
    .login-logo a { display: inline-block; }
    .login-logo img { height: 44px; width: 44px; object-fit: contain; border-radius: 50%; display: block; }
    .login-card { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 16px; padding: 48px 40px; }
    @media (max-width: 480px) { .login-card { padding: 36px 24px; } }
    .login-card h2 { font-size: 2.4rem; font-weight: 800; color: #fff; margin-bottom: 8px; letter-spacing: -0.03em; }
    .login-card p { font-size: 1.1rem; color: #666; margin-bottom: 36px; }
    .lf-group { margin-bottom: 20px; }
    .lf-group label { display: block; font-size: 0.9rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #666; margin-bottom: 8px; }
    .lf-group input { width: 100%; background: #111; border: 1.5px solid #2e2e2e; border-radius: 8px; padding: 16px 18px; font-size: 1.1rem; color: #fff; outline: none; transition: border-color 0.2s; font-family: inherit; }
    .lf-group input:focus { border-color: #555; }
    .lf-group input::placeholder { color: #444; }
    .login-btn-main { width: 100%; background: #fff; color: #111; border: none; border-radius: 8px; padding: 17px; font-size: 1.15rem; font-weight: 700; letter-spacing: 0.04em; cursor: pointer; transition: background 0.2s, transform 0.15s; margin-top: 8px; font-family: inherit; }
    .login-btn-main:hover { background: #e8e8e8; transform: translateY(-1px); }
    .error-box { background: #2a0a0a; border: 1px solid #5a1a1a; color: #ff6b6b; padding: 12px 16px; border-radius: 8px; font-size: 0.95rem; margin-bottom: 24px; }
    .login-footer { text-align: center; margin-top: 28px; font-size: 1rem; color: #444; }
    .login-footer a { color: #777; text-decoration: none; }
    .login-footer a:hover { color: #fff; }
    .login-back { text-align: center; margin-top: 32px; }
    .login-back a { color: #444; font-size: 1rem; text-decoration: none; transition: color 0.2s; }
    .login-back a:hover { color: #fff; }
    </style>
</head>
<body>
    <div class="login-wrap">
        <div class="login-logo">
            <a href="/"><img src="/assets/images/logo-sunny-monkeys-white.png" alt="Sunny Monkeys"></a>
        </div>
        <div class="login-card">
            <h2>Client Portal</h2>
            <p>Sign in to access your project dashboard.</p>
            <?php if ($error): ?>
                <div class="error-box"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="lf-group">
                    <label for="lf-email">Email Address</label>
                    <input type="email" id="lf-email" name="email" required autofocus placeholder="you@company.com">
                </div>
                <div class="lf-group">
                    <label for="lf-pass">Password</label>
                    <input type="password" id="lf-pass" name="password" required placeholder="••••••••">
                </div>
                <button type="submit" class="login-btn-main">Sign In</button>
            </form>
            <div class="login-footer">
                <a href="/contact.html">Need access? Contact us →</a>
            </div>
        </div>
        <div class="login-back">
            <a href="/">← Back to Sunny Monkeys</a>
        </div>
    </div>
</body>
</html>
