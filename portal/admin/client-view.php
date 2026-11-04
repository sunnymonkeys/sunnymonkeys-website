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
<title>Client | Sunny Monkeys admin</title>
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
    <a href="index.php">Clients</a>
    <a href="upload.php?client_id=<?= $id ?>">Upload document</a>
    <a href="../logout.php">Sign out</a>
  </nav>
</header>

<div class="container">
  <div class="client-header">
    <h2><?= htmlspecialchars($client['name']) ?></h2>
    <div class="client-meta">
      <?php if (!empty($client['company'])): ?><span><?= htmlspecialchars($client['company']) ?></span><?php endif; ?>
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
             <div class="doc-date"><?= date('M j, Y',strtotime($doc['uploaded_at'])) ?></div></div>
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
             <div class="doc-date"><?= date('M j, Y',strtotime($doc['uploaded_at'])) ?></div></div>
        <a href="../download.php?id=<?= $doc['id'] ?>" class="btn-sm">Download</a>
      </div>
    <?php endforeach;endif; ?>
  </div>

  <?php elseif ($active_tab === 'subscription'): ?>

  <!-- BASE PLAN -->
  <div class="section-card">
    <h3>Base plan</h3>
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
        <label>Client-facing description <span style="font-size:0.85rem;color:#6b6b6b;font-family:Barlow,sans-serif;font-weight:400">(one bullet per line, shown on the client dashboard)</span></label>
        <textarea name="base_description" rows="6" placeholder="12–14 content pieces/month across Instagram, LinkedIn & Facebook&#10;Full monthly content calendar&#10;Branding updates, printables, flyers & presentations&#10;Weekly or biweekly check-in calls&#10;Same-day response during business hours (9am–5pm CT, Mon–Fri)"><?= htmlspecialchars($sub['base_description']??'') ?></textarea>
      </div>
      <div class="form-group">
        <label>Internal Notes <span style="font-size:0.85rem;color:#6b6b6b;font-family:Barlow,sans-serif;font-weight:400">(not visible to client)</span></label>
        <textarea name="notes"><?= htmlspecialchars($sub['notes']??'') ?></textarea>
      </div>
      <button type="submit" class="btn">Save plan</button>
    </form>
  </div>

  <?php if ($sub): ?>

  <!-- QUICK ADD STANDARD PACKAGES -->
  <div class="section-card">
    <h3>Quick add: standard packages</h3>
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
              <label for="use-promo-<?= $key ?>" style="font:500 0.95rem Barlow,sans-serif;cursor:pointer;margin:0">Activate promotional pricing</label>
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
      <button type="submit" class="btn">Add selected packages</button>
    </form>
  </div>

  <!-- CURRENT ADD-ONS -->
  <div class="section-card">
    <h3>Active add-ons</h3>
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
            <?php else:?><span style="color:#a3a3a3">none</span><?php endif;?></td>
        <td><?= $addon['promo_cycles_remaining']>0?$addon['promo_cycles_remaining']:'<span style="color:#a3a3a3">none</span>' ?></td>
        <td><span class="status-badge <?= $addon['active']?'status-approved':'' ?>"><?= $addon['active']?'Active':'Inactive' ?></span></td>
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
        <td colspan="6" style="padding:14px 12px;background:#f2f2f2">
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
            <div><label>Client-facing description <span style="font-size:0.85rem;color:#6b6b6b;font-family:Barlow,sans-serif;font-weight:400">(one bullet per line)</span></label>
              <textarea name="addon_description" rows="3"><?= htmlspecialchars($addon['description']??'') ?></textarea></div>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <hr class="divider">
    <?php else: ?><p class="empty" style="margin-bottom:16px">No add-ons yet.</p><?php endif; ?>

    <h3 style="margin-top:4px">Add a custom add-on</h3>
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
      <div><label>Description <span style="font-size:0.85rem;color:#6b6b6b;font-family:Barlow,sans-serif;font-weight:400">(one bullet per line)</span></label>
        <textarea name="addon_description" rows="3" placeholder="What's included..."></textarea></div>
    </form>
  </div>
  <?php else: ?>
  <p class="empty" style="text-align:left;padding:8px 0">Save the base plan first to manage add-ons.</p>
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
        <div style="font-size:0.9rem;color:#6b6b6b;padding-top:10px;border-top:1px solid #d6d6d6;margin-top:8px">
          <strong>Note:</strong> <?= htmlspecialchars($req['admin_note']) ?></div>
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
<div class="toast" id="toast"><?= htmlspecialchars($msg) ?></div>
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
