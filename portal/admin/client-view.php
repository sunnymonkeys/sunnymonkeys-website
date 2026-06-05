<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: https://sunnymonkeys.com/portal/login.php'); exit; }
require_once __DIR__ . '/../config/db.php';
$pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8', DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }
$stmt = $pdo->prepare('SELECT * FROM clients WHERE id = ?');
$stmt->execute([$id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$client) { header('Location: index.php'); exit; }

// Standard packages
$PACKAGES = [
  'email'    => ['name'=>'Email Monthly Tier',         'price'=>225.00, 'default_promo_price'=>0.00,   'default_promo_cycles'=>6,
                 'description'=>"Monthly email campaign design & copywriting\nSubscriber list management\nPerformance reporting"],
  'linkedin' => ['name'=>'LinkedIn Personal Branding', 'price'=>350.00, 'default_promo_price'=>175.00, 'default_promo_cycles'=>6,
                 'description'=>"LinkedIn content creation & scheduling\nPersonal brand strategy & profile optimization\nThought leadership post creation"],
  'video'    => ['name'=>'Short-Form Video (4 videos)', 'price'=>500.00, 'default_promo_price'=>null,   'default_promo_cycles'=>0,
                 'description'=>"4 short-form videos per month\nScripting, filming direction & editing\nSocial-ready formats for Reels, TikTok & Shorts"],
];

// ── POST handler ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $redirect_tab = $_POST['tab'] ?? 'docs';
    $msg = '';

    if ($action === 'save_subscription') {
        $visible         = isset($_POST['visible']) ? 1 : 0;
        $base_name       = trim($_POST['base_name']        ?? 'Base Retainer');
        $base_description = trim($_POST['base_description'] ?? '');
        $base_price      = (float)($_POST['base_price']    ?? 0);
        $billing_cycle   = trim($_POST['billing_cycle']    ?? 'biweekly');
        $contract_start  = !empty($_POST['contract_start']) ? $_POST['contract_start'] : null;
        $notes           = trim($_POST['notes'] ?? '');
        $existing = $pdo->prepare('SELECT id FROM subscriptions WHERE client_id = ?');
        $existing->execute([$id]);
        $sub = $existing->fetch();
        if ($sub) {
            $pdo->prepare('UPDATE subscriptions SET visible=?,base_name=?,base_description=?,base_price=?,billing_cycle=?,contract_start=?,notes=? WHERE client_id=?')
               ->execute([$visible,$base_name,$base_description,$base_price,$billing_cycle,$contract_start,$notes,$id]);
        } else {
            $pdo->prepare('INSERT INTO subscriptions (client_id,visible,base_name,base_description,base_price,billing_cycle,contract_start,notes) VALUES (?,?,?,?,?,?,?,?)')
               ->execute([$id,$visible,$base_name,$base_description,$base_price,$billing_cycle,$contract_start,$notes]);
        }
        $msg = 'Subscription saved.'; $redirect_tab = 'subscription';
    }

    elseif ($action === 'add_packages') {
        $sub_id   = (int)($_POST['subscription_id'] ?? 0);
        $selected = $_POST['packages'] ?? [];
        $added    = 0;
        foreach ($selected as $key) {
            if (!isset($PACKAGES[$key])) continue;
            $pkg          = $PACKAGES[$key];
            $use_promo    = !empty($_POST["promo_$key"]);
            $promo_price  = null;
            $promo_cycles = 0;
            if ($use_promo) {
                $promo_price  = $_POST["promo_price_$key"] !== '' ? (float)$_POST["promo_price_$key"] : null;
                $promo_cycles = (int)($_POST["promo_cycles_$key"] ?? 0);
            }
            $pdo->prepare('INSERT INTO subscription_addons (subscription_id,name,description,price,promo_price,promo_cycles_remaining) VALUES (?,?,?,?,?,?)')
               ->execute([$sub_id,$pkg['name'],$pkg['description'],$pkg['price'],$promo_price,$promo_cycles]);
            $added++;
        }
        $msg = $added ? "$added package".($added>1?'s':'')." added." : 'No packages selected.';
        $redirect_tab = 'subscription';
    }

    elseif ($action === 'add_addon') {
        $sub_id       = (int)($_POST['subscription_id']    ?? 0);
        $name         = trim($_POST['addon_name']          ?? '');
        $description  = trim($_POST['addon_description']   ?? '');
        $price        = (float)($_POST['addon_price']      ?? 0);
        $promo_raw    = $_POST['addon_promo_price']        ?? '';
        $promo_price  = ($promo_raw !== '') ? (float)$promo_raw : null;
        $promo_cycles = (int)($_POST['addon_promo_cycles'] ?? 0);
        if ($name && $sub_id) {
            $pdo->prepare('INSERT INTO subscription_addons (subscription_id,name,description,price,promo_price,promo_cycles_remaining) VALUES (?,?,?,?,?,?)')
               ->execute([$sub_id,$name,$description,$price,$promo_price,$promo_cycles]);
            $msg = "Add-on \"$name\" added.";
        }
        $redirect_tab = 'subscription';
    }

    elseif ($action === 'update_addon') {
        $addon_id     = (int)($_POST['addon_id']           ?? 0);
        $name         = trim($_POST['addon_name']          ?? '');
        $description  = trim($_POST['addon_description']   ?? '');
        $price        = (float)($_POST['addon_price']      ?? 0);
        $promo_raw    = $_POST['addon_promo_price']        ?? '';
        $promo_price  = ($promo_raw !== '') ? (float)$promo_raw : null;
        $promo_cycles = (int)($_POST['addon_promo_cycles'] ?? 0);
        $active       = (int)($_POST['active']             ?? 1);
        $pdo->prepare('UPDATE subscription_addons SET name=?,description=?,price=?,promo_price=?,promo_cycles_remaining=?,active=? WHERE id=?')
           ->execute([$name,$description,$price,$promo_price,$promo_cycles,$active,$addon_id]);
        $msg = 'Add-on updated.'; $redirect_tab = 'subscription';
    }

    elseif ($action === 'delete_addon') {
        $pdo->prepare('DELETE FROM subscription_addons WHERE id=?')->execute([(int)($_POST['addon_id']??0)]);
        $msg = 'Add-on removed.'; $redirect_tab = 'subscription';
    }

    elseif ($action === 'respond_request') {
        $req_id = (int)($_POST['request_id'] ?? 0);
        $status = in_array($_POST['status']??'',['approved','declined','pending']) ? $_POST['status'] : 'pending';
        $note   = trim($_POST['admin_note'] ?? '');
        $pdo->prepare('UPDATE subscription_requests SET status=?,admin_note=? WHERE id=?')->execute([$status,$note,$req_id]);
        $msg = 'Request updated.'; $redirect_tab = 'requests';
    }

    header('Location: client-view.php?id='.$id.'&tab='.urlencode($redirect_tab).'&msg='.urlencode($msg)); exit;
}

// ── Fetch ─────────────────────────────────────────────────────────────────────
$docs = $pdo->prepare('SELECT * FROM documents WHERE client_id = ? ORDER BY uploaded_at DESC');
$docs->execute([$id]); $docs = $docs->fetchAll(PDO::FETCH_ASSOC);

$sub_stmt = $pdo->prepare('SELECT * FROM subscriptions WHERE client_id = ?');
$sub_stmt->execute([$id]); $sub = $sub_stmt->fetch(PDO::FETCH_ASSOC);

$addons = [];
if ($sub) {
    $a = $pdo->prepare('SELECT * FROM subscription_addons WHERE subscription_id = ? ORDER BY id');
    $a->execute([$sub['id']]); $addons = $a->fetchAll(PDO::FETCH_ASSOC);
}

$req_stmt = $pdo->prepare('SELECT * FROM subscription_requests WHERE client_id = ? ORDER BY created_at DESC');
$req_stmt->execute([$id]); $all_requests = $req_stmt->fetchAll(PDO::FETCH_ASSOC);
$pending_count = count(array_filter($all_requests, fn($r) => $r['status']==='pending'));

$active_tab = $_GET['tab'] ?? 'docs';
$msg        = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sunny Monkeys</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: #0d0d0d; color: #fff; font-family: 'Segoe UI', sans-serif; min-height: 100vh; }
  header { background: #111; border-bottom: 1px solid #222; padding: 16px 32px; display: flex; align-items: center; justify-content: space-between; }
  header .brand { display: flex; align-items: center; gap: 12px; }
  header img { height: 32px; width: auto; display: block; }
  header h1 { font-size: 1rem; font-weight: 600; }
  header nav a { color: #aaa; font-size: 0.88rem; text-decoration: none; margin-left: 20px; }
  header nav a:hover { color: #fff; }
  .container { max-width: 960px; margin: 0 auto; padding: 40px 24px; }
  .client-header { margin-bottom: 28px; }
  .client-header h2 { font-size: 1.6rem; font-weight: 700; }
  .client-meta { display: flex; gap: 20px; margin-top: 6px; color: #666; font-size: 0.88rem; flex-wrap: wrap; }
  .tabs { display: flex; gap: 2px; border-bottom: 1px solid #222; margin-bottom: 32px; }
  .tab { padding: 10px 18px; font-size: 0.88rem; font-weight: 500; color: #666; text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -1px; transition: color .15s; }
  .tab:hover { color: #aaa; }
  .tab.active { color: #fff; border-bottom-color: #fff; }
  .badge-pill { background: #8B1A2D; color: #fff; font-size: 0.7rem; padding: 2px 7px; border-radius: 10px; margin-left: 6px; vertical-align: middle; }
  .toast { position: fixed; top: 20px; right: 20px; background: #1a3a1a; border: 1px solid #2a6a2a; color: #6fcf6f; padding: 12px 20px; border-radius: 10px; font-size: 0.88rem; font-weight: 500; box-shadow: 0 4px 20px rgba(0,0,0,.4); z-index: 999; animation: slideIn .3s ease; }
  @keyframes slideIn { from{opacity:0;transform:translateX(20px);}to{opacity:1;transform:translateX(0);} }
  label { display: block; font-size: 0.73rem; font-weight: 600; letter-spacing: .08em; color: #777; margin-bottom: 5px; text-transform: uppercase; }
  input[type=text],input[type=email],input[type=number],input[type=date],select,textarea { width:100%;background:#111;border:1px solid #2e2e2e;border-radius:8px;padding:10px 12px;color:#fff;font-size:0.9rem;outline:none;font-family:inherit; }
  input:focus,select:focus,textarea:focus { border-color:#444; }
  select option { background:#111; }
  textarea { resize:vertical;min-height:80px; }
  .form-row { display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px; }
  .form-group { margin-bottom:16px; }
  .btn { background:#fff;color:#000;border:none;border-radius:8px;padding:10px 20px;font-size:0.88rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block; }
  .btn:hover { background:#e0e0e0; }
  .btn-sm { background:#1e1e1e;border:1px solid #333;color:#ddd;padding:6px 12px;border-radius:6px;font-size:0.8rem;cursor:pointer;text-decoration:none;display:inline-block; }
  .btn-sm:hover { background:#2a2a2a; }
  .btn-danger { background:#2a0a0a;border:1px solid #5a1a1a;color:#ff6b6b; }
  .btn-danger:hover { background:#3a1010; }
  .btn-approve { background:#1a3a1a;color:#6fcf6f;border:1px solid #2a6a2a;padding:10px 20px;border-radius:8px;font-size:0.88rem;font-weight:600;cursor:pointer;white-space:nowrap; }
  .btn-approve:hover { background:#1f461f; }
  .btn-decline { background:#2a0a0a;color:#ff6b6b;border:1px solid #5a1a1a;padding:10px 20px;border-radius:8px;font-size:0.88rem;font-weight:600;cursor:pointer;white-space:nowrap; }
  .btn-decline:hover { background:#3a1010; }
  .toggle-row { display:flex;align-items:center;gap:14px;margin-bottom:24px;padding:14px 16px;background:#131313;border:1px solid #222;border-radius:10px; }
  .toggle-label strong { display:block;font-size:0.9rem;font-weight:500;color:#ccc; }
  .toggle-label span { font-size:0.8rem;color:#555; }
  .switch { position:relative;display:inline-block;width:44px;height:26px;flex-shrink:0; }
  .switch input { opacity:0;width:0;height:0; }
  .slider { position:absolute;cursor:pointer;inset:0;background:#2e2e2e;border-radius:26px;transition:.25s; }
  .slider:before { position:absolute;content:"";height:20px;width:20px;left:3px;bottom:3px;background:#666;border-radius:50%;transition:.25s; }
  input:checked + .slider { background:#1e4a1e; }
  input:checked + .slider:before { transform:translateX(18px);background:#6fcf6f; }
  .section-card { background:#111;border:1px solid #1e1e1e;border-radius:12px;padding:24px;margin-bottom:20px; }
  .section-card h3 { font-size:0.95rem;font-weight:600;margin-bottom:18px;color:#ccc; }
  .divider { border:none;border-top:1px solid #1e1e1e;margin:20px 0; }

  /* Quick-add packages */
  .pkg-grid { display:flex;flex-direction:column;gap:12px;margin-bottom:20px; }
  .pkg-item { background:#0d0d0d;border:1px solid #222;border-radius:10px;padding:14px 16px; }
  .pkg-item-top { display:flex;align-items:center;gap:12px; }
  .pkg-item-top label { font-size:0.92rem;font-weight:500;color:#ccc;text-transform:none;letter-spacing:0;cursor:pointer;margin:0; }
  .pkg-price { font-size:0.8rem;color:#555;margin-left:auto; }
  .pkg-promo { margin-top:12px;padding-top:12px;border-top:1px solid #1e1e1e;display:none; }
  .pkg-promo.visible { display:block; }
  .pkg-promo-inner { display:grid;grid-template-columns:auto 1fr 1fr;gap:10px;align-items:center; }
  .pkg-promo-inner label { font-size:0.72rem;color:#666;text-transform:uppercase;letter-spacing:.08em;margin:0; }
  .promo-badge { background:#2a1a0a;border:1px solid #6a3a1a;color:#f0a040;font-size:0.72rem;padding:2px 7px;border-radius:4px;white-space:nowrap; }
  input[type=checkbox] { width:16px;height:16px;cursor:pointer;flex-shrink:0;accent-color:#6fcf6f; }

  /* Add-ons table */
  .addon-table { width:100%;border-collapse:collapse; }
  .addon-table th { text-align:left;font-size:0.7rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#555;padding:0 12px 10px; }
  .addon-table td { padding:11px 12px;border-top:1px solid #1a1a1a;font-size:0.88rem;vertical-align:middle; }
  .addon-table tr:hover td { background:#141414; }
  .doc-item { display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-top:1px solid #1a1a1a; }
  .doc-item:first-child { border-top:none; }
  .empty { color:#555;font-size:0.88rem;padding:12px 0; }
  .request-card { background:#111;border:1px solid #1e1e1e;border-radius:10px;padding:18px 20px;margin-bottom:12px; }
  .req-type { font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:#666;margin-bottom:6px; }
  .req-msg { font-size:0.92rem;color:#ccc;margin-bottom:8px;line-height:1.5; }
  .req-date { font-size:0.78rem;color:#444; }
  .status-badge { font-size:0.75rem;padding:3px 9px;border-radius:5px;font-weight:500; }
  .status-pending  { background:#2a1a0a;border:1px solid #6a3a0a;color:#f0a040; }
  .status-approved { background:#1a2a1a;border:1px solid #2a5a2a;color:#6fcf6f; }
  .status-declined { background:#2a0a0a;border:1px solid #5a1a1a;color:#ff6b6b; }
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
    <a href="upload.php?client_id=<?= $id ?>">Upload Doc</a>
    <a href="../logout.php">Sign out</a>
  </nav>
</header>

<div class="container">
  <div class="client-header">
    <h2><?= htmlspecialchars($client['name']) ?></h2>
    <div class="client-meta">
      <span><?= htmlspecialchars($client['company'] ?? '—') ?></span>
      <span><?= htmlspecialchars($client['email']) ?></span>
      <span>Client since <?= date('M j, Y', strtotime($client['created_at'])) ?></span>
    </div>
  </div>

  <div class="tabs">
    <a href="?id=<?= $id ?>&tab=docs"         class="tab <?= $active_tab==='docs'         ?'active':'' ?>">Documents</a>
    <a href="?id=<?= $id ?>&tab=subscription" class="tab <?= $active_tab==='subscription' ?'active':'' ?>">Subscription</a>
    <a href="?id=<?= $id ?>&tab=requests"     class="tab <?= $active_tab==='requests'     ?'active':'' ?>">
      Requests<?php if ($pending_count): ?><span class="badge-pill"><?= $pending_count ?></span><?php endif; ?>
    </a>
  </div>

  <?php if ($active_tab === 'docs'): ?>
  <div class="section-card">
    <h3>Invoices</h3>
    <?php $inv=array_values(array_filter($docs,fn($d)=>$d['type']==='invoice')); ?>
    <?php if(empty($inv)): ?><p class="empty">No invoices yet.</p><?php else: foreach($inv as $doc): ?>
      <div class="doc-item">
        <div><div style="font-weight:500"><?= htmlspecialchars($doc['title']) ?></div>
             <div style="font-size:0.78rem;color:#555;margin-top:2px"><?= date('M j, Y',strtotime($doc['uploaded_at'])) ?></div></div>
        <a href="../download.php?id=<?= $doc['id'] ?>" class="btn-sm">Download</a>
      </div>
    <?php endforeach;endif; ?>
  </div>
  <div class="section-card">
    <h3>Contracts</h3>
    <?php $con=array_values(array_filter($docs,fn($d)=>$d['type']==='contract')); ?>
    <?php if(empty($con)): ?><p class="empty">No contracts yet.</p><?php else: foreach($con as $doc): ?>
      <div class="doc-item">
        <div><div style="font-weight:500"><?= htmlspecialchars($doc['title']) ?></div>
             <div style="font-size:0.78rem;color:#555;margin-top:2px"><?= date('M j, Y',strtotime($doc['uploaded_at'])) ?></div></div>
        <a href="../download.php?id=<?= $doc['id'] ?>" class="btn-sm">Download</a>
      </div>
    <?php endforeach;endif; ?>
  </div>

  <?php elseif ($active_tab === 'subscription'): ?>

  <!-- BASE PLAN -->
  <div class="section-card">
    <h3>Base Plan</h3>
    <form method="POST">
      <input type="hidden" name="action" value="save_subscription">
      <input type="hidden" name="tab" value="subscription">
      <div class="toggle-row">
        <label class="switch">
          <input type="checkbox" name="visible" value="1" <?= ($sub&&$sub['visible'])?'checked':'' ?>>
          <span class="slider"></span>
        </label>
        <div class="toggle-label">
          <strong>Show subscription to client</strong>
          <span>When off, "My Plan" is hidden from their dashboard</span>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Plan Name</label>
          <input type="text" name="base_name" value="<?= htmlspecialchars($sub['base_name']??'Base Retainer') ?>"></div>
        <div class="form-group"><label>Base Price ($)</label>
          <input type="number" name="base_price" step="0.01" value="<?= htmlspecialchars($sub['base_price']??'1725.00') ?>"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Billing Cycle</label>
          <select name="billing_cycle">
            <option value="biweekly" <?= ($sub['billing_cycle']??'biweekly')==='biweekly'?'selected':'' ?>>Biweekly</option>
            <option value="monthly"  <?= ($sub['billing_cycle']??'')==='monthly' ?'selected':'' ?>>Monthly</option>
            <option value="weekly"   <?= ($sub['billing_cycle']??'')==='weekly'  ?'selected':'' ?>>Weekly</option>
          </select></div>
        <div class="form-group"><label>Contract Start Date</label>
          <input type="date" name="contract_start" value="<?= htmlspecialchars($sub['contract_start']??'') ?>"></div>
      </div>
      <div class="form-group">
        <label>Client-facing description <span style="font-size:0.7rem;color:#555;text-transform:none;letter-spacing:0">(one bullet per line — shown on client dashboard)</span></label>
        <textarea name="base_description" rows="6" placeholder="12–14 content pieces/month across Instagram, LinkedIn & Facebook&#10;Full monthly content calendar&#10;Branding updates, printables, flyers & presentations&#10;Weekly or biweekly check-in calls&#10;Same-day response during business hours (9am–5pm CT, Mon–Fri)"><?= htmlspecialchars($sub['base_description']??'') ?></textarea>
      </div>
      <div class="form-group">
        <label>Internal Notes <span style="font-size:0.7rem;color:#555;text-transform:none;letter-spacing:0">(not visible to client)</span></label>
        <textarea name="notes"><?= htmlspecialchars($sub['notes']??'') ?></textarea>
      </div>
      <button type="submit" class="btn">Save Plan</button>
    </form>
  </div>

  <?php if ($sub): ?>

  <!-- QUICK ADD STANDARD PACKAGES -->
  <div class="section-card">
    <h3>Quick Add — Standard Packages</h3>
    <form method="POST">
      <input type="hidden" name="action" value="add_packages">
      <input type="hidden" name="subscription_id" value="<?= $sub['id'] ?>">
      <input type="hidden" name="tab" value="subscription">
      <div class="pkg-grid">
        <?php foreach ($PACKAGES as $key => $pkg): ?>
        <div class="pkg-item">
          <div class="pkg-item-top">
            <input type="checkbox" id="pkg-<?= $key ?>" name="packages[]" value="<?= $key ?>" onchange="togglePromo('<?= $key ?>', this.checked)">
            <label for="pkg-<?= $key ?>"><?= htmlspecialchars($pkg['name']) ?></label>
            <span class="pkg-price">$<?= number_format($pkg['price'],2) ?>/<?= $sub['billing_cycle']??'biweekly' ?></span>
          </div>
          <div class="pkg-promo" id="promo-<?= $key ?>">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
              <input type="checkbox" id="use-promo-<?= $key ?>" name="promo_<?= $key ?>" value="1" onchange="togglePromoFields('<?= $key ?>', this.checked)"
                <?= $pkg['default_promo_price']!==null?'checked':'' ?>>
              <label for="use-promo-<?= $key ?>" style="font-size:0.82rem;color:#aaa;text-transform:none;letter-spacing:0;cursor:pointer;margin:0">Activate promotional pricing</label>
            </div>
            <div id="promo-fields-<?= $key ?>" style="display:<?= $pkg['default_promo_price']!==null?'grid':'none' ?>;grid-template-columns:1fr 1fr;gap:10px">
              <div><label>Promo Price ($)</label>
                <input type="number" name="promo_price_<?= $key ?>" step="0.01"
                  value="<?= $pkg['default_promo_price']!==null ? $pkg['default_promo_price'] : '' ?>"
                  placeholder="0 = FREE, blank = none"></div>
              <div><label>Promo Cycles</label>
                <input type="number" name="promo_cycles_<?= $key ?>" value="<?= $pkg['default_promo_cycles'] ?>" placeholder="0"></div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="submit" class="btn">Add Selected Packages</button>
    </form>
  </div>

  <!-- CURRENT ADD-ONS -->
  <div class="section-card">
    <h3>Active Add-ons</h3>
    <?php if (!empty($addons)): ?>
    <table class="addon-table">
      <thead><tr><th>Service</th><th>Regular</th><th>Promo</th><th>Cycles Left</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($addons as $addon): ?>
      <tr>
        <td><?= htmlspecialchars($addon['name']) ?></td>
        <td>$<?= number_format($addon['price'],2) ?></td>
        <td><?php if($addon['promo_price']!==null&&$addon['promo_cycles_remaining']>0):?>
              <span class="promo-badge"><?= $addon['promo_price']==0?'FREE':'$'.number_format($addon['promo_price'],2) ?></span>
            <?php else:?><span style="color:#444">—</span><?php endif;?></td>
        <td><?= $addon['promo_cycles_remaining']>0?$addon['promo_cycles_remaining']:'<span style="color:#444">—</span>' ?></td>
        <td><span style="font-size:0.8rem;color:<?= $addon['active']?'#6fcf6f':'#555' ?>"><?= $addon['active']?'Active':'Inactive' ?></span></td>
        <td style="white-space:nowrap">
          <button class="btn-sm" onclick="toggleEdit(<?= $addon['id'] ?>)">Edit</button>
          <form method="POST" style="display:inline" onsubmit="return confirm('Remove?')">
            <input type="hidden" name="action" value="delete_addon">
            <input type="hidden" name="addon_id" value="<?= $addon['id'] ?>">
            <input type="hidden" name="tab" value="subscription">
            <button type="submit" class="btn-sm btn-danger" style="margin-left:6px">Remove</button>
          </form>
        </td>
      </tr>
      <tr id="edit-<?= $addon['id'] ?>" style="display:none">
        <td colspan="6" style="padding:14px 12px;background:#0a0a0a">
          <form method="POST">
            <input type="hidden" name="action" value="update_addon">
            <input type="hidden" name="addon_id" value="<?= $addon['id'] ?>">
            <input type="hidden" name="tab" value="subscription">
            <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr auto;gap:10px;align-items:end;margin-bottom:10px">
              <div><label>Name</label><input type="text" name="addon_name" value="<?= htmlspecialchars($addon['name']) ?>"></div>
              <div><label>Regular ($)</label><input type="number" name="addon_price" step="0.01" value="<?= $addon['price'] ?>"></div>
              <div><label>Promo ($)</label><input type="number" name="addon_promo_price" step="0.01" value="<?= $addon['promo_price']??'' ?>" placeholder="blank=none"></div>
              <div><label>Promo Cycles</label><input type="number" name="addon_promo_cycles" value="<?= $addon['promo_cycles_remaining'] ?>"></div>
              <div><label>Status</label><select name="active"><option value="1" <?= $addon['active']?'selected':'' ?>>Active</option><option value="0" <?= !$addon['active']?'selected':'' ?>>Inactive</option></select></div>
              <button type="submit" class="btn" style="white-space:nowrap">Save</button>
            </div>
            <div><label>Client-facing description <span style="font-size:0.7rem;color:#555;text-transform:none;letter-spacing:0">(one bullet per line)</span></label>
              <textarea name="addon_description" rows="3"><?= htmlspecialchars($addon['description']??'') ?></textarea></div>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <hr class="divider">
    <?php else: ?><p class="empty" style="margin-bottom:16px">No add-ons yet.</p><?php endif; ?>

    <h3 style="font-size:0.88rem;margin-bottom:14px">Add Custom Add-on</h3>
    <form method="POST">
      <input type="hidden" name="action" value="add_addon">
      <input type="hidden" name="subscription_id" value="<?= $sub['id'] ?>">
      <input type="hidden" name="tab" value="subscription">
      <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:12px;align-items:end;margin-bottom:12px">
        <div><label>Service Name</label><input type="text" name="addon_name" placeholder="Custom service" required></div>
        <div><label>Regular ($)</label><input type="number" name="addon_price" step="0.01" placeholder="0.00" required></div>
        <div><label>Promo ($)</label><input type="number" name="addon_promo_price" step="0.01" placeholder="0=FREE, blank=none"></div>
        <div><label>Promo Cycles</label><input type="number" name="addon_promo_cycles" value="0"></div>
        <button type="submit" class="btn">Add</button>
      </div>
      <div><label>Description <span style="font-size:0.7rem;color:#555;text-transform:none;letter-spacing:0">(one bullet per line)</span></label>
        <textarea name="addon_description" rows="3" placeholder="What's included..."></textarea></div>
    </form>
  </div>
  <?php else: ?>
  <p style="color:#555;font-size:0.88rem;padding:8px 0">Save the base plan first to unlock add-on management.</p>
  <?php endif; ?>

  <?php elseif ($active_tab === 'requests'): ?>
  <?php if(empty($all_requests)): ?><p class="empty">No requests yet.</p>
  <?php else: foreach($all_requests as $req): ?>
    <div class="request-card">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px">
        <div>
          <div class="req-type"><?= ucfirst($req['type']) ?> request</div>
          <div class="req-msg"><?= nl2br(htmlspecialchars($req['message'])) ?></div>
          <div class="req-date"><?= date('M j, Y \a\t g:ia',strtotime($req['created_at'])) ?></div>
        </div>
        <span class="status-badge status-<?= $req['status'] ?>"><?= ucfirst($req['status']) ?></span>
      </div>
      <?php if(!empty($req['admin_note'])): ?>
        <div style="font-size:0.82rem;color:#666;padding-top:10px;border-top:1px solid #1e1e1e;margin-top:8px">
          <strong style="color:#555">Note:</strong> <?= htmlspecialchars($req['admin_note']) ?></div>
      <?php endif; ?>
      <?php if($req['status']==='pending'): ?>
      <form method="POST" style="display:flex;gap:10px;margin-top:14px;align-items:flex-end;flex-wrap:wrap">
        <input type="hidden" name="action" value="respond_request">
        <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
        <input type="hidden" name="tab" value="requests">
        <div style="flex:1;min-width:200px"><label>Note (optional)</label><input type="text" name="admin_note" placeholder="Internal note..."></div>
        <button type="submit" name="status" value="approved" class="btn-approve">Approve</button>
        <button type="submit" name="status" value="declined" class="btn-decline">Decline</button>
      </form>
      <?php endif; ?>
    </div>
  <?php endforeach;endif; ?>
  <?php endif; ?>
</div>

<?php if($msg): ?>
<div class="toast" id="toast">✓ <?= htmlspecialchars($msg) ?></div>
<script>setTimeout(()=>{ const t=document.getElementById('toast'); if(t){t.style.transition='opacity .4s';t.style.opacity='0';} },3500);</script>
<?php endif; ?>
<script>
function toggleEdit(id) {
  const row = document.getElementById('edit-' + id);
  if (row) row.style.display = (row.style.display === 'table-row') ? 'none' : 'table-row';
}
function togglePromo(key, show) {
  document.getElementById('promo-' + key).classList.toggle('visible', show);
}
function togglePromoFields(key, show) {
  const el = document.getElementById('promo-fields-' + key);
  if (el) el.style.display = show ? 'grid' : 'none';
}
</script>
</body>
</html>
