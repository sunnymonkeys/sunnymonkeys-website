<?php
// A signed-in client asks for a change to their plan. Saved for the admin, shown on the client's next page.
require_once __DIR__ . '/lib/core.php';
require_client();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    portal_redirect('dashboard.php');
}
csrf_check();

$type    = $_POST['type'] ?? '';
$type    = in_array($type, ['addon', 'removal', 'custom'], true) ? $type : 'custom';
$message = trim((string) ($_POST['message'] ?? ''));

if ($message === '') {
    flash_set('Please include a message with your request.');
} elseif (portal_len($message) > 5000) {
    flash_set('Please keep your request under 5,000 characters.');
} else {
    portal_db()->prepare('INSERT INTO subscription_requests (client_id, type, message) VALUES (?, ?, ?)')
        ->execute([(int) $_SESSION['user_id'], $type, $message]);
    flash_set("Your request was sent. We'll follow up shortly.");
}
portal_redirect('dashboard.php');
