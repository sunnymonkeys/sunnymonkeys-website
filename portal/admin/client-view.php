<?php
require_once __DIR__ . '/../lib/core.php';
require_admin();
$pdo = portal_db();

$id = (int) ($_GET['id'] ?? 0);
if (!$id) { portal_redirect('admin/index.php'); }
$stmt = $pdo->prepare('SELECT id, name, email, company, created_at FROM clients WHERE id = ?');
$stmt->execute([$id]);
$client = $stmt->fetch();
if (!$client) { portal_redirect('admin/index.php'); }

// Standard packages
$PACKAGES = [
  'email'    => ['name'=>'Email Monthly Tier',         'price'=>225.00, 'default_promo_price'=>0.00,   'default_promo_cycles'=>6,
                 'description'=>"Monthly email campaign design & copywriting\nSubscriber list management\nPerformance reporting"],
  'linkedin' => ['name'=>'LinkedIn Personal Branding', 'price'=>350.00, 'default_promo_price'=>175.00, 'default_promo_cycles'=>6,
                 'description'=>"LinkedIn content creation & scheduling\nPersonal brand strategy & profile optimization\nThought leadership post creation"],
  'video'    => ['name'=>'Short-Form Video (4 videos)', 'price'=>500.00, 'default_promo_price'=>null,   'default_promo_cycles'=>0,
                 'description'=>"4 short-form videos per month\nScripting, filming direction & editing\nSocial-ready formats for Reels, TikTok & Shorts"],
];
$BILLING_CYCLES = ['biweekly', 'monthly', 'weekly'];

// This client's subscription row, if any. Every add-on change is scoped to it.
$sub_lookup = $pdo->prepare('SELECT id FROM subscriptions WHERE client_id = ?');
$sub_lookup->execute([$id]);
$sub_id = (int) ($sub_lookup->fetchColumn() ?: 0);

// ── POST handler ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action       = $_POST['action'] ?? '';
    $redirect_tab = 'docs';
    $msg          = '';

    $money  = function ($v): float { $f = (float) $v; return $f < 0 ? 0.0 : round($f, 2); };
    $cycles = function ($v): int { return max(0, min((int) $v, 999)); };
    $text   = function ($v, int $max): string { return portal_cut(trim((string) $v), $max); };

    if ($action === 'save_subscription') {
        $visible          = isset($_POST['visible']) ? 1 : 0;
        $base_name        = $text($_POST['base_name'] ?? '', 120);
        if ($base_name === '') { $base_name = 'Base Retainer'; }
        $base_description = $text($_POST['base_description'] ?? '', 5000);
        $base_price       = $money($_POST['base_price'] ?? 0);
        $billing_cycle    = in_array($_POST['billing_cycle'] ?? '', $BILLING_CYCLES, true) ? $_POST['billing_cycle'] : 'biweekly';
        $start_raw        = trim((string) ($_POST['contract_start'] ?? ''));
        $contract_start   = null;
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $start_raw, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            $contract_start = $start_raw;
        }
        $notes = $text($_POST['notes'] ?? '', 5000);
        if ($sub_id) {
            $pdo->prepare('UPDATE subscriptions SET visible=?,base_name=?,base_description=?,base_price=?,billing_cycle=?,contract_start=?,notes=? WHERE id=? AND client_id=?')
               ->execute([$visible,$base_name,$base_description,$base_price,$billing_cycle,$contract_start,$notes,$sub_id,$id]);
        } else {
            $pdo->prepare('INSERT INTO subscriptions (client_id,visible,base_name,base_description,base_price,billing_cycle,contract_start,notes) VALUES (?,?,?,?,?,?,?,?)')
               ->execute([$id,$visible,$base_name,$base_description,$base_price,$billing_cycle,$contract_start,$notes]);
        }
        $msg = 'Subscription saved.'; $redirect_tab = 'subscription';
    }

    elseif ($action === 'add_packages') {
        $redirect_tab = 'subscription';
        if (!$sub_id) {
            $msg = 'Save the base plan first.';
        } else {
            $selected = $_POST['packages'] ?? [];
            $selected = is_array($selected) ? $selected : [];
            $added    = 0;
            foreach ($selected as $key) {
                if (!is_string($key) || !isset($PACKAGES[$key])) continue;
                $pkg          = $PACKAGES[$key];
                $use_promo    = !empty($_POST["promo_$key"]);
                $promo_price  = null;
                $promo_cycles = 0;
                if ($use_promo) {
                    $raw          = $_POST["promo_price_$key"] ?? '';
                    $promo_price  = $raw !== '' ? $money($raw) : null;
                    $promo_cycles = $cycles($_POST["promo_cycles_$key"] ?? 0);
                }
                $pdo->prepare('INSERT INTO subscription_addons (subscription_id,name,description,price,promo_price,promo_cycles_remaining) VALUES (?,?,?,?,?,?)')
                   ->execute([$sub_id,$pkg['name'],$pkg['description'],$pkg['price'],$promo_price,$promo_cycles]);
                $added++;
            }
            $msg = $added ? "$added package".($added>1?'s':'')." added." : 'No packages selected.';
        }
    }

    elseif ($action === 'add_addon') {
        $redirect_tab = 'subscription';
        $name         = $text($_POST['addon_name'] ?? '', 120);
        $description  = $text($_POST['addon_description'] ?? '', 5000);
        $price        = $money($_POST['addon_price'] ?? 0);
        $promo_raw    = $_POST['addon_promo_price'] ?? '';
        $promo_price  = ($promo_raw !== '') ? $money($promo_raw) : null;
        $promo_cycles = $cycles($_POST['addon_promo_cycles'] ?? 0);
        if (!$sub_id) {
            $msg = 'Save the base plan first.';
        } elseif ($name === '') {
            $msg = 'Please give the add-on a name.';
        } else {
            $pdo->prepare('INSERT INTO subscription_addons (subscription_id,name,description,price,promo_price,promo_cycles_remaining) VALUES (?,?,?,?,?,?)')
               ->execute([$sub_id,$name,$description,$price,$promo_price,$promo_cycles]);
            $msg = "Add-on \"$name\" added.";
        }
    }

    elseif ($action === 'update_addon') {
        $redirect_tab = 'subscription';
        $addon_id     = (int) ($_POST['addon_id'] ?? 0);
        $name         = $text($_POST['addon_name'] ?? '', 120);
        $description  = $text($_POST['addon_description'] ?? '', 5000);
        $price        = $money($_POST['addon_price'] ?? 0);
        $promo_raw    = $_POST['addon_promo_price'] ?? '';
        $promo_price  = ($promo_raw !== '') ? $money($promo_raw) : null;
        $promo_cycles = $cycles($_POST['addon_promo_cycles'] ?? 0);
        $active       = (($_POST['active'] ?? '1') === '0') ? 0 : 1;
        if ($name === '') {
            $msg = 'Please give the add-on a name.';
        } else {
            $pdo->prepare('UPDATE subscription_addons SET name=?,description=?,price=?,promo_price=?,promo_cycles_remaining=?,active=? WHERE id=? AND subscription_id=?')
               ->execute([$name,$description,$price,$promo_price,$promo_cycles,$active,$addon_id,$sub_id]);
            $msg = 'Add-on updated.';
        }
    }

    elseif ($action === 'delete_addon') {
        $pdo->prepare('DELETE FROM subscription_addons WHERE id=? AND subscription_id=?')->execute([(int) ($_POST['addon_id'] ?? 0), $sub_id]);
        $msg = 'Add-on removed.'; $redirect_tab = 'subscription';
    }

    elseif ($action === 'respond_request') {
        $req_id = (int) ($_POST['request_id'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['approved','declined','pending'], true) ? $_POST['status'] : 'pending';
        $note   = $text($_POST['admin_note'] ?? '', 1000);
        $pdo->prepare('UPDATE subscription_requests SET status=?,admin_note=? WHERE id=? AND client_id=?')->execute([$status,$note,$req_id,$id]);
        $msg = 'Request updated.'; $redirect_tab = 'requests';
    }

    if ($msg !== '') { flash_set($msg); }
    portal_redirect('admin/client-view.php?id=' . $id . '&tab=' . urlencode($redirect_tab));
}

// ── Fetch ─────────────────────────────────────────────────────────────────────
$docs = $pdo->prepare('SELECT id, title, type, uploaded_at FROM documents WHERE client_id = ? ORDER BY uploaded_at DESC');
$docs->execute([$id]); $docs = $docs->fetchAll();

$sub = null;
if ($sub_id) {
    $sub_stmt = $pdo->prepare('SELECT id, visible, base_name, base_description, base_price, billing_cycle, contract_start, notes FROM subscriptions WHERE id = ?');
    $sub_stmt->execute([$sub_id]); $sub = $sub_stmt->fetch() ?: null;
}

$addons = [];
if ($sub) {
    $a = $pdo->prepare('SELECT id, name, description, price, promo_price, promo_cycles_remaining, active FROM subscription_addons WHERE subscription_id = ? ORDER BY id');
    $a->execute([$sub_id]); $addons = $a->fetchAll();
}

$req_stmt = $pdo->prepare('SELECT id, type, message, status, admin_note, created_at FROM subscription_requests WHERE client_id = ? ORDER BY created_at DESC');
$req_stmt->execute([$id]); $all_requests = $req_stmt->fetchAll();
$pending_count = count(array_filter($all_requests, fn($r) => $r['status']==='pending'));

$active_tab = in_array($_GET['tab'] ?? '', ['docs', 'subscription', 'requests'], true) ? $_GET['tab'] : 'docs';
$msg        = flash_take();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Client | Sunny Monkeys admin</title>
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
    <a href="index.php">Clients</a>
    <a href="upload.php?client_id=<?= $id ?>">Upload document</a>
    <a href="../logout.php">Sign out</a>
  </nav>
</header>

<div class="container">
  <div class="client-header">
    <h2><?= h($client['name']) ?></h2>
    <div class="client-meta">
      <?php if (!empty($client['company'])): ?><span><?= h($client['company']) ?></span><?php endif; ?>
      <span><?= h($client['email']) ?></span>
      <span>Client since <?= h(date('M j, Y', strtotime($client['created_at']))) ?></span>
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
        <div><div style="font-weight:500"><?= h($doc['title']) ?></div>
             <div class="doc-date"><?= h(date('M j, Y',strtotime($doc['uploaded_at']))) ?></div></div>
        <a href="../download.php?id=<?= (int) $doc['id'] ?>" class="btn-sm">Download</a>
      </div>
    <?php endforeach;endif; ?>
  </div>
  <div class="section-card">
    <h3>Contracts</h3>
    <?php $con=array_values(array_filter($docs,fn($d)=>$d['type']==='contract')); ?>
    <?php if(empty($con)): ?><p class="empty">No contracts yet.</p><?php else: foreach($con as $doc): ?>
      <div class="doc-item">
        <div><div style="font-weight:500"><?= h($doc['title']) ?></div>
             <div class="doc-date"><?= h(date('M j, Y',strtotime($doc['uploaded_at']))) ?></div></div>
        <a href="../download.php?id=<?= (int) $doc['id'] ?>" class="btn-sm">Download</a>
      </div>
    <?php endforeach;endif; ?>
  </div>

  <?php elseif ($active_tab === 'subscription'): ?>

  <!-- BASE PLAN -->
  <div class="section-card">
    <h3>Base plan</h3>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_subscription">
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
          <input type="text" name="base_name" maxlength="120" value="<?= h($sub['base_name']??'Base Retainer') ?>"></div>
        <div class="form-group"><label>Base Price ($)</label>
          <input type="number" name="base_price" step="0.01" min="0" value="<?= h($sub['base_price']??'1725.00') ?>"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Billing Cycle</label>
          <select name="billing_cycle">
            <option value="biweekly" <?= ($sub['billing_cycle']??'biweekly')==='biweekly'?'selected':'' ?>>Biweekly</option>
            <option value="monthly"  <?= ($sub['billing_cycle']??'')==='monthly' ?'selected':'' ?>>Monthly</option>
            <option value="weekly"   <?= ($sub['billing_cycle']??'')==='weekly'  ?'selected':'' ?>>Weekly</option>
          </select></div>
        <div class="form-group"><label>Contract Start Date</label>
          <input type="date" name="contract_start" value="<?= h($sub['contract_start']??'') ?>"></div>
      </div>
      <div class="form-group">
        <label>Client-facing description <span style="font-size:0.85rem;color:#6b6b6b;font-family:Barlow,sans-serif;font-weight:400">(one bullet per line, shown on the client dashboard)</span></label>
        <textarea name="base_description" rows="6" maxlength="5000" placeholder="12–14 content pieces/month across Instagram, LinkedIn & Facebook&#10;Full monthly content calendar&#10;Branding updates, printables, flyers & presentations&#10;Weekly or biweekly check-in calls&#10;Same-day response during business hours (9am–5pm CT, Mon–Fri)"><?= h($sub['base_description']??'') ?></textarea>
      </div>
      <div class="form-group">
        <label>Internal Notes <span style="font-size:0.85rem;color:#6b6b6b;font-family:Barlow,sans-serif;font-weight:400">(not visible to client)</span></label>
        <textarea name="notes" maxlength="5000"><?= h($sub['notes']??'') ?></textarea>
      </div>
      <button type="submit" class="btn">Save plan</button>
    </form>
  </div>

  <?php if ($sub): ?>

  <!-- QUICK ADD STANDARD PACKAGES -->
  <div class="section-card">
    <h3>Quick add: standard packages</h3>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_packages">
      <div class="pkg-grid">
        <?php foreach ($PACKAGES as $key => $pkg): ?>
        <div class="pkg-item">
          <div class="pkg-item-top">
            <input type="checkbox" id="pkg-<?= $key ?>" name="packages[]" value="<?= $key ?>" data-promo-for="<?= $key ?>">
            <label for="pkg-<?= $key ?>"><?= h($pkg['name']) ?></label>
            <span class="pkg-price">$<?= number_format($pkg['price'],2) ?>/<?= h($sub['billing_cycle']??'biweekly') ?></span>
          </div>
          <div class="pkg-promo" id="promo-<?= $key ?>">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
              <input type="checkbox" id="use-promo-<?= $key ?>" name="promo_<?= $key ?>" value="1" data-promo-fields="<?= $key ?>"
                <?= $pkg['default_promo_price']!==null?'checked':'' ?>>
              <label for="use-promo-<?= $key ?>" style="font:500 0.95rem Barlow,sans-serif;cursor:pointer;margin:0">Activate promotional pricing</label>
            </div>
            <div id="promo-fields-<?= $key ?>" style="display:<?= $pkg['default_promo_price']!==null?'grid':'none' ?>;grid-template-columns:1fr 1fr;gap:10px">
              <div><label>Promo Price ($)</label>
                <input type="number" name="promo_price_<?= $key ?>" step="0.01" min="0"
                  value="<?= $pkg['default_promo_price']!==null ? h($pkg['default_promo_price']) : '' ?>"
                  placeholder="0 = FREE, blank = none"></div>
              <div><label>Promo Cycles</label>
                <input type="number" name="promo_cycles_<?= $key ?>" min="0" max="999" value="<?= (int) $pkg['default_promo_cycles'] ?>" placeholder="0"></div>
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
        <td><?= h($addon['name']) ?></td>
        <td>$<?= number_format((float) $addon['price'],2) ?></td>
        <td><?php if($addon['promo_price']!==null&&$addon['promo_cycles_remaining']>0):?>
              <span class="promo-badge"><?= (float) $addon['promo_price']==0?'FREE':'$'.number_format((float) $addon['promo_price'],2) ?></span>
            <?php else:?><span style="color:#a3a3a3">none</span><?php endif;?></td>
        <td><?= $addon['promo_cycles_remaining']>0?(int) $addon['promo_cycles_remaining']:'<span style="color:#a3a3a3">none</span>' ?></td>
        <td><span class="status-badge <?= $addon['active']?'status-approved':'' ?>"><?= $addon['active']?'Active':'Inactive' ?></span></td>
        <td style="white-space:nowrap">
          <button class="btn-sm" type="button" data-edit-toggle="<?= (int) $addon['id'] ?>">Edit</button>
          <form method="POST" style="display:inline" data-confirm="Remove this add-on?">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_addon">
            <input type="hidden" name="addon_id" value="<?= (int) $addon['id'] ?>">
            <button type="submit" class="btn-sm btn-danger" style="margin-left:6px">Remove</button>
          </form>
        </td>
      </tr>
      <tr id="edit-<?= (int) $addon['id'] ?>" style="display:none">
        <td colspan="6" style="padding:14px 12px;background:#f2f2f2">
          <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_addon">
            <input type="hidden" name="addon_id" value="<?= (int) $addon['id'] ?>">
            <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr auto;gap:10px;align-items:end;margin-bottom:10px">
              <div><label>Name</label><input type="text" name="addon_name" maxlength="120" required value="<?= h($addon['name']) ?>"></div>
              <div><label>Regular ($)</label><input type="number" name="addon_price" step="0.01" min="0" value="<?= h($addon['price']) ?>"></div>
              <div><label>Promo ($)</label><input type="number" name="addon_promo_price" step="0.01" min="0" value="<?= h($addon['promo_price']??'') ?>" placeholder="blank=none"></div>
              <div><label>Promo Cycles</label><input type="number" name="addon_promo_cycles" min="0" max="999" value="<?= (int) $addon['promo_cycles_remaining'] ?>"></div>
              <div><label>Status</label><select name="active"><option value="1" <?= $addon['active']?'selected':'' ?>>Active</option><option value="0" <?= !$addon['active']?'selected':'' ?>>Inactive</option></select></div>
              <button type="submit" class="btn" style="white-space:nowrap">Save</button>
            </div>
            <div><label>Client-facing description <span style="font-size:0.85rem;color:#6b6b6b;font-family:Barlow,sans-serif;font-weight:400">(one bullet per line)</span></label>
              <textarea name="addon_description" rows="3" maxlength="5000"><?= h($addon['description']??'') ?></textarea></div>
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
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_addon">
      <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:12px;align-items:end;margin-bottom:12px">
        <div><label>Service Name</label><input type="text" name="addon_name" maxlength="120" placeholder="Custom service" required></div>
        <div><label>Regular ($)</label><input type="number" name="addon_price" step="0.01" min="0" placeholder="0.00" required></div>
        <div><label>Promo ($)</label><input type="number" name="addon_promo_price" step="0.01" min="0" placeholder="0=FREE, blank=none"></div>
        <div><label>Promo Cycles</label><input type="number" name="addon_promo_cycles" min="0" max="999" value="0"></div>
        <button type="submit" class="btn">Add</button>
      </div>
      <div><label>Description <span style="font-size:0.85rem;color:#6b6b6b;font-family:Barlow,sans-serif;font-weight:400">(one bullet per line)</span></label>
        <textarea name="addon_description" rows="3" maxlength="5000" placeholder="What's included..."></textarea></div>
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
          <div class="req-type"><?= h(ucfirst($req['type'])) ?> request</div>
          <div class="req-msg"><?= nl2br(h($req['message'])) ?></div>
          <div class="req-date"><?= h(date('M j, Y \a\t g:ia',strtotime($req['created_at']))) ?></div>
        </div>
        <span class="status-badge status-<?= h($req['status']) ?>"><?= h(ucfirst($req['status'])) ?></span>
      </div>
      <?php if(!empty($req['admin_note'])): ?>
        <div style="font-size:0.9rem;color:#6b6b6b;padding-top:10px;border-top:1px solid #d6d6d6;margin-top:8px">
          <strong>Note:</strong> <?= h($req['admin_note']) ?></div>
      <?php endif; ?>
      <?php if($req['status']==='pending'): ?>
      <form method="POST" style="display:flex;gap:10px;margin-top:14px;align-items:flex-end;flex-wrap:wrap">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="respond_request">
        <input type="hidden" name="request_id" value="<?= (int) $req['id'] ?>">
        <div style="flex:1;min-width:200px"><label>Note (optional)</label><input type="text" name="admin_note" maxlength="1000" placeholder="Internal note..."></div>
        <button type="submit" name="status" value="approved" class="btn-approve">Approve</button>
        <button type="submit" name="status" value="declined" class="btn-decline">Decline</button>
      </form>
      <?php endif; ?>
    </div>
  <?php endforeach;endif; ?>
  <?php endif; ?>
</div>

<?php if($msg): ?>
<div class="toast" data-toast><?= h($msg) ?></div>
<?php endif; ?>
<script src="/portal/assets/portal.js?v=1" defer></script>
</body>
</html>
