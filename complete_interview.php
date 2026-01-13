<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';

// Admin guard
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$formId = (int)($_POST['form_id'] ?? 0);
$type   = (string)($_POST['interview_type'] ?? '');
$attend = (string)($_POST['attendance'] ?? '');
$note   = trim((string)($_POST['feedback'] ?? ''));
$backDate = (string)($_POST['back_date'] ?? ''); // for redirect to calendar_day

if ($formId <= 0 || !in_array($type, ['in_person', 'online'], true) || !in_array($attend, ['attended', 'no_show'], true)) {
    http_response_code(400);
    exit('Invalid request');
}

// Map fields by type
if ($type === 'in_person') {
    $attField = 'attended_in_person';
    $fbField  = 'feedback_in_person';
    $dtField  = 'interview_in_person_at';
} else {
    $attField = 'attended_online';
    $fbField  = 'feedback_online';
    $dtField  = 'interview_online_at';
}

$attVal = ($attend === 'attended') ? 1 : 0;
$newStage = ($attend === 'attended') ? 'interviewed' : 'no_show';

try {
    $conn->beginTransaction();

    // Fetch current state for logging
    $stmt = $conn->prepare("
        SELECT interview_stage, interview_date, interview_in_person_at, interview_online_at
        FROM interview_forms
        WHERE form_id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $formId]);
    $before = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$before) {
        $conn->rollBack();
        http_response_code(404);
        exit('Form not found');
    }

    $fromStage = (string)($before['interview_stage'] ?? '');
    $fromLegacyDate = $before['interview_date'] ?? null;

    // Update interview completion data + stage
    $sql = "
        UPDATE interview_forms
        SET
            {$attField} = :att,
            {$fbField}  = :fb,
            interview_stage = :stage,
            stage_updated_at = NOW()
        WHERE form_id = :id
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':att'   => $attVal,
        ':fb'    => $note !== '' ? $note : null,
        ':stage' => $newStage,
        ':id'    => $formId,
    ]);

    // Optional: also ensure legacy interview_date stays synced to earliest interview datetime
    // (Only do this if your system relies on interview_date; harmless if already handled elsewhere)
    $stmt = $conn->prepare("
        UPDATE interview_forms
        SET interview_date =
            CASE
              WHEN interview_in_person_at IS NULL THEN interview_online_at
              WHEN interview_online_at IS NULL THEN interview_in_person_at
              WHEN interview_in_person_at <= interview_online_at THEN interview_in_person_at
              ELSE interview_online_at
            END
        WHERE form_id = :id
    ");
    $stmt->execute([':id' => $formId]);

    // Get updated legacy date for log
    $stmt = $conn->prepare("SELECT interview_date FROM interview_forms WHERE form_id = :id LIMIT 1");
    $stmt->execute([':id' => $formId]);
    $afterLegacyDate = $stmt->fetchColumn();

    // Insert stage log
    $logNote = "[Complete interview][$type][$attend] " . ($note !== '' ? $note : '');
    $stmt = $conn->prepare("
        INSERT INTO interview_stage_logs
            (form_id, changed_by_user_id, from_stage, to_stage, from_interview_date, to_interview_date, note, changed_at)
        VALUES
            (:form_id, :by_uid, :from_stage, :to_stage, :from_date, :to_date, :note, NOW())
    ");
    $stmt->execute([
        ':form_id'    => $formId,
        ':by_uid'     => (int)$_SESSION['user_id'],
        ':from_stage' => $fromStage !== '' ? $fromStage : null,
        ':to_stage'   => $newStage,
        ':from_date'  => $fromLegacyDate,
        ':to_date'    => $afterLegacyDate,
        ':note'       => $logNote,
    ]);

    $conn->commit();

} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    exit('Failed: ' . $e->getMessage());
}

// Redirect back to calendar day
if ($backDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $backDate)) {
    header('Location: calendar_day.php?date=' . urlencode($backDate));
} else {
    header('Location: calendar_day.php');
}
exit;
