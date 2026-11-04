<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: https://sunnymonkeys.com/portal/login.php'); exit; }
require_once __DIR__ . '/../lib/inquiries.php';
$pdo = inquiries_db();

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }

$tab = in_array($_GET['tab'] ?? '', ['contact', 'newsletter', 'spam'], true) ? $_GET['tab'] : 'contact';

// Mark read/unread, or move in/out of spam
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
    $id = (int) ($_POST['id'] ?? 0);
    switch ($_POST['action'] ?? '') {
        case 'read':     $pdo->prepare('UPDATE inquiries SET read_at = NOW() WHERE id = ?')->execute([$id]); break;
        case 'unread':   $pdo->prepare('UPDATE inquiries SET read_at = NULL WHERE id = ?')->execute([$id]); break;
        case 'spam':     $pdo->prepare('UPDATE inquiries SET spam = 1 WHERE id = ?')->execute([$id]); break;
        case 'not-spam': $pdo->prepare('UPDATE inquiries SET spam = 0 WHERE id = ?')->execute([$id]); break;
    }
    header('Location: inquiries.php?tab=' . $tab); exit;
}

$counts = $pdo->query("
    SELECT
      SUM(type = 'contact' AND spam = 0)                      AS contact,
      SUM(type = 'contact' AND spam = 0 AND read_at IS NULL)  AS unread,
      SUM(type = 'newsletter' AND spam = 0)                   AS newsletter,
      SUM(spam = 1)                                           AS spam
    FROM inquiries
")->fetch(PDO::FETCH_ASSOC);

if ($tab === 'spam') {
    $rows = $pdo->query('SELECT * FROM inquiries WHERE spam = 1 ORDER BY created_at DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare('SELECT * FROM inquiries WHERE type = ? AND spam = 0 ORDER BY created_at DESC LIMIT 500');
    $stmt->execute([$tab]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function action_button(int $id, string $action, string $label) {
    return '<form method="post" style="display:inline"><input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '">'
         . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="action" value="' . $action . '">'
         . '<button class="btn-sm" type="submit">' . $label . '</button></form>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inquiries | Sunny Monkeys</title>
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
    <a href="../logout.php">Sign out</a>
  </nav>
</header>
<div class="container">
  <h2>Inquiries</h2>
  <div class="tabs">
    <a href="?tab=contact" class="<?= $tab === 'contact' ? 'active' : '' ?>">Messages (<?= (int) $counts['contact'] ?><?= $counts['unread'] ? ', ' . (int) $counts['unread'] . ' new' : '' ?>)</a>
    <a href="?tab=newsletter" class="<?= $tab === 'newsletter' ? 'active' : '' ?>">Newsletter (<?= (int) $counts['newsletter'] ?>)</a>
    <a href="?tab=spam" class="<?= $tab === 'spam' ? 'active' : '' ?>">Spam (<?= (int) $counts['spam'] ?>)</a>
  </div>

  <?php if (empty($rows)): ?>
    <p class="empty">Nothing here yet.</p>

  <?php elseif ($tab === 'newsletter'): ?>
    <div class="copy-row"><button class="btn-sm" id="copy-emails" type="button">Copy all emails</button></div>
    <table>
      <thead><tr><th>Email</th><th>Signed up</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="sub-email"><?= h($r['email']) ?></td>
          <td class="meta"><?= h(date('M j, Y g:ia', strtotime($r['created_at']))) ?></td>
          <td style="text-align:right"><?= action_button((int) $r['id'], 'spam', 'Spam') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <script>
      document.getElementById('copy-emails').addEventListener('click', function () {
        var emails = Array.from(document.querySelectorAll('.sub-email')).map(function (td) { return td.textContent.trim(); });
        navigator.clipboard.writeText(emails.join('\n')).then(() => { this.textContent = 'Copied ' + emails.length; });
      });
    </script>

  <?php else: ?>
    <?php foreach ($rows as $r): $unread = $r['type'] === 'contact' && !$r['read_at'] && !$r['spam']; ?>
      <div class="card <?= $unread ? 'unread' : '' ?>">
        <div class="card-top">
          <div class="who">
            <?= h($r['name'] ?: '(no name)') ?>
            <a href="mailto:<?= h($r['email']) ?>?subject=<?= rawurlencode('Re: your inquiry to Sunny Monkeys') ?>"><?= h($r['email']) ?></a>
            <?php if ($r['service']): ?><span class="badge service"><?= h(array_key_exists($r['service'], INQUIRY_SERVICES) ? INQUIRY_SERVICES[$r['service']] : $r['service']) ?></span><?php endif; ?>
            <?php if ($r['type'] === 'newsletter'): ?><span class="badge service">Newsletter</span><?php endif; ?>
            <?php if ($unread): ?><span class="badge new">New</span><?php endif; ?>
          </div>
          <div class="meta">
            <?= h(date('M j, Y g:ia', strtotime($r['created_at']))) ?>
            <?php if ($r['email_status'] && $r['email_status'] !== 'sent'): ?>
              <span class="badge fail" title="<?= h($r['email_status']) ?>">Email not sent</span>
            <?php endif; ?>
          </div>
        </div>
        <?php if ($r['message']): ?><div class="msg"><?= h($r['message']) ?></div><?php endif; ?>
        <div class="actions">
          <?php if ($tab === 'spam'): ?>
            <?= action_button((int) $r['id'], 'not-spam', 'Not spam') ?>
          <?php else: ?>
            <?= $unread ? action_button((int) $r['id'], 'read', 'Mark as read') : action_button((int) $r['id'], 'unread', 'Mark as unread') ?>
            <?= action_button((int) $r['id'], 'spam', 'Spam') ?>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
</body>
</html>
