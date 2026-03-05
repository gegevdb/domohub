<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

$username = $argv[1] ?? '';
$password = $argv[2] ?? '';

if ($username === '' || $password === '') {
    fwrite(STDERR, "Usage: php tools/create_admin.php <username> <password>\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

// Ensure users table exists on old DB
$db = db();

$existing = $db->fetchOne('SELECT id FROM users WHERE username = ?', [$username]);
if ($existing) {
    $db->execute('UPDATE users SET password_hash = ?, role = ?, enabled = 1 WHERE username = ?', [$hash, 'admin', $username]);
    fwrite(STDOUT, "Updated admin user: $username\n");
    exit(0);
}

$db->insert(
    'INSERT INTO users (username, password_hash, role, enabled) VALUES (?, ?, ?, 1)',
    [$username, $hash, 'admin']
);

fwrite(STDOUT, "Created admin user: $username\n");
