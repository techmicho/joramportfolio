<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

if ($argc < 3) {
    fwrite(STDERR, "Usage: php database/create_admin.php <email> <password>\n");
    exit(1);
}

$email = strtolower(trim($argv[1]));
$password = $argv[2];

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
    fwrite(STDERR, "A valid email and a password of at least 12 characters are required.\n");
    exit(1);
}

$statement = $pdo->prepare(
    'INSERT INTO admin_users (email, password_hash)
     VALUES (?, ?)
     ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), failed_attempts = 0, locked_until = NULL'
);
$statement->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);

echo "Administrator account created or updated.\n";
