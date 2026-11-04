<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: https://sunnymonkeys.com/portal/login.php'); exit;
}
require_once __DIR__ . '/config/db.php';
$pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8', DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$client_id  = $_SESSION['user_id'];
$first_name = explode(' ', trim($_SESSION['user_name']))[0];

$stmt = $pdo->prepare('SELECT * FROM documents WHERE client_id = ? ORDER BY uploaded_at DESC');
$stmt->execute([$client_id]);
$docs      = $stmt->fetchAll(PDO::FETCH_ASSOC);
$invoices  = array_values(array_filter($docs, fn($d) => $d['type'] === 'invoice'));
$contracts = array_values(array_filter($docs, fn($d) => $d['type'] === 'contract'));

$sub_stmt = $pdo->prepare('SELECT * FROM subscriptions WHERE client_id = ? AND visible = 1');
$sub_stmt->execute([$client_id]);
$sub = $sub_stmt->fetch(PDO::FETCH_ASSOC);

$addons = [];
if ($sub) {
    $a = $pdo->prepare('SELECT * FROM subscription_addons WHERE subscription_id = ? AND active = 1 ORDER BY id');
    $a->execute([$sub['id']]);
    $addons = $a->fetchAll(PDO::FETCH_ASSOC);
}

$req_msg = $_GET['req_msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Client Portal | Sunny Monkeys</title>
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
    <h1>Client Portal</h1>
  </div>
  <nav>
    <span><?= htmlspecialchars($first_name) ?></span>
    <a href="logout.php">Sign out</a>
  </nav>
</header>

<div class="container">
  <div class="page-title">Welcome back, <?= htmlspecialchars($first_name) ?>.</div>
  <div class="page-sub">Your portal with Sunny Monkeys LLC.</div>

  <?php if ($sub): ?>
  <!-- MY PLAN ─────────────────────────────────────────── -->
  <div class="section-label">My Plan</div>

  <!-- Base plan card -->
  <div class="plan-card">
    <div class="plan-card-header">
      <span class="plan-card-name"><?= htmlspecialchars($sub['base_name']) ?></span>
      <?php if ($sub['billing_cycle']): ?>
        <span class="meta">Billed <?= htmlspecialchars($sub['billing_cycle']) ?></span>
      <?php endif; ?>
    </div>
    <?php
    $base_bullets = !empty($sub['base_description'])
      ? array_filter(array_map('trim', explode("\n", $sub['base_description'])))
      : [];
    ?>
    <?php if (!empty($base_bullets)): ?>
    <ul class="plan-bullets">
      <?php foreach ($base_bullets as $b): ?>
        <li><?= htmlspecialchars($b) ?></li>
      <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <p class="plan-empty">Your base services are active.</p>
    <?php endif; ?>
  </div>

  <!-- Add-on cards -->
  <?php foreach ($addons as $addon):
    $is_promo = ($addon['promo_price'] !== null && $addon['promo_cycles_remaining'] > 0);
    $bullets  = !empty($addon['description'])
      ? array_filter(array_map('trim', explode("\n", $addon['description'])))
      : [];
  ?>
  <div class="plan-card">
    <div class="plan-card-header">
      <span class="plan-card-name"><?= htmlspecialchars($addon['name']) ?></span>
      <?php if ($is_promo): ?><span class="promo-chip">Promotional</span><?php endif; ?>
    </div>
    <?php if (!empty($bullets)): ?>
    <ul class="plan-bullets">
      <?php foreach ($bullets as $b): ?>
        <li><?= htmlspecialchars($b) ?></li>
      <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <p class="plan-empty">Service is active.</p>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

  <button class="request-btn" onclick="openPanel()">
    <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M7 1v12M1 7h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    Request a Change or Upgrade
  </button>
  <?php endif; ?>

  <!-- DOCUMENTS ───────────────────────────────────────── -->
  <div class="section-label" style="margin-top:<?= $sub ? '48px' : '0' ?>">Invoices</div>
  <div class="doc-card">
    <?php if (empty($invoices)): ?>
      <p class="empty-state">No invoices yet.</p>
    <?php else: foreach ($invoices as $doc): ?>
      <div class="doc-item">
        <div class="doc-info">
          <div class="doc-icon invoice"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/></svg></div>
          <div>
            <div class="doc-name"><?= htmlspecialchars($doc['title']) ?></div>
            <div class="doc-date"><?= date('M j, Y', strtotime($doc['uploaded_at'])) ?></div>
          </div>
        </div>
        <a href="download.php?id=<?= $doc['id'] ?>" class="dl-btn">Download PDF</a>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <div class="section-label">Contracts</div>
  <div class="doc-card">
    <?php if (empty($contracts)): ?>
      <p class="empty-state">No contracts yet.</p>
    <?php else: foreach ($contracts as $doc): ?>
      <div class="doc-item">
        <div class="doc-info">
          <div class="doc-icon contract"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l4 4v14H6z"/><path d="M15 3v4h4M9 12h7M9 16h7"/></svg></div>
          <div>
            <div class="doc-name"><?= htmlspecialchars($doc['title']) ?></div>
            <div class="doc-date"><?= date('M j, Y', strtotime($doc['uploaded_at'])) ?></div>
          </div>
        </div>
        <a href="download.php?id=<?= $doc['id'] ?>" class="dl-btn">Download PDF</a>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<!-- SLIDE-IN PANEL ──────────────────────────────────── -->
<div class="overlay" id="overlay" onclick="closePanel()"></div>
<div class="panel" id="panel">
  <div class="panel-head">
    <h3>Request a Change</h3>
    <button class="panel-close" onclick="closePanel()">×</button>
  </div>
  <div class="panel-body">
    <p class="panel-hint">Let us know what you'd like to add, remove, or ask about. We'll follow up by email.</p>
    <form action="subscription-request.php" method="POST">
      <div class="p-group">
        <label>Request Type</label>
        <select name="type" required>
          <option value="">Choose one</option>
          <option value="addon">Add a service</option>
          <option value="removal">Remove a service</option>
          <option value="custom">Question or custom request</option>
        </select>
      </div>
      <div class="p-group">
        <label>Message</label>
        <textarea name="message" placeholder="Describe what you'd like to change or ask about." required></textarea>
      </div>
      <button type="submit" class="panel-submit">Send Request</button>
    </form>
  </div>
</div>

<?php if ($req_msg): ?>
<div class="toast" id="toast"><?= htmlspecialchars($req_msg) ?></div>
<script>setTimeout(()=>{ const t=document.getElementById('toast'); if(t){t.style.transition='opacity .4s';t.style.opacity='0';} },4000);</script>
<?php endif; ?>

<script>
function openPanel() {
  document.getElementById('overlay').classList.add('open');
  document.getElementById('panel').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closePanel() {
  document.getElementById('overlay').classList.remove('open');
  document.getElementById('panel').classList.remove('open');
  document.body.style.overflow = '';
}
</script>
</body>
</html>
