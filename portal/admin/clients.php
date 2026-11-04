<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: https://sunnymonkeys.com/portal/login.php'); exit; }
require_once __DIR__ . '/../config/db.php';
$pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8', DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? ''); $email = trim($_POST['email'] ?? '');
    $company = trim($_POST['company'] ?? ''); $password = $_POST['password'] ?? '';
    if (!$name || !$email || !$password) {
        $error = 'Name, email, and password are required.';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO clients (name, email, company, password) VALUES (?, ?, ?, ?)')->execute([$name, $email, $company, $hash]);
            if (isset($_POST['action']) && $_POST['action'] === 'view') {
                header('Location: https://sunnymonkeys.com/portal/admin/index.php?toast=' . urlencode("Client \"$name\" created successfully.")); exit;
            }
            $success = "Client \"$name\" created successfully.";
        } catch (Exception $e) { $error = 'Email already exists or database error.'; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add client | Sunny Monkeys admin</title>
<link rel="icon" type="image/png" href="/assets/images/fav.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700&family=Barlow:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/portal/assets/portal.css?v=2">
</head>
<body>
<header>
  <div class="brand">
    <a class="logo" href="/"><span class="mark" aria-hidden="true"></span>Sunny Monkeys</a>
    <h1>Admin Portal</h1>
  </div>
  <nav><a href="index.php">Clients</a><a href="../logout.php">Sign out</a></nav>
</header>
<div class="container narrow">
  <h2>Add a client</h2>
  <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="POST">
    <label>Full name</label><input type="text" name="name" required placeholder="Jane Smith">
    <label>Email address</label><input type="email" name="email" required placeholder="jane@company.com">
    <label>Company (optional)</label><input type="text" name="company" placeholder="Acme Inc.">
    <label>Temporary password</label><input type="text" name="password" required placeholder="They can change this later">
    <div style="display:flex;gap:12px;margin-top:8px;flex-wrap:wrap">
      <button type="submit" name="action" value="view" class="btn">Create and view clients</button>
      <button type="submit" name="action" value="another" class="btn btn-outline">Create and add another</button>
    </div>
  </form>
</div>
</body>
</html>
