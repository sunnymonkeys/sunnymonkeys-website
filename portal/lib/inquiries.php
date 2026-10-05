<?php
// Shared helpers for website inquiries (contact form + newsletter sign-ups).
// The table is created automatically the first time it's needed.

require_once __DIR__ . '/../config/db.php';

const INQUIRY_SERVICES = [
    'brand-identity'         => 'Brand Identity',
    'corporate-branding'     => 'Corporate Branding',
    'social-media-design'    => 'Social Media Design',
    'email-design-marketing' => 'Email Design & Marketing',
    'web-design'             => 'Web Design',
    'starter'                => 'Starter Package',
    'growth'                 => 'Growth Package',
    'premium'                => 'Premium Package',
    'custom'                 => "Custom / Let's Talk",
];

function inquiries_db(): PDO
{
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS inquiries (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            type         ENUM('contact','newsletter') NOT NULL,
            name         VARCHAR(120) NULL,
            email        VARCHAR(200) NOT NULL,
            service      VARCHAR(40)  NULL,
            message      TEXT         NULL,
            spam         TINYINT(1)   NOT NULL DEFAULT 0,
            ip_hash      CHAR(64)     NOT NULL,
            user_agent   VARCHAR(255) NULL,
            email_status VARCHAR(255) NULL,
            read_at      DATETIME     NULL,
            created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
            INDEX (type, created_at),
            INDEX (ip_hash, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    return $pdo;
}

function inquiry_len(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);
}
