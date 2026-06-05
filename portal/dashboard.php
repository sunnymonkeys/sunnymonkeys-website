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
<title>Sunny Monkeys</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: #0d0d0d; color: #fff; font-family: 'Segoe UI', sans-serif; min-height: 100vh; }

  /* Header */
  header { background: #111; border-bottom: 1px solid #222; padding: 16px 32px; display: flex; align-items: center; justify-content: space-between; }
  header .brand { display: flex; align-items: center; gap: 12px; }
  header img { height: 32px; width: auto; display: block; }
  header h1 { font-size: 1rem; font-weight: 600; }
  header nav { display: flex; align-items: center; gap: 20px; }
  header nav span { color: #888; font-size: 0.9rem; }
  header nav a { color: #aaa; font-size: 0.88rem; text-decoration: none; }
  header nav a:hover { color: #fff; }

  /* Layout */
  .container { max-width: 860px; margin: 0 auto; padding: 44px 24px 80px; }
  .page-title { font-size: 1.6rem; font-weight: 700; margin-bottom: 4px; }
  .page-sub { color: #555; font-size: 0.9rem; margin-bottom: 40px; }

  /* Section headers */
  .section-label { font-size: 0.7rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: #555; margin-bottom: 14px; margin-top: 40px; }
  .section-label:first-of-type { margin-top: 0; }

  /* Plan cards */
  .plan-card { background: #141414; border: 1px solid #222; border-radius: 14px; padding: 24px 26px; margin-bottom: 12px; }
  .plan-card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
  .plan-card-name { font-size: 1rem; font-weight: 700; color: #fff; }
  .promo-chip { background: #2a1a0a; border: 1px solid #5a3a0a; color: #f0a040; font-size: 0.7rem; font-weight: 600; padding: 3px 9px; border-radius: 20px; text-transform: uppercase; letter-spacing: .06em; }
  .plan-bullets { list-style: none; display: flex; flex-direction: column; gap: 8px; }
  .plan-bullets li { font-size: 0.9rem; color: #bbb; display: flex; align-items: flex-start; gap: 10px; line-height: 1.45; }
  .plan-bullets li::before { content: ""; display: inline-block; width: 5px; height: 5px; border-radius: 50%; background: #555; flex-shrink: 0; margin-top: 7px; }
  .plan-empty { color: #555; font-size: 0.88rem; font-style: italic; }

  /* Request button */
  .request-btn { display: inline-flex; align-items: center; gap: 8px; background: #1e1e1e; border: 1px solid #333; color: #ccc; padding: 11px 20px; border-radius: 8px; font-size: 0.88rem; font-weight: 500; cursor: pointer; transition: background .2s, color .2s; margin-top: 20px; }
  .request-btn:hover { background: #2a2a2a; color: #fff; }
  .request-btn svg { opacity: .6; }

  /* Doc cards */
  .doc-card { background: #141414; border: 1px solid #222; border-radius: 14px; overflow: hidden; margin-bottom: 12px; }
  .doc-card-title { font-size: 0.7rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #555; padding: 16px 20px 12px; border-bottom: 1px solid #1e1e1e; }
  .doc-item { display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; border-top: 1px solid #1a1a1a; }
  .doc-item:first-of-type { border-top: none; }
  .doc-info { display: flex; align-items: center; gap: 14px; }
  .doc-icon { width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; }
  .doc-icon.invoice { background: #1a2a1a; }
  .doc-icon.contract { background: #1a1a2a; }
  .doc-name { font-size: 0.92rem; font-weight: 500; }
  .doc-date { font-size: 0.78rem; color: #555; margin-top: 2px; }
  .dl-btn { background: #1e1e1e; border: 1px solid #2e2e2e; color: #aaa; padding: 7px 14px; border-radius: 6px; font-size: 0.8rem; text-decoration: none; transition: background .2s; white-space: nowrap; }
  .dl-btn:hover { background: #2a2a2a; color: #fff; }
  .empty-state { color: #444; font-size: 0.88rem; padding: 20px; }

  /* Slide-in panel */
  .overlay { position: fixed; inset: 0; background: rgba(0,0,0,.65); z-index: 100; opacity: 0; pointer-events: none; transition: opacity .3s; }
  .overlay.open { opacity: 1; pointer-events: all; }
  .panel { position: fixed; top: 0; right: 0; bottom: 0; width: 440px; max-width: 100vw; background: #161616; border-left: 1px solid #2a2a2a; z-index: 101; transform: translateX(100%); transition: transform .3s cubic-bezier(.23,1,.32,1); display: flex; flex-direction: column; }
  .panel.open { transform: translateX(0); }
  .panel-head { padding: 24px 28px 20px; border-bottom: 1px solid #222; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
  .panel-head h3 { font-size: 1.1rem; font-weight: 700; }
  .panel-close { background: none; border: none; color: #666; font-size: 1.4rem; cursor: pointer; padding: 4px; line-height: 1; }
  .panel-close:hover { color: #fff; }
  .panel-body { padding: 28px; flex: 1; overflow-y: auto; }
  .panel-hint { font-size: 0.85rem; color: #555; margin-bottom: 24px; line-height: 1.5; }
  .p-group { margin-bottom: 20px; }
  .p-group label { display: block; font-size: 0.72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #666; margin-bottom: 7px; }
  .p-group select, .p-group textarea { width: 100%; background: #111; border: 1px solid #2e2e2e; border-radius: 8px; padding: 12px 14px; color: #fff; font-size: 0.92rem; outline: none; font-family: inherit; }
  .p-group select:focus, .p-group textarea:focus { border-color: #444; }
  .p-group select option { background: #111; }
  .p-group textarea { resize: vertical; min-height: 120px; }
  .panel-submit { width: 100%; background: #fff; color: #000; border: none; border-radius: 8px; padding: 14px; font-size: 0.95rem; font-weight: 700; cursor: pointer; transition: background .2s; }
  .panel-submit:hover { background: #e8e8e8; }

  /* Toast */
  .toast { position: fixed; top: 20px; right: 20px; background: #1a3a1a; border: 1px solid #2a6a2a; color: #6fcf6f; padding: 12px 20px; border-radius: 10px; font-size: 0.88rem; font-weight: 500; box-shadow: 0 4px 20px rgba(0,0,0,.4); z-index: 999; animation: fadeIn .3s ease; }
  @keyframes fadeIn { from { opacity:0; transform:translateX(20px); } to { opacity:1; transform:translateX(0); } }
</style>
</head>
<body>

<header>
  <div class="brand">
    <img src="/assets/images/logo-sunny-monkeys-white.png" alt="Sunny Monkeys">
    <h1>Client Portal</h1>
  </div>
  <nav>
    <span>👋 <?= htmlspecialchars($first_name) ?></span>
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
        <span style="font-size:0.78rem;color:#555">Billed <?= htmlspecialchars($sub['billing_cycle']) ?></span>
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
          <div class="doc-icon invoice">🧾</div>
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
          <div class="doc-icon contract">📄</div>
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
    <p class="panel-hint">Let us know what you'd like to add, remove, or ask about. We'll follow up within one business day.</p>
    <form action="subscription-request.php" method="POST">
      <div class="p-group">
        <label>Request Type</label>
        <select name="type" required>
          <option value="">— Select —</option>
          <option value="addon">Add a service</option>
          <option value="removal">Remove a service</option>
          <option value="custom">Question or custom request</option>
        </select>
      </div>
      <div class="p-group">
        <label>Message</label>
        <textarea name="message" placeholder="Describe what you'd like to change or ask about…" required></textarea>
      </div>
      <button type="submit" class="panel-submit">Send Request</button>
    </form>
  </div>
</div>

<?php if ($req_msg): ?>
<div class="toast" id="toast">✓ <?= htmlspecialchars($req_msg) ?></div>
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
