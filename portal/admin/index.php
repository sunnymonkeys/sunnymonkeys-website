<?php
session_start();

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

require_once '../config/db.php';
$db = getDB();

// Stats
$totalClients = $db->query("SELECT COUNT(*) FROM users WHERE role='client'")->fetchColumn();
$totalDocs    = $db->query("SELECT COUNT(*) FROM documents")->fetchColumn();
$totalInv     = $db->query("SELECT COUNT(*) FROM documents WHERE type='invoice'")->fetchColumn();
$totalCon     = $db->query("SELECT COUNT(*) FROM documents WHERE type='contract'")->fetchColumn();

// All clients with doc counts
$clients = $db->query("
    SELECT u.id, u.name, u.email, u.created_at,
           COUNT(d.id) as doc_count
    FROM users u
    LEFT JOIN documents d ON d.client_id = u.id
    WHERE u.role = 'client'
    GROUP BY u.id
    ORDER BY u.name ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Sunny Monkeys Portal</title>
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
            <span><?= htmlspecialchars($_SESSION['name']) ?></span>
            <a href="../logout.php">Sign Out</a>
        </div>
    </header>

    <main class="portal-main">
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">Manage clients and their documents.</p>

        <nav class="admin-nav">
            <a href="index.php" class="active">Clients</a>
            <a href="upload.php">Upload Document</a>
            <a href="clients.php">Add Client</a>
        </nav>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="num"><?= $totalClients ?></div>
                <div class="lbl">Clients</div>
            </div>
            <div class="stat-card">
                <div class="num"><?= $totalDocs ?></div>
                <div class="lbl">Total Documents</div>
            </div>
            <div class="stat-card">
                <div class="num"><?= $totalInv ?></div>
                <div class="lbl">Invoices</div>
            </div>
            <div class="stat-card">
                <div class="num"><?= $totalCon ?></div>
                <div class="lbl">Contracts</div>
            </div>
        </div>

        <!-- Client list -->
        <div class="card">
            <div class="section-head">
                <h2>All Clients</h2>
                <a href="clients.php" class="btn btn-outline btn-sm">+ Add Client</a>
            </div>

            <?php if (empty($clients)): ?>
                <div class="empty-state">
                    <div class="icon">👤</div>
                    <p>No clients yet. <a href="clients.php">Add your first client.</a></p>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Documents</th>
                            <th>Added</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['name']) ?></td>
                            <td style="color:rgba(255,255,255,0.5)"><?= htmlspecialchars($c['email']) ?></td>
                            <td><?= $c['doc_count'] ?></td>
                            <td style="color:rgba(255,255,255,0.4);font-size:0.88rem;">
                                <?= date('M j, Y', strtotime($c['created_at'])) ?>
                            </td>
                            <td>
                                <a href="client-view.php?id=<?= $c['id'] ?>" class="btn btn-outline btn-sm">View Docs</a>
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
