<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: https://sunnymonkeys.com/portal/login.php');
    exit;
}
require_once __DIR__ . '/config/db.php';
$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8', DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $pdo->prepare('SELECT * FROM documents WHERE client_id = ? ORDER BY uploaded_at DESC');
$stmt->execute([$_SESSION['user_id']]);
$docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$invoices = array_filter($docs, fn($d) => $d['type'] === 'invoice');
$contracts = array_filter($docs, fn($d) => $d['type'] === 'contract');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Documents — Sunny Monkeys</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: #0d0d0d; color: #fff; font-family: 'Segoe UI', sans-serif; min-height: 100vh; }
  header { background: #111; border-bottom: 1px solid #222; padding: 16px 32px; display: flex; align-items: center; justify-content: space-between; }
  header .brand { display: flex; align-items: center; gap: 12px; }
  header img { width: 36px; height: 36px; object-fit: contain; border-radius: 50%; }
  header h1 { font-size: 1rem; font-weight: 600; }
  header nav { display: flex; align-items: center; gap: 20px; }
  header nav span { color: #888; font-size: 0.9rem; }
  header nav a { color: #aaa; font-size: 0.88rem; text-decoration: none; }
  header nav a:hover { color: #fff; }
  .container { max-width: 900px; margin: 0 auto; padding: 40px 24px; }
  h2 { font-size: 1.5rem; font-weight: 600; margin-bottom: 8px; }
  .subtitle { color: #666; font-size: 0.9rem; margin-bottom: 36px; }
  .section-title { font-size: 0.75rem; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; color: #888; margin-bottom: 12px; margin-top: 36px; }
  .doc-list { display: flex; flex-direction: column; gap: 8px; }
  .doc-item { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 10px; padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; }
  .doc-info { display: flex; align-items: center; gap: 14px; }
  .doc-icon { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
  .doc-icon.invoice { background: #1a2a1a; }
  .doc-icon.contract { background: #1a1a2a; }
  .doc-title { font-size: 0.95rem; font-weight: 500; }
  .doc-date { font-size: 0.8rem; color: #666; margin-top: 2px; }
  .download-btn { background: #222; border: 1px solid #333; color: #fff; padding: 8px 16px; border-radius: 6px; font-size: 0.82rem; text-decoration: none; transition: background 0.2s; }
  .download-btn:hover { background: #333; }
  .empty { color: #555; font-size: 0.9rem; padding: 20px 0; }
</style>
</head>
<body>
<header>
  <div class="brand">
    <img src="/assets/images/logo-sunny-monkeys-white.png" alt="Sunny Monkeys">
    <h1>Client Portal</h1>
  </div>
  <nav>
    <span>👋 <?= htmlspecialchars($_SESSION['user_name']) ?></span>
    <a href="logout.php">Sign out</a>
  </nav>
</header>
<div class="container">
  <h2>My Documents</h2>
  <p class="subtitle">Your invoices and contracts from Sunny Monkeys LLC.</p>

  <div class="section-title">Invoices</div>
  <div class="doc-list">
    <?php if (empty($invoices)): ?>
      <p class="empty">No invoices yet.</p>
    <?php else: foreach ($invoices as $doc): ?>
      <div class="doc-item">
        <div class="doc-info">
          <div class="doc-icon invoice">🧾</div>
          <div>
            <div class="doc-title"><?= htmlspecialchars($doc['title']) ?></div>
            <div class="doc-date"><?= date('M j, Y', strtotime($doc['uploaded_at'])) ?></div>
          </div>
        </div>
        <a href="download.php?id=<?= $doc['id'] ?>" class="download-btn">Download PDF</a>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <div class="section-title">Contracts</div>
  <div class="doc-list">
    <?php if (empty($contracts)): ?>
      <p class="empty">No contracts yet.</p>
    <?php else: foreach ($contracts as $doc): ?>
      <div class="doc-item">
        <div class="doc-info">
          <div class="doc-icon contract">📄</div>
          <div>
            <div class="doc-title"><?= htmlspecialchars($doc['title']) ?></div>
            <div class="doc-date"><?= date('M j, Y', strtotime($doc['uploaded_at'])) ?></div>
          </div>
        </div>
        <a href="download.php?id=<?= $doc['id'] ?>" class="download-btn">Download PDF</a>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>
</body>
</html>
