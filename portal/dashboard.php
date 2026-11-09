<?php
require_once __DIR__ . '/lib/core.php';
require_client();
$pdo = portal_db();

$client_id  = (int) $_SESSION['user_id'];
$first_name = explode(' ', trim((string) ($_SESSION['user_name'] ?? '')))[0];

$stmt = $pdo->prepare('SELECT id, title, type, uploaded_at FROM documents WHERE client_id = ? ORDER BY uploaded_at DESC');
$stmt->execute([$client_id]);
$docs      = $stmt->fetchAll();
$invoices  = array_values(array_filter($docs, fn($d) => $d['type'] === 'invoice'));
$contracts = array_values(array_filter($docs, fn($d) => $d['type'] === 'contract'));

$sub_stmt = $pdo->prepare('SELECT id, base_name, base_description, billing_cycle FROM subscriptions WHERE client_id = ? AND visible = 1');
$sub_stmt->execute([$client_id]);
$sub = $sub_stmt->fetch();

$addons = [];
if ($sub) {
    $a = $pdo->prepare('SELECT id, name, description, promo_price, promo_cycles_remaining FROM subscription_addons WHERE subscription_id = ? AND active = 1 ORDER BY id');
    $a->execute([(int) $sub['id']]);
    $addons = $a->fetchAll();
}

$req_msg = flash_take();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Client Portal | Sunny Monkeys</title>
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
    <h1>Client Portal</h1>
  </div>
  <nav>
    <span><?= h($first_name) ?></span>
    <a href="logout.php">Sign out</a>
  </nav>
</header>

<div class="container">
  <div class="page-title">Welcome back, <?= h($first_name) ?>.</div>
  <div class="page-sub">Your portal with Sunny Monkeys LLC.</div>

  <?php if ($sub): ?>
  <!-- MY PLAN ─────────────────────────────────────────── -->
  <div class="section-label">My Plan</div>

  <!-- Base plan card -->
  <div class="plan-card">
    <div class="plan-card-header">
      <span class="plan-card-name"><?= h($sub['base_name']) ?></span>
      <?php if ($sub['billing_cycle']): ?>
        <span class="meta">Billed <?= h($sub['billing_cycle']) ?></span>
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
        <li><?= h($b) ?></li>
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
      <span class="plan-card-name"><?= h($addon['name']) ?></span>
      <?php if ($is_promo): ?><span class="promo-chip">Promotional</span><?php endif; ?>
    </div>
    <?php if (!empty($bullets)): ?>
    <ul class="plan-bullets">
      <?php foreach ($bullets as $b): ?>
        <li><?= h($b) ?></li>
      <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <p class="plan-empty">Service is active.</p>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

  <button class="request-btn" type="button" data-panel-open>
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
            <div class="doc-name"><?= h($doc['title']) ?></div>
            <div class="doc-date"><?= h(date('M j, Y', strtotime($doc['uploaded_at']))) ?></div>
          </div>
        </div>
        <a href="download.php?id=<?= (int) $doc['id'] ?>" class="dl-btn">Download PDF</a>
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
            <div class="doc-name"><?= h($doc['title']) ?></div>
            <div class="doc-date"><?= h(date('M j, Y', strtotime($doc['uploaded_at']))) ?></div>
          </div>
        </div>
        <a href="download.php?id=<?= (int) $doc['id'] ?>" class="dl-btn">Download PDF</a>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<!-- SLIDE-IN PANEL ──────────────────────────────────── -->
<div class="overlay" id="overlay" data-panel-close></div>
<div class="panel" id="panel">
  <div class="panel-head">
    <h3>Request a Change</h3>
    <button class="panel-close" type="button" data-panel-close aria-label="Close">×</button>
  </div>
  <div class="panel-body">
    <p class="panel-hint">Let us know what you'd like to add, remove, or ask about. We'll follow up by email.</p>
    <form action="subscription-request.php" method="POST">
      <?= csrf_field() ?>
      <div class="p-group">
        <label for="req-type">Request Type</label>
        <select name="type" id="req-type" required>
          <option value="">Choose one</option>
          <option value="addon">Add a service</option>
          <option value="removal">Remove a service</option>
          <option value="custom">Question or custom request</option>
        </select>
      </div>
      <div class="p-group">
        <label for="req-message">Message</label>
        <textarea name="message" id="req-message" maxlength="5000" placeholder="Describe what you'd like to change or ask about." required></textarea>
      </div>
      <button type="submit" class="panel-submit">Send Request</button>
    </form>
  </div>
</div>

<?php if ($req_msg): ?>
<div class="toast" data-toast><?= h($req_msg) ?></div>
<?php endif; ?>

<script src="/portal/assets/portal.js?v=1" defer></script>
</body>
</html>
