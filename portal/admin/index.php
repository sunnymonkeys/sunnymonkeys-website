<?php
require_once __DIR__ . '/../lib/core.php';
require_admin();
$pdo = portal_db();
$clients = $pdo->query('SELECT c.id, c.name, c.email, c.company, c.created_at, COUNT(d.id) AS doc_count
                        FROM clients c LEFT JOIN documents d ON c.id = d.client_id
                        GROUP BY c.id, c.name, c.email, c.company, c.created_at
                        ORDER BY c.created_at DESC')->fetchAll();
$toast = flash_take();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Clients | Sunny Monkeys admin</title>
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
        <td><?= h($c['name']) ?></td>
        <td><?= h($c['email']) ?></td>
        <td><?= h($c['company'] ?: '') ?></td>
        <td><span class="badge"><?= (int) $c['doc_count'] ?> docs</span></td>
        <td>
          <a href="client-view.php?id=<?= (int) $c['id'] ?>" class="btn-sm">View</a>
          <a href="upload.php?client_id=<?= (int) $c['id'] ?>" class="btn-sm" style="margin-left:6px">Upload</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php if ($toast): ?>
<div class="toast" data-toast><?= h($toast) ?></div>
<?php endif; ?>
<script src="/portal/assets/portal.js?v=1" defer></script>
</body>
</html>
