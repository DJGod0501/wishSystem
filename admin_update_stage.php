<?php
// admin_update_stage.php (single-row update + audit log)
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

// DB handle compatibility
$db = null;
if (isset($pdo) && $pdo instanceof PDO) $db = $pdo;
if (isset($conn) && $conn instanceof PDO) $db = $conn;
if (!$db) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB connection not found ($pdo/$conn)']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$form_id = isset($_POST['form_id']) ? (int)$_POST['form_id'] : 0;
$new_stage = isset($_POST['interview_stage']) ? trim((string)$_POST['interview_stage']) : '';
$new_date = isset($_POST['interview_date']) ? trim((string)$_POST['interview_date']) : '';
$note = isset($_POST['note']) ? trim((string)$_POST['note']) : '';

$allowed = ['new','scheduled','interviewed','passed','failed','no_show','withdrawn'];

if ($form_id <= 0 || $new_stage === '' || !in_array($new_stage, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid input']);
    exit;
}

// interview_date: empty => NULL, else YYYY-MM-DD
$new_date_db = null;
if ($new_date !== '') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $new_date)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid interview_date (YYYY-MM-DD)']);
        exit;
    }
    $new_date_db = $new_date;
}

try {
    $db->beginTransaction();

    $stmt = $db->prepare("SELECT interview_stage, interview_date FROM interview_forms WHERE form_id = ?");
    $stmt->execute([$form_id]);
    $cur = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cur) {
        $db->rollBack();
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Form not found']);
        exit;
    }

    $from_stage = (string)$cur['interview_stage'];
    $from_date  = $cur['interview_date'];

    $stmt = $db->prepare("
        UPDATE interview_forms
        SET interview_stage = ?,
            interview_date = ?,
            stage_updated_at = NOW()
        WHERE form_id = ?
    ");
    $stmt->execute([$new_stage, $new_date_db, $form_id]);

    $stmt = $db->prepare("
        INSERT INTO interview_stage_logs
        (form_id, changed_by_user_id, from_stage, to_stage, from_interview_date, to_interview_date, note, changed_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $form_id,
        (int)($_SESSION['user_id'] ?? 0),
        $from_stage,
        $new_stage,
        $from_date,
        $new_date_db,
        ($note === '' ? null : $note)
    ]);

    $db->commit();
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error', 'detail' => $e->getMessage()]);
}
