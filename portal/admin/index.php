<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: https://sunnymonkeys.com/portal/login.php'); exit; }
require_once __DIR__ . '/../config/db.php';
$pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8', DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$clients = $pdo->query('SELECT c.*, COUNT(d.id) as doc_count FROM clients c LEFT JOIN documents d ON c.id = d.client_id GROUP BY c.id ORDER BY c.created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Clients | Sunny Monkeys admin</title>
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
    <a href="inquiries.php">Inquiries</a>
    <a href="clients.php">New client</a>
    <a href="upload.php">Upload document</a>
    <a href="../logout.php">Sign out</a>
  </nav>
</header>
<div class="container">
  <div class="top"><h2>Clients</h2><a href="clients.php" class="btn">Add client</a></div>
  <?php if (empty($clients)): ?>
    <p class="empty">No clients yet.</p>
  <?php else: ?>
  <table>
    <thead><tr><th>Name</th><th>Email</th><th>Company</th><th>Documents</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($clients as $c): ?>
      <tr>
        <td><?= htmlspecialchars($c['name']) ?></td>
        <td><?= htmlspecialchars($c['email']) ?></td>
        <td><?= htmlspecialchars($c['company'] ?: '') ?></td>
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
<?php if (!empty($_GET['toast'])): ?>
<div class="toast" id="toast"><?= htmlspecialchars($_GET['toast']) ?></div>
<script>setTimeout(()=>{ const t=document.getElementById('toast'); if(t)t.style.opacity='0'; },3500);</script>
<?php endif; ?>
</body>
</html>
