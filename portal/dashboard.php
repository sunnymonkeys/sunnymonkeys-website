<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: https://sunnymonkeys.com/portal/login.php'); exit;
}
require_once __DIR__ . '/config/db.php';
$pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8', DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$client_id = $_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT * FROM documents WHERE client_id = ? ORDER BY uploaded_at DESC');
$stmt->execute([$client_id]);
$docs      = $stmt->fetchAll(PDO::FETCH_ASSOC);
$invoices  = array_filter($docs, fn($d) => $d['type'] === 'invoice');
$contracts = array_filter($docs, fn($d) => $d['type'] === 'contract');

// Subscription (only if visible)
$sub_stmt = $pdo->prepare('SELECT * FROM subscriptions WHERE client_id = ? AND visible = 1');
$sub_stmt->execute([$client_id]);
$sub = $sub_stmt->fetch(PDO::FETCH_ASSOC);

$addons = [];
$sub_total = 0;
if ($sub) {
    $a = $pdo->prepare('SELECT * FROM subscription_addons WHERE subscription_id = ? AND active = 1 ORDER BY id');
    $a->execute([$sub['id']]);
    $addons = $a->fetchAll(PDO::FETCH_ASSOC);

    $sub_total = (float)$sub['base_price'];
    foreach ($addons as $addon) {
        $effective = ($addon['promo_price'] !== null && $addon['promo_cycles_remaining'] > 0)
            ? (float)$addon['promo_price']
            : (float)$addon['price'];
        $sub_total += $effective;
    }
}

$req_msg = $_GET['req_msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Portal — Sunny Monkeys</title>
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
  .section-title { font-size: 0.73rem; font-weight: 600; letter-spacing: .1em; text-transform: uppercase; color: #666; margin-bottom: 12px; margin-top: 36px; }
  .section-title:first-of-type { margin-top: 0; }

  /* Doc items */
  .doc-list { display: flex; flex-direction: column; gap: 8px; }
  .doc-item { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 10px; padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; }
  .doc-info { display: flex; align-items: center; gap: 14px; }
  .doc-icon { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
  .doc-icon.invoice { background: #1a2a1a; }
  .doc-icon.contract { background: #1a1a2a; }
  .doc-title { font-size: 0.95rem; font-weight: 500; }
  .doc-date { font-size: 0.8rem; color: #666; margin-top: 2px; }
  .download-btn { background: #222; border: 1px solid #333; color: #fff; padding: 8px 16px; border-radius: 6px; font-size: 0.82rem; text-decoration: none; }
  .download-btn:hover { background: #2a2a2a; }
  .empty { color: #555; font-size: 0.9rem; padding: 20px 0; }

  /* My Plan */
  .plan-card { background: #111; border: 1px solid #1e1e1e; border-radius: 14px; overflow: hidden; margin-bottom: 24px; }
  .plan-header { padding: 20px 24px 16px; border-bottom: 1px solid #1a1a1a; display: flex; align-items: flex-start; justify-content: space-between; }
  .plan-name { font-size: 1.05rem; font-weight: 600; }
  .plan-cycle { font-size: 0.8rem; color: #888; margin-top: 3px; }
  .plan-price { text-align: right; }
  .plan-price .amount { font-size: 1.3rem; font-weight: 700; }
  .plan-price .per { font-size: 0.8rem; color: #666; }
  .plan-row { display: flex; align-items: center; justify-content: space-between; padding: 13px 24px; border-top: 1px solid #161616; }
  .plan-row:first-child { border-top: none; }
  .plan-row-name { font-size: 0.92rem; display: flex; align-items: center; gap: 8px; }
  .plan-row-price { font-size: 0.92rem; font-weight: 500; }
  .promo-tag { background: #2a1a0a; border: 1px solid #5a3a0a; color: #f0a040; font-size: 0.7rem; padding: 2px 7px; border-radius: 4px; }
  .free-tag { background: #1a2a1a; border: 1px solid #2a5a2a; color: #6fcf6f; font-size: 0.7rem; padding: 2px 7px; border-radius: 4px; }
  .plan-total-row { display: flex; align-items: center; justify-content: space-between; padding: 16px 24px; background: #141414; border-top: 1px solid #222; }
  .plan-total-label { font-size: 0.8rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: #888; }
  .plan-total-amount { font-size: 1.1rem; font-weight: 700; }
  .promo-note { font-size: 0.78rem; color: #666; padding: 0 24px 14px; }

  /* Request form */
  .request-form-card { background: #111; border: 1px solid #1e1e1e; border-radius: 14px; padding: 24px; }
  .request-form-card h3 { font-size: 0.95rem; font-weight: 600; margin-bottom: 16px; color: #ccc; }
  label { display: block; font-size: 0.73rem; font-weight: 600; letter-spacing: .08em; color: #777; margin-bottom: 5px; text-transform: uppercase; }
  select, textarea { width: 100%; background: #0d0d0d; border: 1px solid #2e2e2e; border-radius: 8px; padding: 10px 12px; color: #fff; font-size: 0.9rem; outline: none; font-family: inherit; }
  select:focus, textarea:focus { border-color: #444; }
  select option { background: #0d0d0d; }
  textarea { resize: vertical; min-height: 100px; }
  .form-group { margin-bottom: 16px; }
  .btn { background: #fff; color: #000; border: none; border-radius: 8px; padding: 11px 24px; font-size: 0.9rem; font-weight: 600; cursor: pointer; }
  .btn:hover { background: #e0e0e0; }

  /* Toast */
  .toast { position: fixed; top: 20px; right: 20px; background: #1a3a1a; border: 1px solid #2a6a2a; color: #6fcf6f; padding: 12px 20px; border-radius: 10px; font-size: 0.88rem; font-weight: 500; box-shadow: 0 4px 20px rgba(0,0,0,.4); z-index: 999; animation: slideIn .3s ease; }
  @keyframes slideIn { from { opacity:0; transform:translateX(20px); } to { opacity:1; transform:translateX(0); } }
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
  <h2>Welcome back<?= $_SESSION['user_name'] ? ', '.htmlspecialchars(explode(' ',$_SESSION['user_name'])[0]) : '' ?>.</h2>
  <p class="subtitle">Your invoices, contracts, and plan details from Sunny Monkeys LLC.</p>

  <?php if ($sub): ?>
  <!-- MY PLAN ──────────────────────────────────────────────────── -->
  <div class="section-title">My Plan</div>

  <div class="plan-card">
    <div class="plan-header">
      <div>
        <div class="plan-name"><?= htmlspecialchars($sub['base_name']) ?></div>
        <div class="plan-cycle">Billed <?= htmlspecialchars($sub['billing_cycle']) ?><?php if ($sub['contract_start']): ?> · Started <?= date('M j, Y', strtotime($sub['contract_start'])) ?><?php endif; ?></div>
      </div>
      <div class="plan-price">
        <div class="amount">$<?= number_format($sub['base_price'], 2) ?></div>
        <div class="per">per <?= htmlspecialchars($sub['billing_cycle']) ?> period</div>
      </div>
    </div>

    <?php foreach ($addons as $addon):
      $is_promo  = ($addon['promo_price'] !== null && $addon['promo_cycles_remaining'] > 0);
      $is_free   = $is_promo && (float)$addon['promo_price'] === 0.0;
      $effective = $is_promo ? (float)$addon['promo_price'] : (float)$addon['price'];
    ?>
    <div class="plan-row">
      <div class="plan-row-name">
        <?= htmlspecialchars($addon['name']) ?>
        <?php if ($is_free): ?>
          <span class="free-tag">FREE</span>
        <?php elseif ($is_promo): ?>
          <span class="promo-tag">PROMO</span>
        <?php endif; ?>
      </div>
      <div class="plan-row-price">
        <?php if ($is_free): ?>
          <span style="color:#6fcf6f">$0.00</span>
        <?php elseif ($is_promo): ?>
          <span style="color:#f0a040">$<?= number_format($effective,2) ?></span>
          <span style="color:#555;font-size:0.78rem;text-decoration:line-through;margin-left:6px">$<?= number_format($addon['price'],2) ?></span>
        <?php else: ?>
          $<?= number_format($effective,2) ?>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <div class="plan-total-row">
      <span class="plan-total-label">Total this billing period</span>
      <span class="plan-total-amount">$<?= number_format($sub_total, 2) ?></span>
    </div>

    <?php
    $promo_addons = array_filter($addons, fn($a) => $a['promo_price'] !== null && $a['promo_cycles_remaining'] > 0);
    if (!empty($promo_addons)): ?>
    <div class="promo-note">
      Promotional pricing active on:
      <?php $notes_arr = [];
      foreach ($promo_addons as $a) {
          $label = htmlspecialchars($a['name']);
          if ((float)$a['promo_price'] === 0.0) {
              $notes_arr[] = "$label (free for {$a['promo_cycles_remaining']} more cycle".($a['promo_cycles_remaining']===1?'':'s').")";
          } else {
              $notes_arr[] = "$label ({$a['promo_cycles_remaining']} cycle".($a['promo_cycles_remaining']===1?'':'s')." remaining)";
          }
      }
      echo implode(', ', $notes_arr); ?>.
    </div>
    <?php endif; ?>
  </div>

  <!-- REQUEST A CHANGE ─────────────────────────────────────────── -->
  <div class="request-form-card">
    <h3>Request a Change or Upgrade</h3>
    <form action="subscription-request.php" method="POST">
      <div class="form-group">
        <label>Request Type</label>
        <select name="type" required>
          <option value="">— Select —</option>
          <option value="addon">Add a service</option>
          <option value="removal">Remove a service</option>
          <option value="custom">Custom request / question</option>
        </select>
      </div>
      <div class="form-group">
        <label>Message</label>
        <textarea name="message" placeholder="Describe what you'd like to add, remove, or ask about..." required></textarea>
      </div>
      <button type="submit" class="btn">Send Request</button>
    </form>
  </div>

  <?php endif; ?>

  <!-- DOCUMENTS ────────────────────────────────────────────────── -->
  <div class="section-title" style="margin-top:<?= $sub ? '36px' : '0' ?>">Invoices</div>
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

<?php if ($req_msg): ?>
<div class="toast" id="toast">✓ <?= htmlspecialchars($req_msg) ?></div>
<script>setTimeout(()=>{ const t=document.getElementById('toast'); if(t){t.style.transition='opacity .4s';t.style.opacity='0';} },4000);</script>
<?php endif; ?>
</body>
</html>
