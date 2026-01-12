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
$note   = trim($_POST['note'] ?? 'Deleted by admin');

if ($formId <= 0) {
    http_response_code(400);
    exit('Invalid form_id');
}

$adminId = (int)($_SESSION['user_id'] ?? 0);

try {
    $conn->beginTransaction();

    // Lock row and get old values (optional, but good for audit)
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
        exit('Form not found or already deleted');
    }

    // Soft delete
    $stmt = $conn->prepare("
      UPDATE interview_forms
      SET deleted_at = NOW()
      WHERE form_id = :id AND deleted_at IS NULL
    ");
    $stmt->execute([':id' => $formId]);

    // Write an audit row into interview_stage_logs as a "deletion event"
    // (No schema change needed. We store to_stage='deleted' even if not in enum on main table.)
    $stmt = $conn->prepare("
      INSERT INTO interview_stage_logs
        (form_id, changed_by_user_id, from_stage, to_stage, from_interview_date, to_interview_date, note, changed_at)
      VALUES
        (:form_id, :by, :from_stage, :to_stage, :from_date, :to_date, :note, NOW())
    ");
    $stmt->bindValue(':form_id', $formId, PDO::PARAM_INT);
    $stmt->bindValue(':by', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':from_stage', $old['interview_stage'], PDO::PARAM_STR);
    $stmt->bindValue(':to_stage', 'deleted', PDO::PARAM_STR);

    if ($old['interview_date'] === null) $stmt->bindValue(':from_date', null, PDO::PARAM_NULL);
    else $stmt->bindValue(':from_date', $old['interview_date'], PDO::PARAM_STR);

    $stmt->bindValue(':to_date', null, PDO::PARAM_NULL);
    $stmt->bindValue(':note', $note !== '' ? $note : 'Deleted by admin', PDO::PARAM_STR);
    $stmt->execute();

    $conn->commit();

    header('Location: form.php?deleted=1');
    exit;

} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    exit('Server error');
}
