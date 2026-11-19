<?php

$config = [];
$configFile = __DIR__ . '/config.local.php';
if (is_file($configFile)) {
    $config = require $configFile;
}

$host = getenv('DB_HOST') ?: ($config['DB_HOST'] ?? '');
$configuredPort = getenv('DB_PORT') ?: ($config['DB_PORT'] ?? 3306);
$database = getenv('DB_NAME') ?: ($config['DB_NAME'] ?? '');
$username = getenv('DB_USER') ?: ($config['DB_USER'] ?? '');
$password = getenv('DB_PASSWORD') ?: ($config['DB_PASSWORD'] ?? '');

if (!$host || !$database || !$username) {
    throw new RuntimeException('Database settings are missing. Set DB_HOST, DB_NAME, DB_USER, and DB_PASSWORD.');
}

$port = filter_var($configuredPort, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 65535],
]);
if ($port === false) {
    throw new RuntimeException('DB_PORT must be a valid port number between 1 and 65535.');
}

$dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $username, $password ?: '', $options);
} catch (PDOException $error) {
    error_log($error->getMessage());
    throw new RuntimeException('Unable to connect to the database. Check the server configuration.');
}
