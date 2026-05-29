<?php
session_start();

// Auth check — clients only
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header('Location: login.php');
    exit;
}

require_once 'config/db.php';

$clientId = $_SESSION['user_id'];
$db       = getDB();

// Fetch documents grouped by type
$stmt = $db->prepare("
    SELECT * FROM documents WHERE client_id = ? ORDER BY uploaded_at DESC
");
$stmt->execute([$clientId]);
$allDocs = $stmt->fetchAll();

$invoices  = array_filter($allDocs, fn($d) => $d['type'] === 'invoice');
$contracts = array_filter($allDocs, fn($d) => $d['type'] === 'contract');

function fmtDate(string $d): string {
    return date('M j, Y', strtotime($d));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Documents — Sunny Monkeys Portal</title>
    <link rel="shortcut icon" href="../assets/images/fav.png">
    <link rel="stylesheet" href="assets/portal.css">
</head>
<body>

<div class="portal-wrap">

    <!-- Header -->
    <header class="portal-header">
        <div style="display:flex;align-items:center;">
            <a href="../index.html" class="logo"><img src="../assets/images/logo-sunny-monkeys-white.png" alt="Sunny Monkeys"></a>
            <span class="logo-name">Sunny Monkeys</span>
        </div>
        <div class="header-right">
            <span>Hello, <?= htmlspecialchars($_SESSION['name']) ?></span>
            <a href="logout.php">Sign Out</a>
        </div>
    </header>

    <!-- Main -->
    <main class="portal-main">
        <h1 class="page-title">My Documents</h1>
        <p class="page-subtitle">Your contracts and invoices from Sunny Monkeys LLC.</p>

        <!-- Contracts -->
        <div class="card">
            <div class="section-head">
                <h2>Contracts</h2>
            </div>
            <?php if (empty($contracts)): ?>
                <div class="empty-state">
                    <div class="icon">📄</div>
                    <p>No contracts uploaded yet.</p>
                </div>
            <?php else: ?>
                <ul class="doc-list">
                    <?php foreach ($contracts as $doc): ?>
                    <li class="doc-item">
                        <div class="doc-item-left">
                            <div class="doc-icon contract">📋</div>
                            <div>
                                <div class="doc-title"><?= htmlspecialchars($doc['title']) ?></div>
                                <div class="doc-meta">Uploaded <?= fmtDate($doc['uploaded_at']) ?></div>
                            </div>
                        </div>
                        <a href="download.php?id=<?= $doc['id'] ?>" class="doc-download">
                            ↓ Download
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- Invoices -->
        <div class="card">
            <div class="section-head">
                <h2>Invoices</h2>
            </div>
            <?php if (empty($invoices)): ?>
                <div class="empty-state">
                    <div class="icon">🧾</div>
                    <p>No invoices uploaded yet.</p>
                </div>
            <?php else: ?>
                <ul class="doc-list">
                    <?php foreach ($invoices as $doc): ?>
                    <li class="doc-item">
                        <div class="doc-item-left">
                            <div class="doc-icon invoice">🧾</div>
                            <div>
                                <div class="doc-title"><?= htmlspecialchars($doc['title']) ?></div>
                                <div class="doc-meta">Uploaded <?= fmtDate($doc['uploaded_at']) ?></div>
                            </div>
                        </div>
                        <a href="download.php?id=<?= $doc['id'] ?>" class="doc-download">
                            ↓ Download
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <p style="text-align:center;color:rgba(255,255,255,0.3);margin-top:40px;font-size:0.88rem;">
            Questions? Email us at <a href="mailto:contact@sunnymonkeys.com" style="color:rgba(255,255,255,0.5);">contact@sunnymonkeys.com</a>
        </p>
    </main>

</div>
</body>
</html>
