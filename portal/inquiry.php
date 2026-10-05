<?php
// Receives the website's contact form and newsletter sign-ups.
// Every submission is saved to the `inquiries` table FIRST, so nothing is lost if email fails.
// Contact messages are then emailed through Titan (see config/mail.php) after the visitor gets a reply.

ignore_user_abort(true);
header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex');

function reply(int $code, string $message): void
{
    http_response_code($code);
    echo $message;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    reply(405, 'Method not allowed.');
    exit;
}

require_once __DIR__ . '/lib/inquiries.php';

$type    = ($_POST['form'] ?? '') === 'newsletter' ? 'newsletter' : 'contact';
$email   = trim($_POST['email'] ?? '');
$name    = trim($_POST['name'] ?? '');
$service = trim($_POST['service'] ?? '');
$message = trim($_POST['message'] ?? '');
$fallback = 'Sorry, something went wrong. Please email us at contact@sunnymonkeys.com.';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 200) {
    reply(422, 'Please enter a valid email address.');
    exit;
}
if ($type === 'contact') {
    if ($name === '' || inquiry_len($name) > 120) {
        reply(422, 'Please enter your name.');
        exit;
    }
    if (!array_key_exists($service, INQUIRY_SERVICES)) {
        reply(422, 'Please choose a service or package.');
        exit;
    }
    if ($message === '' || inquiry_len($message) > 5000) {
        reply(422, 'Please tell us about your project (up to 5,000 characters).');
        exit;
    }
}

// Spam signals: a hidden field people never see got filled in, or the form was sent within 3 seconds
// of the page loading. Flagged submissions are still saved (in case a real person tripped them),
// but they don't trigger an email.
$started = (int) ($_POST['started'] ?? 0);
$spam = (($_POST['website'] ?? '') !== '') || ($started > 0 && (time() * 1000 - $started) < 3000);

$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$ipHash = hash('sha256', $ip . '|' . DB_NAME);

try {
    $pdo = inquiries_db();

    $recent = $pdo->prepare('SELECT COUNT(*) FROM inquiries WHERE ip_hash = ? AND created_at > (NOW() - INTERVAL 1 HOUR)');
    $recent->execute([$ipHash]);
    if ((int) $recent->fetchColumn() >= 5) {
        reply(429, 'Too many messages from your connection. Please try again later or email us at contact@sunnymonkeys.com.');
        exit;
    }

    if ($type === 'newsletter') {
        $dupe = $pdo->prepare("SELECT id FROM inquiries WHERE type = 'newsletter' AND email = ? AND spam = 0 LIMIT 1");
        $dupe->execute([$email]);
        if ($dupe->fetchColumn()) {
            reply(200, "You're already subscribed. Thanks!");
            exit;
        }
    }

    $insert = $pdo->prepare('INSERT INTO inquiries (type, name, email, service, message, spam, ip_hash, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $insert->execute([
        $type,
        $name !== '' ? $name : null,
        $email,
        $service !== '' ? $service : null,
        $message !== '' ? $message : null,
        $spam ? 1 : 0,
        $ipHash,
        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);
    $id = (int) $pdo->lastInsertId();
} catch (Throwable $e) {
    error_log('inquiry.php: could not save submission: ' . $e->getMessage());
    reply(500, $fallback);
    exit;
}

reply(200, $type === 'newsletter'
    ? "Thanks! You're subscribed."
    : "Thanks, we got your message! We'll get back to you soon.");

if ($type !== 'contact' || $spam) {
    exit;
}

// Let the visitor go before talking to the mail server.
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} elseif (function_exists('litespeed_finish_request')) {
    litespeed_finish_request();
} else {
    flush();
}

if (is_file(__DIR__ . '/config/mail.php')) {
    require_once __DIR__ . '/config/mail.php';
}
require_once __DIR__ . '/lib/mailer.php';

$serviceLabel = INQUIRY_SERVICES[$service];
$body = "New inquiry from sunnymonkeys.com\n\n"
      . "Name:    $name\n"
      . "Email:   $email\n"
      . "Service: $serviceLabel\n\n"
      . "$message\n\n"
      . "---\n"
      . "Reply to this email to answer them directly.\n"
      . "All inquiries: https://sunnymonkeys.com/portal/admin/inquiries.php\n";

$result = sm_send_mail(defined('MAIL_TO') ? MAIL_TO : 'contact@sunnymonkeys.com', "New inquiry: $serviceLabel from $name", $body, $email);

try {
    $pdo->prepare('UPDATE inquiries SET email_status = ? WHERE id = ?')
        ->execute([$result === true ? 'sent' : substr('failed: ' . $result, 0, 255), $id]);
} catch (Throwable $e) {
    error_log('inquiry.php: could not record email status: ' . $e->getMessage());
}
