<?php
session_start();

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

require_once '../config/db.php';
$db = getDB();

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$name || !$email || !$password) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        // Check duplicate
        $chk = $db->prepare("SELECT id FROM users WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            $error = 'A client with that email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $ins  = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'client')");
            $ins->execute([$name, $email, $hash]);
            $success = "Client <strong>" . htmlspecialchars($name) . "</strong> added successfully. They can now log in at <code>/portal/login.php</code>.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Client — Sunny Monkeys Portal</title>
    <link rel="shortcut icon" href="../../assets/images/fav.png">
    <link rel="stylesheet" href="../assets/portal.css">
</head>
<body>

<div class="portal-wrap">

    <header class="portal-header">
        <div style="display:flex;align-items:center;">
            <a href="../../index.html" class="logo"><img src="../../assets/images/logo-sunny-monkeys-white.png" alt="Sunny Monkeys"></a>
            <span class="logo-name">Admin Panel</span>
        </div>
        <div class="header-right">
            <a href="index.php">← Dashboard</a>
            <a href="../logout.php">Sign Out</a>
        </div>
    </header>

    <main class="portal-main">
        <h1 class="page-title">Add New Client</h1>
        <p class="page-subtitle">Create a login for a client so they can access their documents.</p>

        <nav class="admin-nav">
            <a href="index.php">Clients</a>
            <a href="upload.php">Upload Document</a>
            <a href="clients.php" class="active">Add Client</a>
        </nav>

        <div class="card" style="max-width:560px;">
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="name">Client Name</label>
                    <input type="text" id="name" name="name" placeholder="Acme Corp or Jane Doe"
                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="client@company.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="password">Temporary Password</label>
                    <input type="text" id="password" name="password" placeholder="min. 8 characters" required>
                    <p style="font-size:0.8rem;color:rgba(255,255,255,0.3);margin-top:6px;">
                        Share this with the client. They can't change it yet — that feature can be added later.
                    </p>
                </div>
                <button type="submit" class="btn btn-primary">Create Client Account</button>
            </form>
        </div>
    </main>
</div>
</body>
</html>
