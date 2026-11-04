<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: https://sunnymonkeys.com/portal/login.php'); exit; }
require_once __DIR__ . '/../config/db.php';
$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8', DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$clients = $pdo->query('SELECT id, name, company FROM clients ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$success = $error = '';
$preselect = intval($_GET['client_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = intval($_POST['client_id'] ?? 0);
    $type = $_POST['type'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $file = $_FILES['document'] ?? null;

    if (!$client_id || !$type || !$title || !$file || $file['error'] !== UPLOAD_ERR_OK) {
        $error = 'All fields are required and file must upload successfully.';
    } elseif ($file['type'] !== 'application/pdf') {
        $error = 'Only PDF files are allowed.';
    } elseif ($file['size'] > 10 * 1024 * 1024) {
        $error = 'File must be under 10MB.';
    } else {
        $filename = uniqid('doc_') . '_' . preg_replace('/[^a-z0-9._-]/i', '_', $file['name']);
        $dest = __DIR__ . '/../uploads/' . $filename;
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $stmt = $pdo->prepare('INSERT INTO documents (client_id, type, title, filename) VALUES (?, ?, ?, ?)');
            $stmt->execute([$client_id, $type, $title, $filename]);
            $success = 'Document uploaded successfully.';
        } else {
            $error = 'Failed to save file. Check uploads folder permissions.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Upload document | Sunny Monkeys admin</title>
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
  <nav>
    <a href="index.php">Clients</a>
    <a href="../logout.php">Sign out</a>
  </nav>
</header>
<div class="container narrow">
  <h2>Upload a document</h2>
  <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="POST" enctype="multipart/form-data">
    <label>Client</label>
    <select name="client_id" required>
      <option value="">Choose a client</option>
      <?php foreach ($clients as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $c['id'] == $preselect ? 'selected' : '' ?>>
          <?= htmlspecialchars($c['name']) ?><?= $c['company'] ? ', ' . htmlspecialchars($c['company']) : '' ?>
        </option>
      <?php endforeach; ?>
    </select>
    <label>Document type</label>
    <select name="type" required>
      <option value="">Choose a type</option>
      <option value="invoice">Invoice</option>
      <option value="contract">Contract</option>
    </select>
    <label>Document title</label>
    <input type="text" name="title" required placeholder="e.g. Invoice 001, June 2026">
    <label>PDF file (max 10MB)</label>
    <input type="file" name="document" accept=".pdf" required>
    <button type="submit" class="btn">Upload document</button>
  </form>
</div>
</body>
</html>
