<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/csrf.php';

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// CSRF compatible
$csrf_ok = false;
if (function_exists('csrf_check')) {
    $csrf_ok = (bool)csrf_check();
} else {
    $posted = $_POST['csrf_token'] ?? '';
    $sess   = $_SESSION['csrf_token'] ?? ($_SESSION['csrf'] ?? '');
    if ($posted !== '' && $sess !== '' && hash_equals((string)$sess, (string)$posted)) {
        $csrf_ok = true;
    }
}

if (!$csrf_ok) {
    http_response_code(400);
    exit('Bad CSRF token');
}

// Staff only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'online_posting') {
    http_response_code(403);
    exit('Forbidden');
}

unset($_SESSION['prefill_submission']);

header('Location: submit_form.php?prefill=cleared');
exit;
