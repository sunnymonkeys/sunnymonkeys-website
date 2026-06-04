<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: https://sunnymonkeys.com/portal/login.php'); exit; }
require_once __DIR__ . '/../config/db.php';
$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8', DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$id = intval($_GET['id'] ?? 0);
$client = $pdo->prepare('SELECT * FROM clients WHERE id = ?');
$client->execute([$id]);
$client = $client->fetch(PDO::FETCH_ASSOC);
if (!$client) { die('Client not found.'); }

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_doc'])) {
    $doc_id = intval($_POST['delete_doc']);
    $doc = $pdo->prepare('SELECT filename FROM documents WHERE id = ? AND client_id = ?');
    $doc->execute([$doc_id, $id]);
    $doc = $doc->fetch(PDO::FETCH_ASSOC);
    if ($doc) {
        @unlink(__DIR__ . '/../uploads/' . $doc['filename']);
        $pdo->prepare('DELETE FROM documents WHERE id = ?')->execute([$doc_id]);
    }
    header('Location: https://sunnymonkeys.com/portal/admin/client-view.php?id=' . $id);
    exit;
}

$docs = $pdo->prepare('SELECT * FROM documents WHERE client_id = ? ORDER BY uploaded_at DESC');
$docs->execute([$id]);
$docs = $docs->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($client['name']) ?> — Sunny Monkeys</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: #0d0d0d; color: #fff; font-family: 'Segoe UI', sans-serif; min-height: 100vh; }
  header { background: #111; border-bottom: 1px solid #222; padding: 16px 32px; display: flex; align-items: center; justify-content: space-between; }
  header .brand { display: flex; align-items: center; gap: 12px; }
  header img { width: 36px; height: 36px; object-fit: contain; border-radius: 50%; }
  header h1 { font-size: 1rem; font-weight: 600; }
  header nav a { color: #aaa; font-size: 0.88rem; text-decoration: none; margin-left: 20px; }
  header nav a:hover { color: #fff; }
  .container { max-width: 900px; margin: 0 auto; padding: 40px 24px; }
  .client-header { margin-bottom: 32px; }
  .client-header h2 { font-size: 1.5rem; font-weight: 600; }
  .client-header p { color: #666; font-size: 0.9rem; margin-top: 4px; }
  .top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
  .section-title { font-size: 0.75rem; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; color: #888; margin-bottom: 12px; margin-top: 32px; }
  .doc-list { display: flex; flex-direction: column; gap: 8px; }
  .doc-item { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 10px; padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; }
  .doc-info { display: flex; align-items: center; gap: 12px; }
  .doc-title { font-size: 0.9rem; font-weight: 500; }
  .doc-date { font-size: 0.78rem; color: #666; margin-top: 2px; }
  .actions { display: flex; gap: 8px; }
  .btn-sm { background: #222; border: 1px solid #333; color: #fff; padding: 6px 12px; border-radius: 6px; font-size: 0.8rem; text-decoration: none; cursor: pointer; }
  .btn-sm:hover { background: #333; }
  .btn-sm.danger { border-color: #5a1a1a; color: #ff6b6b; }
  .btn-sm.danger:hover { background: #2a0a0a; }
  .btn { background: #fff; color: #000; border: none; border-radius: 8px; padding: 10px 20px; font-size: 0.88rem; font-weight: 600; cursor: pointer; text-decoration: none; }
  .empty { color: #555; font-size: 0.9rem; padding: 16px 0; }
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
  <div class="top">
    <div class="client-header">
      <h2><?= htmlspecialchars($client['name']) ?></h2>
      <p><?= htmlspecialchars($client['email']) ?><?= $client['company'] ? ' · ' . htmlspecialchars($client['company']) : '' ?></p>
    </div>
    <a href="upload.php?client_id=<?= $client['id'] ?>" class="btn">+ Upload Document</a>
  </div>

  <?php foreach (['invoice' => '🧾 Invoices', 'contract' => '📄 Contracts'] as $type => $label):
    $filtered = array_filter($docs, fn($d) => $d['type'] === $type); ?>
  <div class="section-title"><?= $label ?></div>
  <div class="doc-list">
    <?php if (empty($filtered)): ?>
      <p class="empty">None yet.</p>
    <?php else: foreach ($filtered as $doc): ?>
      <div class="doc-item">
        <div class="doc-info">
          <div>
            <div class="doc-title"><?= htmlspecialchars($doc['title']) ?></div>
            <div class="doc-date"><?= date('M j, Y', strtotime($doc['uploaded_at'])) ?></div>
          </div>
        </div>
        <div class="actions">
          <a href="../download.php?id=<?= $doc['id'] ?>" class="btn-sm">Download</a>
          <form method="POST" style="display:inline" onsubmit="return confirm('Delete this document?')">
            <input type="hidden" name="delete_doc" value="<?= $doc['id'] ?>">
            <button type="submit" class="btn-sm danger">Delete</button>
          </form>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>
  <?php endforeach; ?>
</div>
</body>
</html>
