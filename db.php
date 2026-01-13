<?php
declare(strict_types=1);

/**
 * db.php
 * - Reads config.php (return array)
 * - Creates PDO as $conn
 * - Provides backward compatibility alias $pdo
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Load config.php (must return array)
$configPath = __DIR__ . '/config.php';
if (!is_file($configPath)) {
    http_response_code(500);
    exit('Missing config.php');
}

$config = require $configPath;
if (!is_array($config)) {
    http_response_code(500);
    exit('config.php must return an array');
}

// Validate config keys
foreach (['host', 'db', 'user'] as $key) {
    if (!array_key_exists($key, $config)) {
        http_response_code(500);
        exit("Missing DB config key: {$key}");
    }
}

$db_host = $config['host'];
$db_name = $config['db'];
$db_user = $config['user'];
$db_pass = $config['pass'] ?? '';

try {
    $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
    $conn = new PDO($dsn, (string)$db_user, (string)$db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    exit('DB connection failed: ' . $e->getMessage());
}

/**
 * ✅ Backward compatibility
 * Some legacy pages still use $pdo
 */
$pdo = $conn;
