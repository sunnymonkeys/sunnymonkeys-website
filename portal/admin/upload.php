<?php
session_start();

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

require_once '../config/db.php';
$db = getDB();

$success = '';
$error   = '';

// Load client list for dropdown
$clients = $db->query("SELECT id, name, email FROM users WHERE role='client' ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientId = (int)($_POST['client_id'] ?? 0);
    $title    = trim($_POST['title']     ?? '');
    $type     = $_POST['type']           ?? '';

    if (!$clientId || !$title || !in_array($type, ['invoice','contract'])) {
        $error = 'Please fill in all fields.';
    } elseif (empty($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please select a PDF file to upload.';
    } else {
        $file     = $_FILES['pdf'];
        $origName = basename($file['name']);
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if ($ext !== 'pdf') {
            $error = 'Only PDF files are accepted.';
        } else {
            // Check MIME type too
            $mime = mime_content_type($file['tmp_name']);
            if (!in_array($mime, ['application/pdf','application/x-pdf'])) {
                $error = 'File does not appear to be a valid PDF.';
            } else {
                // Save with random name to prevent guessing
                $storedName = bin2hex(random_bytes(16)) . '.pdf';
                $uploadDir  = __DIR__ . '/../uploads/';
                $destPath   = $uploadDir . $storedName;

                if (move_uploaded_file($file['tmp_name'], $destPath)) {
                    $ins = $db->prepare("
                        INSERT INTO documents (client_id, title, type, filename, original_name)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $ins->execute([$clientId, $title, $type, $storedName, $origName]);
                    $success = "Document <strong>" . htmlspecialchars($title) . "</strong> uploaded successfully.";
                } else {
                    $error = 'Upload failed. Check that the uploads/ folder is writable on the server.';
                }
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
    <title>Upload Document — Sunny Monkeys Portal</title>
    <link rel="shortcut icon" href="../../assets/images/fav.png">
    <link rel="stylesheet" href="../assets/portal.css">
</head>
<body>

<div class="portal-wrap">

    <header class="portal-header">
        <div style="display:flex;align-items:center;">
            <a href="../../index.html" class="logo"><img src="../../assets/images/logo-sunny-monkeys-white.png" alt="Sunny Monkeys"></a>
            <span class="logo-name">Admin Panel</span>
        </div>
        <div class="header-right">
            <a href="index.php">← Dashboard</a>
            <a href="../logout.php">Sign Out</a>
        </div>
    </header>

    <main class="portal-main">
        <h1 class="page-title">Upload Document</h1>
        <p class="page-subtitle">Upload an invoice or contract PDF for a specific client.</p>

        <nav class="admin-nav">
            <a href="index.php">Clients</a>
            <a href="upload.php" class="active">Upload Document</a>
            <a href="clients.php">Add Client</a>
        </nav>

        <div class="card" style="max-width:600px;">
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if (empty($clients)): ?>
                <div class="empty-state">
                    <div class="icon">👤</div>
                    <p>No clients yet. <a href="clients.php">Add a client first.</a></p>
                </div>
            <?php else: ?>
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="client_id">Client</label>
                        <select id="client_id" name="client_id" required>
                            <option value="">— Select client —</option>
                            <?php foreach ($clients as $c): ?>
                                <option value="<?= $c['id'] ?>"
                                    <?= (($_POST['client_id'] ?? '') == $c['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['email']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="type">Document Type</label>
                            <select id="type" name="type" required>
                                <option value="">— Select type —</option>
                                <option value="invoice"  <?= (($_POST['type'] ?? '') === 'invoice')  ? 'selected' : '' ?>>Invoice</option>
                                <option value="contract" <?= (($_POST['type'] ?? '') === 'contract') ? 'selected' : '' ?>>Contract</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="title">Document Title</label>
                            <input type="text" id="title" name="title"
                                   placeholder="e.g. Invoice #001 — June 2026"
                                   value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="pdf">PDF File</label>
                        <input type="file" id="pdf" name="pdf" accept=".pdf,application/pdf" required
                               style="padding:10px 14px;">
                    </div>
                    <button type="submit" class="btn btn-primary">Upload Document</button>
                </form>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
