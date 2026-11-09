<?php
// Serves a client's PDF. Admins can download any document; clients only their own.
require_once __DIR__ . '/lib/core.php';
portal_session();
portal_no_cache();
if (empty($_SESSION['user_id']) && empty($_SESSION['admin_id'])) {
    portal_redirect('login.php');
}

function not_found(): void
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('File not found.');
}

$pdo = portal_db();
$id  = (int) ($_GET['id'] ?? 0);

if (!empty($_SESSION['admin_id'])) {
    $stmt = $pdo->prepare('SELECT id, filename FROM documents WHERE id = ?');
    $stmt->execute([$id]);
} else {
    $stmt = $pdo->prepare('SELECT id, filename FROM documents WHERE id = ? AND client_id = ?');
    $stmt->execute([$id, (int) $_SESSION['user_id']]);
}
$doc = $stmt->fetch();
if (!$doc) {
    not_found();
}

// The stored name must be a plain file name inside uploads/, nothing else.
$name = (string) $doc['filename'];
if ($name === '' || basename($name) !== $name) {
    not_found();
}
$dir  = realpath(__DIR__ . '/uploads');
$path = $dir === false ? false : realpath($dir . '/' . $name);
if ($path === false || strpos($path, $dir . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) {
    not_found();
}

header('Content-Type: application/pdf');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', $name) . '"');
header('Content-Length: ' . (string) filesize($path));
readfile($path);
exit;
