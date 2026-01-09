<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/csrf.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Admin only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

/* ---------- CSRF ---------- */
$token = $_POST['csrf_token'] ?? '';
if (function_exists('csrf_validate')) {
    if (!csrf_validate($token)) exit('Invalid CSRF');
} else {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        exit('Invalid CSRF');
    }
}

/* ---------- Validate ---------- */
$formId = (int)($_POST['form_id'] ?? 0);
$stage  = (string)($_POST['interview_stage'] ?? '');
$date   = (string)($_POST['interview_date'] ?? '');

$allowedStages = [
    'new',
    'scheduled',
    'interviewed',
    'passed',
    'failed',
    'no_show',
    'withdrawn'
];

if ($formId <= 0) exit('Invalid form_id');
if (!in_array($stage, $allowedStages, true)) exit('Invalid stage');

$interviewDate = null;
if ($date !== '') {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date) {
        exit('Invalid date');
    }
    $interviewDate = $date;
}

/* ---------- Ensure form exists ---------- */
$chk = $pdo->prepare("SELECT form_id FROM interview_forms WHERE form_id = :id LIMIT 1");
$chk->execute([':id' => $formId]);
if (!$chk->fetchColumn()) {
    http_response_code(404);
    exit('Form not found');
}

/* ---------- Update ---------- */
$upd = $pdo->prepare("
    UPDATE interview_forms
    SET interview_stage = :stage,
        interview_date = :interview_date,
        stage_updated_at = NOW()
    WHERE form_id = :id
    LIMIT 1
");
$upd->execute([
    ':stage' => $stage,
    ':interview_date' => $interviewDate,
    ':id' => $formId,
]);

header("Location: form_detail.php?form_id={$formId}&updated=1");
exit;
