<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

// If your staff management page is user.php
if (is_file(__DIR__ . '/user.php')) {
    header('Location: /wishSystem/user.php', true, 302);
    exit;
}

// Fallback: try other likely filenames if you renamed
$alternatives = [
    'staff.php',
    'staff_management.php',
    'admin_staff.php',
];

foreach ($alternatives as $f) {
    if (is_file(__DIR__ . '/' . $f)) {
        header('Location: /wishSystem/' . $f, true, 302);
        exit;
    }
}

http_response_code(404);
echo "staff_manage.php exists, but no target staff page found (expected user.php).";
exit;
