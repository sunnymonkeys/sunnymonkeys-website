<?php
require_once __DIR__ . '/../lib/core.php';
require_admin();
$pdo = portal_db();
$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name     = trim((string) ($_POST['name'] ?? ''));
    $email    = trim((string) ($_POST['email'] ?? ''));
    $company  = trim((string) ($_POST['company'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($name === '' || portal_len($name) > 120) {
        $error = 'Please enter a name (up to 120 characters).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 200) {
        $error = 'Please enter a valid email address.';
    } elseif (portal_len($company) > 120) {
        $error = 'The company name is too long (120 characters max).';
    } elseif (strlen($password) < 10 || strlen($password) > 1024) {
        $error = 'The temporary password needs at least 10 characters.';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO clients (name, email, company, password) VALUES (?, ?, ?, ?)')->execute([$name, $email, $company, $hash]);
            if (($_POST['action'] ?? '') === 'view') {
                flash_set("Client \"$name\" created successfully.");
                portal_redirect('admin/index.php');
            }
            $success = "Client \"$name\" created successfully.";
        } catch (PDOException $e) {
            error_log('clients.php: ' . $e->getMessage());
            $error = 'That email is already registered, or the database refused the request.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add client | Sunny Monkeys admin</title>
<meta name="robots" content="noindex">
<link rel="icon" type="image/png" href="/assets/images/fav.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700&family=Barlow:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/portal/assets/portal.css?v=3">
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
  <?php if ($success): ?><div class="success"><?= h($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
  <form method="POST">
    <?= csrf_field() ?>
    <label for="c-name">Full name</label><input type="text" id="c-name" name="name" required maxlength="120" placeholder="Jane Smith">
    <label for="c-email">Email address</label><input type="email" id="c-email" name="email" required maxlength="200" placeholder="jane@company.com">
    <label for="c-company">Company (optional)</label><input type="text" id="c-company" name="company" maxlength="120" placeholder="Acme Inc.">
    <label for="c-pass">Temporary password (10 characters or more)</label><input type="text" id="c-pass" name="password" required minlength="10" autocomplete="off" placeholder="They can change this later">
    <div style="display:flex;gap:12px;margin-top:8px;flex-wrap:wrap">
      <button type="submit" name="action" value="view" class="btn">Create and view clients</button>
      <button type="submit" name="action" value="another" class="btn btn-outline">Create and add another</button>
    </div>
  </form>
</div>
</body>
</html>
