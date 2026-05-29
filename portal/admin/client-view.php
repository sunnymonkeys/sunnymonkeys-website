<?php
session_start();

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

require_once '../config/db.php';
$db = getDB();

$clientId = (int)($_GET['id'] ?? 0);
if (!$clientId) { header('Location: index.php'); exit; }

// Get client info
$stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role = 'client'");
$stmt->execute([$clientId]);
$client = $stmt->fetch();
if (!$client) { header('Location: index.php'); exit; }

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_doc'])) {
    $docId = (int)$_POST['delete_doc'];
    $sel   = $db->prepare("SELECT filename FROM documents WHERE id = ? AND client_id = ?");
    $sel->execute([$docId, $clientId]);
    $doc = $sel->fetch();
    if ($doc) {
        $filePath = __DIR__ . '/../uploads/' . $doc['filename'];
        if (file_exists($filePath)) unlink($filePath);
        $db->prepare("DELETE FROM documents WHERE id = ?")->execute([$docId]);
    }
    header("Location: client-view.php?id={$clientId}&deleted=1");
    exit;
}

// Get documents
$docs = $db->prepare("SELECT * FROM documents WHERE client_id = ? ORDER BY type, uploaded_at DESC");
$docs->execute([$clientId]);
$documents = $docs->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($client['name']) ?> — Sunny Monkeys Portal</title>
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
            <a href="index.php">← All Clients</a>
            <a href="../logout.php">Sign Out</a>
        </div>
    </header>

    <main class="portal-main">
        <h1 class="page-title"><?= htmlspecialchars($client['name']) ?></h1>
        <p class="page-subtitle"><?= htmlspecialchars($client['email']) ?></p>

        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">Document deleted successfully.</div>
        <?php endif; ?>

        <div style="display:flex;justify-content:flex-end;margin-bottom:28px;">
            <a href="upload.php?client_id=<?= $clientId ?>" class="btn btn-primary btn-sm">+ Upload Document</a>
        </div>

        <div class="card">
            <?php if (empty($documents)): ?>
                <div class="empty-state">
                    <div class="icon">📁</div>
                    <p>No documents yet. <a href="upload.php?client_id=<?= $clientId ?>">Upload the first one.</a></p>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Uploaded</th>
                            <th>File</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $doc): ?>
                        <tr>
                            <td><?= htmlspecialchars($doc['title']) ?></td>
                            <td><span class="badge badge-<?= $doc['type'] ?>"><?= ucfirst($doc['type']) ?></span></td>
                            <td style="color:rgba(255,255,255,0.4);font-size:0.88rem;">
                                <?= date('M j, Y', strtotime($doc['uploaded_at'])) ?>
                            </td>
                            <td style="color:rgba(255,255,255,0.4);font-size:0.82rem;">
                                <?= htmlspecialchars($doc['original_name']) ?>
                            </td>
                            <td style="display:flex;gap:8px;align-items:center;">
                                <a href="../download.php?id=<?= $doc['id'] ?>" class="btn btn-outline btn-sm">↓</a>
                                <form method="POST" onsubmit="return confirm('Delete this document?')">
                                    <input type="hidden" name="delete_doc" value="<?= $doc['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
