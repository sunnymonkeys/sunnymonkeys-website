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
<title>Upload Document — Sunny Monkeys</title>
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
  select, input { width: 100%; background: #111; border: 1px solid #333; border-radius: 8px; padding: 12px 14px; color: #fff; font-size: 0.95rem; margin-bottom: 20px; outline: none; }
  select:focus, input:focus { border-color: #555; }
  .btn { background: #fff; color: #000; border: none; border-radius: 8px; padding: 12px 24px; font-size: 0.95rem; font-weight: 600; cursor: pointer; }
  .btn:hover { background: #e0e0e0; }
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
  <h2>Upload Document</h2>
  <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="POST" enctype="multipart/form-data">
    <label>Client</label>
    <select name="client_id" required>
      <option value="">Select a client…</option>
      <?php foreach ($clients as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $c['id'] == $preselect ? 'selected' : '' ?>>
          <?= htmlspecialchars($c['name']) ?><?= $c['company'] ? ' — ' . htmlspecialchars($c['company']) : '' ?>
        </option>
      <?php endforeach; ?>
    </select>
    <label>Document Type</label>
    <select name="type" required>
      <option value="">Select type…</option>
      <option value="invoice">Invoice</option>
      <option value="contract">Contract</option>
    </select>
    <label>Document Title</label>
    <input type="text" name="title" required placeholder="e.g. Invoice #001 — June 2026">
    <label>PDF File (max 10MB)</label>
    <input type="file" name="document" accept=".pdf" required>
    <button type="submit" class="btn">Upload Document</button>
  </form>
</div>
</body>
</html>
