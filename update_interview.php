<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  http_response_code(403);
  exit('Forbidden');
}

if (!function_exists('csrf_validate')) {
  function csrf_validate(?string $t): bool {
    return isset($_SESSION['_csrf']) && is_string($t) && hash_equals($_SESSION['_csrf'], $t);
  }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}

if (!csrf_validate($_POST['csrf_token'] ?? null)) {
  http_response_code(400);
  exit('Invalid CSRF token');
}

$formId = (int)($_POST['form_id'] ?? 0);
$stage  = $_POST['stage'] ?? '';
$note   = trim($_POST['note'] ?? '');
$dtLocal = trim($_POST['interview_datetime'] ?? ''); // YYYY-MM-DDTHH:MM or ''

$allowedStages = ['new','scheduled','interviewed','passed','failed','no_show','withdrawn'];
if ($formId <= 0) exit('Invalid form_id');
if (!in_array($stage, $allowedStages, true)) exit('Invalid stage');

$newInterviewDT = null;
if ($dtLocal !== '') {
  if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $dtLocal)) exit('Invalid datetime');
  $newInterviewDT = str_replace('T', ' ', $dtLocal) . ':00';
}

$adminId = (int)($_SESSION['user_id'] ?? 0);

try {
  $conn->beginTransaction();

  // read old values from DB
  $stmt = $conn->prepare("SELECT interview_stage, interview_date FROM interview_forms WHERE form_id=:id FOR UPDATE");
  $stmt->execute([':id' => $formId]);
  $old = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$old) { $conn->rollBack(); exit('Form not found'); }

  $oldStage = $old['interview_stage'];
  $oldDate  = $old['interview_date'];

  // update
  $stmt = $conn->prepare("
    UPDATE interview_forms
    SET interview_stage=:stage, interview_date=:dt, stage_updated_at=NOW()
    WHERE form_id=:id
  ");
  $stmt->bindValue(':stage', $stage, PDO::PARAM_STR);
  if ($newInterviewDT === null) $stmt->bindValue(':dt', null, PDO::PARAM_NULL);
  else $stmt->bindValue(':dt', $newInterviewDT, PDO::PARAM_STR);
  $stmt->bindValue(':id', $formId, PDO::PARAM_INT);
  $stmt->execute();

  // audit log
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
  $stmt->bindValue(':from_date', $oldDate, $oldDate === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
  $stmt->bindValue(':to_date', $newInterviewDT, $newInterviewDT === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
  $stmt->bindValue(':note', $note !== '' ? $note : null, $note !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
  $stmt->execute();

  $conn->commit();

  $redirect = $newInterviewDT ? substr($newInterviewDT,0,10) : ($oldDate ? substr((string)$oldDate,0,10) : null);
  if ($redirect) header('Location: calender_day.php?date=' . urlencode($redirect));
  else header('Location: form_detail.php?form_id=' . urlencode((string)$formId));
  exit;

} catch (Throwable $e) {
  if ($conn->inTransaction()) $conn->rollBack();
  http_response_code(500);
  exit('Server error');
}
