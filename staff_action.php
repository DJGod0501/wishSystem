<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';

// Admin only
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

$userId = (int)($_POST['user_id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($userId <= 0 || !in_array($action, ['enable', 'disable'], true)) {
    http_response_code(400);
    exit('Invalid request');
}

$newStatus = ($action === 'enable') ? 'active' : 'inactive';

$stmt = $conn->prepare("
    UPDATE users
    SET status = :status
    WHERE user_id = :uid
      AND role = 'online_posting'
");
$stmt->execute([
    ':status' => $newStatus,
    ':uid'    => $userId,
]);

header('Location: user.php');
exit;
