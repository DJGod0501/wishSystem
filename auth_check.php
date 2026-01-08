<?php
// auth_check.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/db.php";

// 1) 未登录
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// 2) 自动登出（15分钟无操作）
$timeoutSeconds = 15 * 60;
if (isset($_SESSION["last_activity"]) && (time() - $_SESSION["last_activity"]) > $timeoutSeconds) {
    session_unset();
    session_destroy();
    header("Location: login.php?msg=timeout");
    exit;
}
$_SESSION["last_activity"] = time();

// 3) 每次请求重新检查用户状态（active/inactive）+ 同步 role
$stmt = $conn->prepare("SELECT role, status FROM users WHERE user_id = :id LIMIT 1");
$stmt->execute([":id" => (int)$_SESSION["user_id"]]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_unset();
    session_destroy();
    header("Location: login.php?msg=notfound");
    exit;
}

if (($user["status"] ?? "inactive") !== "active") {
    session_unset();
    session_destroy();
    header("Location: login.php?msg=inactive");
    exit;
}

// 同步 role
$_SESSION["role"] = $user["role"];
