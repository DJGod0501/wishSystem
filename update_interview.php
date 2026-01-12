<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  http_response_code(403);
  exit('Forbidden');
}
if (!isset($conn) || !($conn instanceof PDO)) {
  http_response_code(500);
  exit('DB connection $conn not found');
}

if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}

$csrf = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['_csrf'], $csrf)) {
  http_response_code(403);
  exit('CSRF failed');
}

$formId = (int)($_POST['form_id'] ?? 0);
$dtLocal = trim($_POST['interview_datetime'] ?? '');
$backDate = trim($_POST['back_date'] ?? '');

if ($formId <= 0) exit('Invalid form_id');
if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $dtLocal)) exit('Invalid datetime');

$mysqlDT = str_replace('T', ' ', $dtLocal) . ':00';

try {
  $conn->beginTransaction();

  $oldStmt = $conn->prepare("SELECT interview_stage, interview_date FROM interview_forms WHERE form_id=:id FOR UPDATE");
  $oldStmt->execute([':id' => $formId]);
  $old = $oldStmt->fetch(PDO::FETCH_ASSOC);
  if (!$old) {
    $conn->rollBack();
    exit('Form not found');
  }

  $fromStage = (string)($old['interview_stage'] ?? '');
  $fromDate  = $old['interview_date'];

  // ✅ 自动变 scheduled
  $toStage = 'scheduled';

  $upd = $conn->prepare("
    UPDATE interview_forms
    SET interview_date=:dt, interview_stage=:stage, stage_updated_at=NOW()
    WHERE form_id=:id
  ");
  $upd->execute([':dt' => $mysqlDT, ':stage' => $toStage, ':id' => $formId]);

  // ✅ 写 audit log（如果表存在）
  try {
    $log = $conn->prepare("
      INSERT INTO interview_stage_logs
        (form_id, changed_by_user_id, from_stage, to_stage, from_interview_date, to_interview_date, note, changed_at)
      VALUES
        (:form_id, :by, :from_stage, :to_stage, :from_date, :to_date, NULL, NOW())
    ");
    $log->execute([
      ':form_id' => $formId,
      ':by' => (int)($_SESSION['user_id'] ?? 0),
      ':from_stage' => $fromStage,
      ':to_stage' => $toStage,
      ':from_date' => $fromDate,
      ':to_date' => $mysqlDT,
    ]);
  } catch(Throwable $e) {
    // ignore if table not exist
  }

  $conn->commit();
} catch(Throwable $e) {
  if ($conn->inTransaction()) $conn->rollBack();
  http_response_code(500);
  exit('Server error: ' . htmlspecialchars($e->getMessage()));
}

if ($backDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $backDate)) {
  header('Location: calendar_day.php?date=' . urlencode($backDate));
} else {
  header('Location: form_detail.php?form_id=' . $formId);
}
exit;
