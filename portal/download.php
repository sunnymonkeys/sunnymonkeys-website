<?php
session_start();
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/config/db.php';
$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8', DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$id = intval($_GET['id'] ?? 0);

if (isset($_SESSION['admin_id'])) {
    $stmt = $pdo->prepare('SELECT * FROM documents WHERE id = ?');
    $stmt->execute([$id]);
} else {
    $stmt = $pdo->prepare('SELECT * FROM documents WHERE id = ? AND client_id = ?');
    $stmt->execute([$id, $_SESSION['user_id']]);
}

$doc = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$doc) { http_response_code(404); die('File not found.'); }

$filepath = __DIR__ . '/uploads/' . $doc['filename'];
if (!file_exists($filepath)) { http_response_code(404); die('File not found.'); }

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . basename($doc['filename']) . '"');
header('Content-Length: ' . filesize($filepath));
readfile($filepath);
exit;
