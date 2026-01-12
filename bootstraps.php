<?php
declare(strict_types=1);

/**
 * Global bootstrap for WishSystem
 * - Error reporting
 * - Timezone
 * - Session safety
 */

/* =========================
   DEBUG SWITCH
   ========================= */
define('DEBUG_MODE', true);   // ❗修好后改成 false

if (DEBUG_MODE) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
}

/* =========================
   TIMEZONE
   ========================= */
date_default_timezone_set('Asia/Kuala_Lumpur');

/* =========================
   SESSION SAFETY
   ========================= */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================
   BASIC SECURITY HEADERS
   ========================= */
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
