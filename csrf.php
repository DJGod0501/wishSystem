<?php
// csrf.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function csrf_token(): string {
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf_token"];
}

function csrf_validate(): void {
    $token = $_POST["csrf_token"] ?? "";
    if (!$token || !hash_equals($_SESSION["csrf_token"] ?? "", $token)) {
        http_response_code(403);
        exit("CSRF validation failed.");
    }
}

function csrf_rotate(): void {
    unset($_SESSION["csrf_token"]);
}

// For GET download links (export)
function csrf_validate_get(string $param = "token"): void {
    $token = $_GET[$param] ?? "";
    if (!$token || !hash_equals($_SESSION["csrf_token"] ?? "", $token)) {
        http_response_code(403);
        exit("Invalid token.");
    }
}
