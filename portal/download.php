<?php
session_start();

// Must be logged in
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(404); exit('Not found.'); }

$db   = getDB();
$role = $_SESSION['role'];

// Admins can download any doc; clients only their own
if ($role === 'admin') {
    $stmt = $db->prepare("SELECT * FROM documents WHERE id = ?");
    $stmt->execute([$id]);
} else {
    $stmt = $db->prepare("SELECT * FROM documents WHERE id = ? AND client_id = ?");
    $stmt->execute([$id, $_SESSION['user_id']]);
}

$doc = $stmt->fetch();
if (!$doc) { http_response_code(403); exit('Access denied.'); }

$filePath = __DIR__ . '/uploads/' . $doc['filename'];
if (!file_exists($filePath)) { http_response_code(404); exit('File not found.'); }

// Serve the PDF
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . addslashes($doc['original_name']) . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
