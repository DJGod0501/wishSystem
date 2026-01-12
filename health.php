<?php
declare(strict_types=1);

// 这个文件不要 require 任何东西，确保“只要 PHP 能跑就一定有输出”
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

echo "OK: PHP is running\n";
echo "PHP_VERSION=" . PHP_VERSION . "\n";
echo "FILE=" . __FILE__ . "\n";
echo "DOC_ROOT=" . ($_SERVER['DOCUMENT_ROOT'] ?? '') . "\n";
echo "SCRIPT=" . ($_SERVER['SCRIPT_FILENAME'] ?? '') . "\n";
