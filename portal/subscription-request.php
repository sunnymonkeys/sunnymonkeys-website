<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: https://sunnymonkeys.com/portal/login.php'); exit;
}
require_once __DIR__ . '/config/db.php';
$pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8', DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php'); exit;
}

$client_id = $_SESSION['user_id'];
$type      = in_array($_POST['type'] ?? '', ['addon','removal','custom']) ? $_POST['type'] : 'custom';
$message   = trim($_POST['message'] ?? '');

if ($message) {
    $pdo->prepare('INSERT INTO subscription_requests (client_id, type, message) VALUES (?, ?, ?)')
       ->execute([$client_id, $type, $message]);
    $msg = 'Your request was sent. We\'ll follow up shortly.';
} else {
    $msg = 'Please include a message with your request.';
}

header('Location: dashboard.php?req_msg=' . urlencode($msg));
exit;
