<?php
header('Content-Type: text/plain; charset=utf-8');

$path = __DIR__ . '/bootstrap.php';
echo $path . "\n";
echo is_file($path) ? "FOUND\n" : "MISSING\n";
