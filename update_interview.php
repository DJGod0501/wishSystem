<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';

// Admin only (按你现在的 update 逻辑，calendar day 是 admin edit)
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

if (!isset($conn) || !($conn instanceof PDO)) {
    http_response_code(500);
    exit('DB connection $conn not found');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// CSRF (你现有 convention)
$csrf = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], (string)$csrf)) {
    http_response_code(403);
    exit('Invalid CSRF token');
}

$form_id = (int)($_POST['form_id'] ?? 0);
$back_date = $_POST['back_date'] ?? '';
$interview_type = $_POST['interview_type'] ?? ''; // in_person / online
$dt_local = $_POST['interview_datetime'] ?? '';

if ($form_id <= 0) {
    http_response_code(400);
    exit('Invalid form_id');
}

if (!in_array($interview_type, ['in_person', 'online'], true)) {
    http_response_code(400);
    exit('Invalid interview_type');
}

// datetime-local: 2026-01-12T15:30
if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', (string)$dt_local)) {
    http_response_code(400);
    exit('Invalid interview_datetime');
}

// convert to MySQL DATETIME
$dt_mysql = str_replace('T', ' ', $dt_local) . ':00';

$col = ($interview_type === 'in_person') ? 'interview_in_person_at' : 'interview_online_at';

$conn->beginTransaction();

try {
    // fetch current for audit
    $stmt = $conn->prepare("
        SELECT interview_stage, interview_date, interview_in_person_at, interview_online_at
        FROM interview_forms
        WHERE form_id = :id
        FOR UPDATE
    ");
    $stmt->execute([':id' => $form_id]);
    $cur = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cur) {
        throw new RuntimeException('Form not found');
    }

    $from_stage = (string)($cur['interview_stage'] ?? '');
    $from_in_person = $cur['interview_in_person_at'] ?? null;
    $from_online = $cur['interview_online_at'] ?? null;

    // update time + auto scheduled
    $stmt2 = $conn->prepare("
        UPDATE interview_forms
        SET {$col} = :dt,
            interview_stage = CASE WHEN interview_stage = 'new' THEN 'scheduled' ELSE interview_stage END,
            stage_updated_at = NOW()
        WHERE form_id = :id
    ");
    $stmt2->execute([':dt' => $dt_mysql, ':id' => $form_id]);

    // after values
    $to_stage = ($from_stage === 'new') ? 'scheduled' : $from_stage;

    // keep interview_date (legacy) updated to nearest upcoming among the two, so old views still work
    $stmt3 = $conn->prepare("
        UPDATE interview_forms
        SET interview_date = (
          CASE
            WHEN interview_in_person_at IS NULL AND interview_online_at IS NULL THEN NULL
            WHEN interview_in_person_at IS NULL THEN interview_online_at
            WHEN interview_online_at IS NULL THEN interview_in_person_at
            ELSE LEAST(interview_in_person_at, interview_online_at)
          END
        )
        WHERE form_id = :id
    ");
    $stmt3->execute([':id' => $form_id]);

    // audit log note
    $note = ($interview_type === 'in_person')
        ? 'Update in-person interview time'
        : 'Update online interview time';

    // fetch updated row for audit "to"
    $stmt4 = $conn->prepare("
        SELECT interview_date, interview_in_person_at, interview_online_at
        FROM interview_forms
        WHERE form_id = :id
    ");
    $stmt4->execute([':id' => $form_id]);
    $newRow = $stmt4->fetch(PDO::FETCH_ASSOC);

    $to_interview_date = $newRow['interview_date'] ?? null;

    $stmtLog = $conn->prepare("
        INSERT INTO interview_stage_logs
          (form_id, changed_by_user_id, from_stage, to_stage, from_interview_date, to_interview_date, note, changed_at)
        VALUES
          (:form_id, :by, :from_stage, :to_stage, :from_dt, :to_dt, :note, NOW())
    ");
    $stmtLog->execute([
        ':form_id' => $form_id,
        ':by' => (int)$_SESSION['user_id'],
        ':from_stage' => $from_stage,
        ':to_stage' => $to_stage,
        ':from_dt' => $cur['interview_date'] ?? null,
        ':to_dt' => $to_interview_date,
        ':note' => $note,
    ]);

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollBack();
    http_response_code(500);
    exit('Update failed: ' . $e->getMessage());
}

// redirect back
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$back_date)) {
    header('Location: /wishSystem/calendar_day.php?date=' . rawurlencode($back_date));
} else {
    header('Location: /wishSystem/calendar.php');
}
exit;
