<?php
session_start();
if (isset($_SESSION['user_id']) || isset($_SESSION['admin_id'])) {
    header('Location: https://sunnymonkeys.com/portal/' . (isset($_SESSION['admin_id']) ? 'admin/index.php' : 'dashboard.php'));
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/config/db.php';
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8', DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('SELECT * FROM admins WHERE username = ?');
    $stmt->execute([$email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id']   = $admin['id'];
        $_SESSION['admin_name'] = $admin['username'];
        header('Location: https://sunnymonkeys.com/portal/admin/index.php'); exit;
    }
    $stmt = $pdo->prepare('SELECT * FROM clients WHERE email = ?');
    $stmt->execute([$email]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($client && password_verify($password, $client['password'])) {
        $_SESSION['user_id']   = $client['id'];
        $_SESSION['user_name'] = $client['name'];
        header('Location: https://sunnymonkeys.com/portal/dashboard.php'); exit;
    }
    $error = 'Invalid email or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client portal | Sunny Monkeys</title>
    <meta name="robots" content="noindex">
<link rel="icon" type="image/png" href="/assets/images/fav.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700&family=Barlow:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/portal/assets/portal.css?v=2">
</head>
<body>
    <div class="login-wrap">
        <div class="login-box">
            <div class="login-logo">
                <a href="/"><span class="mark" aria-hidden="true"></span>Sunny Monkeys</a>
            </div>
            <div class="login-card">
                <h2>Client portal</h2>
                <p>Sign in to see your plan, invoices and contracts.</p>
                <?php if ($error): ?><div class="error-box" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <form method="POST">
                    <div class="lf-group">
                        <label for="lf-email">Email address</label>
                        <input type="email" id="lf-email" name="email" required autofocus autocomplete="username" placeholder="you@company.com">
                    </div>
                    <div class="lf-group">
                        <label for="lf-pass">Password</label>
                        <input type="password" id="lf-pass" name="password" required autocomplete="current-password">
                    </div>
                    <button type="submit" class="login-btn-main">Sign in</button>
                </form>
                <div class="login-footer"><a href="/contact">Need access? Contact us</a></div>
            </div>
            <div class="login-back"><a href="/">Back to Sunny Monkeys</a></div>
        </div>
    </div>
</body>
</html>
