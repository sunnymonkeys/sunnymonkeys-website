<?php
session_start();

// Already logged in — redirect to right place
if (!empty($_SESSION['user_id'])) {
    $dest = ($_SESSION['role'] === 'admin') ? 'admin/index.php' : 'dashboard.php';
    header("Location: $dest");
    exit;
}

require_once 'config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email && $password) {
        $stmt = getDB()->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['role']    = $user['role'];

            $dest = ($user['role'] === 'admin') ? 'admin/index.php' : 'dashboard.php';
            header("Location: $dest");
            exit;
        } else {
            $error = 'Incorrect email or password.';
        }
    } else {
        $error = 'Please enter your email and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Login — Sunny Monkeys</title>
    <link rel="shortcut icon" href="../assets/images/fav.png">
    <link rel="stylesheet" href="assets/portal.css">
</head>
<body>
<div class="login-page">
    <div class="login-box">
        <div class="logo-area">
            <a href="../index.html"><img src="../assets/images/logo-sunny-monkeys-white.png" alt="Sunny Monkeys"></a>
            <h1>Client Portal</h1>
            <p>Sign in to view your documents</p>
        </div>

        <div class="login-card">
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="you@company.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn btn-primary">Sign In</button>
            </form>
        </div>

        <p class="login-back">
            <a href="../index.html">← Back to sunnymonkeys.com</a>
        </p>
    </div>
</div>
</body>
</html>
