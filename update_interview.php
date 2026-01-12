<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';

function require_admin_fallback(): void {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        exit('Forbidden');
    }
}
require_admin_fallback();

if (!function_exists('csrf_validate')) {
    function csrf_validate(?string $token): bool {
        return isset($_SESSION['_csrf']) && is_string($token) && hash_equals($_SESSION['_csrf'], $token);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$token = $_POST['csrf_token'] ?? null;
if (!csrf_validate($token)) {
    http_response_code(400);
    exit('Invalid CSRF token');
}

$formId = isset($_POST['form_id']) ? (int)$_POST['form_id'] : 0;
$stage  = $_POST['stage'] ?? '';
$note   = trim($_POST['note'] ?? '');
$dtLocal = trim($_POST['interview_datetime'] ?? ''); // datetime-local: "YYYY-MM-DDTHH:MM" or ""

$allowedStages = ['new','scheduled','interviewed','passed','failed','no_show','withdrawn'];

if ($formId <= 0) {
    http_response_code(400);
    exit('Invalid form_id');
}
if (!in_array($stage, $allowedStages, true)) {
    http_response_code(400);
    exit('Invalid stage');
}
if ($note !== '' && mb_strlen($note) > 255) {
    http_response_code(400);
    exit('Note too long');
}

// Convert datetime-local -> "YYYY-MM-DD HH:MM:00" (MySQL-friendly) or NULL
$newInterviewDT = null;
if ($dtLocal !== '') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $dtLocal)) {
        http_response_code(400);
        exit('Invalid datetime format');
    }
    $newInterviewDT = str_replace('T', ' ', $dtLocal) . ':00';
}

$adminId = (int)($_SESSION['user_id'] ?? 0);

try {
    $conn->beginTransaction();

    // Lock the row and fetch old values from DB (do NOT trust hidden inputs)
    $stmt = $conn->prepare("
        SELECT interview_stage, interview_date
        FROM interview_forms
        WHERE form_id = :id AND deleted_at IS NULL
        FOR UPDATE
    ");
    $stmt->execute([':id' => $formId]);
    $old = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$old) {
        $conn->rollBack();
        http_response_code(404);
        exit('Form not found');
    }

    $oldStage = $old['interview_stage'];
    $oldDate  = $old['interview_date']; // may be NULL

    // Update main table
    $stmt = $conn->prepare("
        UPDATE interview_forms
        SET interview_stage = :stage,
            interview_date = :dt,
            stage_updated_at = NOW()
        WHERE form_id = :id AND deleted_at IS NULL
    ");
    $stmt->bindValue(':stage', $stage, PDO::PARAM_STR);
    if ($newInterviewDT === null) {
        $stmt->bindValue(':dt', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':dt', $newInterviewDT, PDO::PARAM_STR);
    }
    $stmt->bindValue(':id', $formId, PDO::PARAM_INT);
    $stmt->execute();

    // Write audit log (always)
    $stmt = $conn->prepare("
        INSERT INTO interview_stage_logs
          (form_id, changed_by_user_id, from_stage, to_stage, from_interview_date, to_interview_date, note, changed_at)
        VALUES
          (:form_id, :by, :from_stage, :to_stage, :from_date, :to_date, :note, NOW())
    ");
    $stmt->bindValue(':form_id', $formId, PDO::PARAM_INT);
    $stmt->bindValue(':by', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':from_stage', $oldStage, PDO::PARAM_STR);
    $stmt->bindValue(':to_stage', $stage, PDO::PARAM_STR);

    if ($oldDate === null) $stmt->bindValue(':from_date', null, PDO::PARAM_NULL);
    else $stmt->bindValue(':from_date', $oldDate, PDO::PARAM_STR);

    if ($newInterviewDT === null) $stmt->bindValue(':to_date', null, PDO::PARAM_NULL);
    else $stmt->bindValue(':to_date', $newInterviewDT, PDO::PARAM_STR);

    $stmt->bindValue(':note', $note !== '' ? $note : null, $note !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->execute();

    $conn->commit();

    // Redirect back (prefer day view if we can infer date)
    $redirectDate = null;
    if ($newInterviewDT !== null) {
        $redirectDate = substr($newInterviewDT, 0, 10);
    } elseif ($oldDate !== null) {
        $redirectDate = substr($oldDate, 0, 10);
    }

    if ($redirectDate) {
        header('Location: calender_day.php?date=' . urlencode($redirectDate));
    } else {
        header('Location: form_detail.php?form_id=' . urlencode((string)$formId));
    }
    exit;

} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    exit('Server error');
}
