<?php
// db.php

$cfgFile = __DIR__ . "/config.php";
if (!file_exists($cfgFile)) {
    http_response_code(500);
    exit("Missing config.php. Copy config.example.php to config.php and fill in credentials.");
}

$cfg = require $cfgFile;

$host = $cfg["host"] ?? "localhost";
$db   = $cfg["db"]   ?? "wishsystem";
$user = $cfg["user"] ?? "root";
$pass = $cfg["pass"] ?? "";
$charset = "utf8mb4";

$dsn = "mysql:host={$host};dbname={$db};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $conn = new PDO($dsn, $user, $pass, $options);
} catch (Exception $e) {
    http_response_code(500);
    exit("Database connection failed.");
}
