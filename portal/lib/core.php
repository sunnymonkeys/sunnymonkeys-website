<?php
// Shared plumbing for the client portal: database connection, hardened sessions, sign-in checks,
// CSRF tokens, output escaping, flash messages and the sign-in throttle.
// Every portal script starts with: require_once __DIR__ . '/lib/core.php'; (admin pages: '/../lib/core.php').

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db.php';

const PORTAL_BASE = 'https://sunnymonkeys.com/portal/';
const PORTAL_IDLE_SECONDS = 14400;          // signed out after 4 hours without activity
const PORTAL_LOGIN_WINDOW_MINUTES = 15;
const PORTAL_LOGIN_MAX_PER_IP = 10;         // failed sign-ins per connection in the window
const PORTAL_LOGIN_MAX_PER_ACCOUNT = 5;     // failed sign-ins per email in the window

// Unexpected errors: the details go to the server log, visitors get a plain message, never a stack trace.
set_exception_handler(function (Throwable $e): void {
    error_log('portal: ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo 'Something went wrong on our side. Please try again in a few minutes or email contact@sunnymonkeys.com.';
    exit;
});

function portal_db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// Session cookie: HttpOnly (no JavaScript access), Secure (HTTPS only), SameSite=Lax (not sent on
// cross-site POSTs), strict mode (unknown session ids are rejected), and a 4-hour idle timeout.
function portal_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', (string) PORTAL_IDLE_SECONDS);
    session_name('smportal');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    $now = time();
    if (isset($_SESSION['last_seen']) && ($now - (int) $_SESSION['last_seen']) > PORTAL_IDLE_SECONDS) {
        portal_session_destroy();
        session_start();
    }
    $_SESSION['last_seen'] = $now;
}

function portal_session_destroy(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'],
        ]);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

// Call right after a password check succeeds: a fresh session id so a pre-login id is never reused.
function portal_sign_in(array $data): void
{
    session_regenerate_id(true);
    foreach ($data as $key => $value) {
        $_SESSION[$key] = $value;
    }
    $_SESSION['signed_in_at'] = time();
}

function portal_redirect(string $path): void
{
    header('Location: ' . PORTAL_BASE . ltrim($path, '/'));
    exit;
}

// Portal pages hold private data, so browsers must not keep copies.
function portal_no_cache(): void
{
    header('Cache-Control: no-store, max-age=0');
    header('Pragma: no-cache');
}

function require_admin(): void
{
    portal_session();
    portal_no_cache();
    if (empty($_SESSION['admin_id'])) {
        portal_redirect('login.php');
    }
}

function require_client(): void
{
    portal_session();
    portal_no_cache();
    if (empty($_SESSION['user_id'])) {
        portal_redirect('login.php');
    }
}

// Escape anything that goes into HTML.
function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function portal_len(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);
}

function portal_cut(string $s, int $max): string
{
    return function_exists('mb_substr') ? mb_substr($s, 0, $max) : substr($s, 0, $max);
}

// CSRF: every form carries a token tied to the session; every POST handler checks it.
function csrf_token(): string
{
    portal_session();
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    portal_session();
    $sent = $_POST['csrf'] ?? '';
    return is_string($sent) && !empty($_SESSION['csrf']) && is_string($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $sent);
}

function csrf_check(): void
{
    if (!csrf_valid()) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        exit('This form has expired. Please go back, reload the page and try again.');
    }
}

// One-time messages shown after a redirect (kept in the session, never in the URL).
function flash_set(string $message): void
{
    portal_session();
    $_SESSION['flash'] = $message;
}

function flash_take(): string
{
    portal_session();
    $message = $_SESSION['flash'] ?? '';
    unset($_SESSION['flash']);
    return is_string($message) ? $message : '';
}

function portal_hash(string $value): string
{
    return hash('sha256', $value . '|' . DB_NAME);
}

// The connection a request came from, as a hash. Raw addresses are never stored.
function portal_ip_hash(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip .= '|' . $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    return portal_hash($ip);
}

// Sign-in throttle: failed attempts are counted per connection and per email for 15 minutes.
function login_attempts_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS login_attempts (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            ip_hash      CHAR(64) NOT NULL,
            account_hash CHAR(64) NOT NULL,
            created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (ip_hash, created_at),
            INDEX (account_hash, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function login_blocked(PDO $pdo, string $ipHash, string $accountHash): bool
{
    login_attempts_table($pdo);
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(ip_hash = ?), 0) AS by_ip, COALESCE(SUM(account_hash = ?), 0) AS by_account
                           FROM login_attempts
                           WHERE created_at > (NOW() - INTERVAL ' . PORTAL_LOGIN_WINDOW_MINUTES . ' MINUTE)
                             AND (ip_hash = ? OR account_hash = ?)');
    $stmt->execute([$ipHash, $accountHash, $ipHash, $accountHash]);
    $row = $stmt->fetch();
    return (int) $row['by_ip'] >= PORTAL_LOGIN_MAX_PER_IP || (int) $row['by_account'] >= PORTAL_LOGIN_MAX_PER_ACCOUNT;
}

function login_record_failure(PDO $pdo, string $ipHash, string $accountHash): void
{
    $pdo->prepare('INSERT INTO login_attempts (ip_hash, account_hash) VALUES (?, ?)')->execute([$ipHash, $accountHash]);
    $pdo->exec('DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 1 DAY)');
}

function login_clear_failures(PDO $pdo, string $accountHash): void
{
    $pdo->prepare('DELETE FROM login_attempts WHERE account_hash = ?')->execute([$accountHash]);
}
