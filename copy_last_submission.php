<?php
// copy_last_submission.php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';

// csrf.php 可能存在不同函数名，这里尽量兼容
$csrfFile = __DIR__ . '/csrf.php';
if (file_exists($csrfFile)) require_once $csrfFile;

// 只允许 POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// 统一的 base path（防止子目录导致 redirect 错）
$base = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');

// ---- CSRF 兼容校验（避免你 csrf.php 函数名不同导致 fatal 白屏）----
$csrf_ok = false;

if (function_exists('csrf_check')) {
    // 你有些页面用过 csrf_check()，优先用
    $csrf_ok = (bool)csrf_check();
} else {
    // fallback：用 session token 做校验（假设 csrf_token() 把 token 存在 session）
    $posted = $_POST['csrf_token'] ?? '';
    $sess   = $_SESSION['csrf_token'] ?? ($_SESSION['csrf'] ?? '');

    if ($posted !== '' && $sess !== '' && hash_equals((string)$sess, (string)$posted)) {
        $csrf_ok = true;
    }
}

if (!$csrf_ok) {
    http_response_code(400);
    exit('Bad CSRF token');
}

// 只允许 staff (online_posting)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'online_posting') {
    http_response_code(403);
    exit('Forbidden');
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    exit('Unauthorized');
}

// DB 连接变量兼容
if (isset($conn) && !isset($pdo)) $pdo = $conn;

try {
    // 1) 找当前 staff 最新一份 form
    $stmt = $pdo->prepare("
        SELECT *
        FROM interview_forms
        WHERE user_id = :user_id
        ORDER BY created_at DESC, form_id DESC
        LIMIT 1
    ");
    $stmt->execute([':user_id' => $user_id]);
    $form = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$form) {
        header("Location: {$base}/submit_form.php?copy=none");
        exit;
    }

    // 2) 找 language_skills（如果有）
    $stmt2 = $pdo->prepare("
        SELECT *
        FROM language_skills
        WHERE form_id = :form_id
        LIMIT 1
    ");
    $stmt2->execute([':form_id' => $form['form_id']]);
    $lang = $stmt2->fetch(PDO::FETCH_ASSOC) ?: [];

    // 3) 预填充 payload（不带 stage/date）
    $prefill = [
        'name' => $form['name'] ?? '',
        'age' => $form['age'] ?? '',
        'gender' => $form['gender'] ?? '',
        'phone' => $form['phone'] ?? '',
        'position' => $form['position'] ?? '',
        'transportation' => $form['transportation'] ?? '',
        'education' => $form['education'] ?? '',
        'area' => $form['area'] ?? '',
        'area_other' => '', // 如果你 submit_form.php 有 others 输入框
        'expected_salary' => $form['expected_salary'] ?? '',
        'work_experience' => $form['work_experience'] ?? '',
        'start_date' => $form['start_date'] ?? '',

        // 语言（兼容两种常见列名）
        'chinese_writing'  => $lang['chinese_writing']  ?? ($lang['chinese_w'] ?? ''),
        'chinese_speaking' => $lang['chinese_speaking'] ?? ($lang['chinese_s'] ?? ''),
        'english_writing'  => $lang['english_writing']  ?? ($lang['english_w'] ?? ''),
        'english_speaking' => $lang['english_speaking'] ?? ($lang['english_s'] ?? ''),
        'malay_writing'    => $lang['malay_writing']    ?? ($lang['malay_w'] ?? ''),
        'malay_speaking'   => $lang['malay_speaking']   ?? ($lang['malay_s'] ?? ''),
    ];

    $_SESSION['prefill_submission'] = [
        'ts' => time(),
        'data' => $prefill,
        'source_form_id' => $form['form_id'],
    ];

    header("Location: {$base}/submit_form.php?copy=ok");
    exit;
} catch (Throwable $e) {
    // 你现在先做功能，白屏很烦：这里直接输出错误，方便你立刻看到原因
    http_response_code(500);
    echo "Copy failed: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}
