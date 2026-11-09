<?php
require_once __DIR__ . '/../lib/core.php';
require_admin();
$pdo = portal_db();

$clients   = $pdo->query('SELECT id, name, company FROM clients ORDER BY name')->fetchAll();
$success   = $error = '';
$preselect = (int) ($_GET['client_id'] ?? 0);

// A real PDF: starts with %PDF- and the file type check agrees. The browser's own claim is ignored.
function upload_is_pdf(string $tmp): bool
{
    $head = (string) file_get_contents($tmp, false, null, 0, 1024);
    if (strpos($head, '%PDF-') === false) {
        return false;
    }
    if (class_exists('finfo')) {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if ($mime !== 'application/pdf') {
            return false;
        }
    }
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $client_id = (int) ($_POST['client_id'] ?? 0);
    $type      = $_POST['type'] ?? '';
    $title     = trim((string) ($_POST['title'] ?? ''));
    $file      = $_FILES['document'] ?? null;

    if (!$client_id || !in_array($type, ['invoice', 'contract'], true) || $title === '' || portal_len($title) > 255
        || !$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        $error = 'All fields are required and the file must upload successfully.';
    } elseif ($file['size'] > 10 * 1024 * 1024) {
        $error = 'File must be under 10MB.';
    } elseif (!upload_is_pdf($file['tmp_name'])) {
        $error = 'Only PDF files are allowed.';
    } else {
        $known = $pdo->prepare('SELECT id FROM clients WHERE id = ?');
        $known->execute([$client_id]);
        if (!$known->fetch()) {
            $error = 'Unknown client.';
        } else {
            // Stored under a random name; the title is what people see.
            $filename = 'doc_' . bin2hex(random_bytes(8)) . '.pdf';
            $dest     = __DIR__ . '/../uploads/' . $filename;
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $pdo->prepare('INSERT INTO documents (client_id, type, title, filename) VALUES (?, ?, ?, ?)')
                    ->execute([$client_id, $type, $title, $filename]);
                $success = 'Document uploaded successfully.';
            } else {
                $error = 'Failed to save file. Check uploads folder permissions.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Upload document | Sunny Monkeys admin</title>
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
    <a href="../logout.php">Sign out</a>
  </nav>
</header>
<div class="container narrow">
  <h2>Upload a document</h2>
  <?php if ($success): ?><div class="success"><?= h($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
  <form method="POST" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <label for="u-client">Client</label>
    <select name="client_id" id="u-client" required>
      <option value="">Choose a client</option>
      <?php foreach ($clients as $c): ?>
        <option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === $preselect ? 'selected' : '' ?>>
          <?= h($c['name']) ?><?= $c['company'] ? ', ' . h($c['company']) : '' ?>
        </option>
      <?php endforeach; ?>
    </select>
    <label for="u-type">Document type</label>
    <select name="type" id="u-type" required>
      <option value="">Choose a type</option>
      <option value="invoice">Invoice</option>
      <option value="contract">Contract</option>
    </select>
    <label for="u-title">Document title</label>
    <input type="text" id="u-title" name="title" required maxlength="255" placeholder="e.g. Invoice 001, June 2026">
    <label for="u-file">PDF file (max 10MB)</label>
    <input type="file" id="u-file" name="document" accept="application/pdf,.pdf" required>
    <button type="submit" class="btn">Upload document</button>
  </form>
</div>
</body>
</html>
