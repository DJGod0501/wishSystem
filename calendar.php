<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

echo "STEP 0: calendar.php reached\n";

$boot = __DIR__ . '/bootstrap.php';
echo "STEP 1: bootstrap exists? " . (is_file($boot) ? 'YES' : 'NO') . "\n";
if (!is_file($boot)) exit("STOP: bootstrap.php missing\n");
require_once $boot;
echo "STEP 2: bootstrap included OK\n";

$auth = __DIR__ . '/auth_check.php';
echo "STEP 3: auth_check exists? " . (is_file($auth) ? 'YES' : 'NO') . "\n";
if (!is_file($auth)) exit("STOP: auth_check.php missing\n");
require_once $auth;
echo "STEP 4: auth_check included OK\n";

$db = __DIR__ . '/db.php';
echo "STEP 5: db exists? " . (is_file($db) ? 'YES' : 'NO') . "\n";
if (!is_file($db)) exit("STOP: db.php missing\n");
require_once $db;
echo "STEP 6: db included OK\n";

echo "STEP 7: isset(\$conn)=" . (isset($conn) ? 'YES' : 'NO') . " ; isset(\$pdo)=" . (isset($pdo) ? 'YES' : 'NO') . "\n";
echo "STEP 8: role=" . ($_SESSION['role'] ?? 'NONE') . " user_id=" . ($_SESSION['user_id'] ?? 'NONE') . "\n";

exit("DONE: calendar preflight OK\n");
