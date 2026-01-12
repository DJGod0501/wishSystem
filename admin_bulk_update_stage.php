<?php
// admin_bulk_update_stage.php (bulk update + audit log per row)
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

$form_ids_raw = isset($_POST['form_ids']) ? trim((string)$_POST['form_ids']) : '';
$new_stage = isset($_POST['interview_stage']) ? trim((string)$_POST['interview_stage']) : '';
$new_date = isset($_POST['interview_date']) ? trim((string)$_POST['interview_date']) : '';
$note = isset($_POST['note']) ? trim((string)$_POST['note']) : '';

$allowed = ['new','scheduled','interviewed','passed','failed','no_show','withdrawn'];

if ($form_ids_raw === '' || $new_stage === '' || !in_array($new_stage, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid input']);
    exit;
}

// parse form_ids: "1,2,3"
$ids = array_values(array_filter(array_map('trim', explode(',', $form_ids_raw)), fn($x) => $x !== ''));
$ids = array_values(array_unique(array_map('intval', $ids)));
$ids = array_values(array_filter($ids, fn($x) => $x > 0));

if (count($ids) === 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No valid form_ids']);
    exit;
}

// interview_date: empty => keep unchanged (NULL means no change here), else YYYY-MM-DD
$change_date = false;
$new_date_db = null;
if ($new_date !== '') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $new_date)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid interview_date (YYYY-MM-DD)']);
        exit;
    }
    $change_date = true;
    $new_date_db = $new_date;
}

// note: optional, stored in log only
$note_db = ($note === '' ? null : $note);

try {
    $db->beginTransaction();

    $selectStmt = $db->prepare("SELECT interview_stage, interview_date FROM interview_forms WHERE form_id = ?");
    $updateStageOnly = $db->prepare("
        UPDATE interview_forms
        SET interview_stage = ?, stage_updated_at = NOW()
        WHERE form_id = ?
    ");
    $updateStageAndDate = $db->prepare("
        UPDATE interview_forms
        SET interview_stage = ?, interview_date = ?, stage_updated_at = NOW()
        WHERE form_id = ?
    ");
    $logStmt = $db->prepare("
        INSERT INTO interview_stage_logs
        (form_id, changed_by_user_id, from_stage, to_stage, from_interview_date, to_interview_date, note, changed_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $updated = 0;
    $adminId = (int)($_SESSION['user_id'] ?? 0);

    foreach ($ids as $form_id) {
        $selectStmt->execute([$form_id]);
        $cur = $selectStmt->fetch(PDO::FETCH_ASSOC);
        if (!$cur) {
            continue; // skip invalid ids silently
        }

        $from_stage = (string)$cur['interview_stage'];
        $from_date  = $cur['interview_date'];

        if ($change_date) {
            $updateStageAndDate->execute([$new_stage, $new_date_db, $form_id]);
        } else {
            $updateStageOnly->execute([$new_stage, $form_id]);
        }

        $to_date = $change_date ? $new_date_db : $from_date;

        $logStmt->execute([
            $form_id,
            $adminId,
            $from_stage,
            $new_stage,
            $from_date,
            $to_date,
            $note_db
        ]);

        $updated++;
    }

    $db->commit();
    echo json_encode(['ok' => true, 'updated' => $updated]);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error', 'detail' => $e->getMessage()]);
}
