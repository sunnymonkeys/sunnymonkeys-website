<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: https://sunnymonkeys.com/portal/login.php');
    exit;
}
require_once __DIR__ . '/../config/db.php';
$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8', DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$clients = $pdo->query('SELECT c.*, COUNT(d.id) as doc_count FROM clients c LEFT JOIN documents d ON c.id = d.client_id GROUP BY c.id ORDER BY c.created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — Sunny Monkeys</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: #0d0d0d; color: #fff; font-family: 'Segoe UI', sans-serif; min-height: 100vh; }
  header { background: #111; border-bottom: 1px solid #222; padding: 16px 32px; display: flex; align-items: center; justify-content: space-between; }
  header .brand { display: flex; align-items: center; gap: 12px; }
  header img { width: 36px; height: 36px; object-fit: contain; border-radius: 50%; }
  header h1 { font-size: 1rem; font-weight: 600; }
  header nav a { color: #aaa; font-size: 0.88rem; text-decoration: none; margin-left: 20px; }
  header nav a:hover { color: #fff; }
  .container { max-width: 960px; margin: 0 auto; padding: 40px 24px; }
  .top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; }
  h2 { font-size: 1.5rem; font-weight: 600; }
  .btn { background: #fff; color: #000; border: none; border-radius: 8px; padding: 10px 20px; font-size: 0.88rem; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
  .btn:hover { background: #e0e0e0; }
  .btn-sm { background: #222; border: 1px solid #333; color: #fff; padding: 7px 14px; border-radius: 6px; font-size: 0.82rem; text-decoration: none; }
  .btn-sm:hover { background: #333; }
  table { width: 100%; border-collapse: collapse; }
  th { text-align: left; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #666; padding: 0 16px 12px; }
  td { padding: 14px 16px; border-top: 1px solid #1e1e1e; font-size: 0.9rem; }
  tr:hover td { background: #141414; }
  .badge { background: #1e2e1e; color: #6fcf6f; font-size: 0.75rem; padding: 3px 8px; border-radius: 4px; }
  .empty { color: #555; text-align: center; padding: 48px; }
</style>
</head>
<body>
<header>
  <div class="brand">
    <img src="/assets/images/logo-sunny-monkeys-white.png" alt="Sunny Monkeys">
    <h1>Admin Portal</h1>
  </div>
  <nav>
    <a href="clients.php">+ New Client</a>
    <a href="upload.php">Upload Doc</a>
    <a href="../logout.php">Sign out</a>
  </nav>
</header>
<div class="container">
  <div class="top">
    <h2>Clients</h2>
    <a href="clients.php" class="btn">+ Add Client</a>
  </div>
  <?php if (empty($clients)): ?>
    <p class="empty">No clients yet. Add your first one!</p>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th>Name</th>
        <th>Email</th>
        <th>Company</th>
        <th>Documents</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($clients as $c): ?>
      <tr>
        <td><?= htmlspecialchars($c['name']) ?></td>
        <td><?= htmlspecialchars($c['email']) ?></td>
        <td><?= htmlspecialchars($c['company'] ?? '—') ?></td>
        <td><span class="badge"><?= $c['doc_count'] ?> docs</span></td>
        <td>
          <a href="client-view.php?id=<?= $c['id'] ?>" class="btn-sm">View</a>
          <a href="upload.php?client_id=<?= $c['id'] ?>" class="btn-sm" style="margin-left:6px">Upload</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
</body>
</html>
