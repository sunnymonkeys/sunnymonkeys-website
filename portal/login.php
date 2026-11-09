<?php
require_once __DIR__ . '/lib/core.php';
portal_session();
if (!empty($_SESSION['user_id']) || !empty($_SESSION['admin_id'])) {
    portal_redirect(!empty($_SESSION['admin_id']) ? 'admin/index.php' : 'dashboard.php');
}
portal_no_cache();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!csrf_valid()) {
        $error = 'Your session expired. Please try again.';
    } elseif ($email === '' || strlen($email) > 200 || $password === '' || strlen($password) > 1024) {
        $error = 'Invalid email or password.';
    } else {
        $pdo         = portal_db();
        $ipHash      = portal_ip_hash();
        $accountHash = portal_hash(strtolower($email));

        if (login_blocked($pdo, $ipHash, $accountHash)) {
            $error = 'Too many sign-in attempts. Please wait 15 minutes and try again.';
        } else {
            $account = null;
            $role    = '';

            $stmt = $pdo->prepare('SELECT id, username, password FROM admins WHERE username = ?');
            $stmt->execute([$email]);
            $row = $stmt->fetch();
            if ($row && password_verify($password, $row['password'])) {
                $account = $row;
                $role    = 'admin';
            }
            if (!$account) {
                $stmt = $pdo->prepare('SELECT id, name, password FROM clients WHERE email = ?');
                $stmt->execute([$email]);
                $row = $stmt->fetch();
                if ($row && password_verify($password, $row['password'])) {
                    $account = $row;
                    $role    = 'client';
                }
            }

            if (!$account) {
                // Same work whether or not the email exists, so response time gives nothing away.
                password_verify($password, password_hash('not-a-real-account', PASSWORD_DEFAULT));
                login_record_failure($pdo, $ipHash, $accountHash);
                $error = 'Invalid email or password.';
            } else {
                if (password_needs_rehash($account['password'], PASSWORD_DEFAULT)) {
                    $table = $role === 'admin' ? 'admins' : 'clients';
                    $pdo->prepare("UPDATE $table SET password = ? WHERE id = ?")
                        ->execute([password_hash($password, PASSWORD_DEFAULT), (int) $account['id']]);
                }
                login_clear_failures($pdo, $accountHash);
                if ($role === 'admin') {
                    portal_sign_in(['admin_id' => (int) $account['id'], 'admin_name' => $account['username']]);
                    portal_redirect('admin/index.php');
                }
                portal_sign_in(['user_id' => (int) $account['id'], 'user_name' => $account['name']]);
                portal_redirect('dashboard.php');
            }
        }
    }
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
    <link rel="stylesheet" href="/portal/assets/portal.css?v=3">
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
                <?php if ($error): ?><div class="error-box" role="alert"><?= h($error) ?></div><?php endif; ?>
                <form method="POST" action="/portal/login.php">
                    <?= csrf_field() ?>
                    <div class="lf-group">
                        <label for="lf-email">Email address</label>
                        <input type="email" id="lf-email" name="email" required autofocus autocomplete="username" maxlength="200" placeholder="you@company.com" value="<?= h($_POST['email'] ?? '') ?>">
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
