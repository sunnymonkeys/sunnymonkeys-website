<?php
// Shared helpers for website inquiries (contact form + newsletter sign-ups).
// The table is created automatically the first time it's needed.

require_once __DIR__ . '/../config/db.php';

const INQUIRY_SERVICES = [
    'brand-identity'         => 'Brand Identity',
    'web-design'             => 'Web Design',
    'web-apps'               => 'Custom Web Apps & Development',
    'social-media-design'    => 'Social Media Design',
    'email-design-marketing' => 'Email Design & Marketing',
    'starter'                => 'Starter Package',
    'growth'                 => 'Growth Package',
    'premium'                => 'Premium Package',
    'in-house-full-time'     => 'In-House Designer (full-time)',
    'in-house-part-time'     => 'In-House Designer (part-time)',
    'in-house'               => 'In-House Designer (not sure yet)',
    'custom'                 => "Custom / Let's Talk",
    // No longer on the form (merged into Brand Identity), kept so older inquiries show their label.
    'corporate-branding'     => 'Corporate Branding',
];

// Asked only on In-House Designer inquiries. Saved as the first line of the message.
const INQUIRY_START_OPTIONS = [
    'asap'          => 'As soon as possible',
    '1-2-months'    => 'In 1 to 2 months',
    '3-months-plus' => 'In 3 months or later',
    'exploring'     => 'Just exploring for now',
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
