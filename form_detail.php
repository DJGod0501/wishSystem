<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/audit_log_helper.php';

if (isset($conn) && !isset($pdo)) $pdo = $conn;

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Admin only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

$form_id = (int)($_GET['form_id'] ?? $_POST['form_id'] ?? 0);
if ($form_id <= 0) {
    http_response_code(400);
    exit('Bad form_id');
}

/* ================= UPDATE STAGE/DATE (WITH AUDIT LOG) ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_stage') {

    // CSRF compatible
    $csrf_ok = false;
    if (function_exists('csrf_check')) {
        $csrf_ok = (bool)csrf_check();
    } else {
        $posted = $_POST['csrf_token'] ?? '';
        $sess   = $_SESSION['csrf_token'] ?? ($_SESSION['csrf'] ?? '');
        if ($posted !== '' && $sess !== '' && hash_equals((string)$sess, (string)$posted)) $csrf_ok = true;
    }
    if (!$csrf_ok) {
        http_response_code(400);
        exit('Bad CSRF token');
    }

    $new_stage = trim((string)($_POST['interview_stage'] ?? ''));
    $new_date  = trim((string)($_POST['interview_date'] ?? '')); // '' or YYYY-MM-DD
    $note      = trim((string)($_POST['note'] ?? ''));

    // Fetch current for audit
    $stmt = $pdo->prepare("SELECT interview_stage, interview_date FROM interview_forms WHERE form_id = :id LIMIT 1");
    $stmt->execute([':id' => $form_id]);
    $cur = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cur) {
        header('Location: form.php?err=not_found');
        exit;
    }

    $old_stage = $cur['interview_stage'] ?? null;
    $old_date  = $cur['interview_date'] ?? null;   // string or null
    $date_to_save = ($new_date === '' ? null : $new_date);

    // If no change, return
    $changed = false;
    if ((string)$old_stage !== (string)$new_stage) $changed = true;
    if ((string)$old_date !== (string)$date_to_save) $changed = true;

    if ($changed) {
        // Update main row
        $stmt2 = $pdo->prepare("
            UPDATE interview_forms
            SET interview_stage = :stage,
                interview_date  = :date,
                stage_updated_at = NOW()
            WHERE form_id = :id
            LIMIT 1
        ");
        $stmt2->execute([
            ':stage' => $new_stage,
            ':date'  => $date_to_save,
            ':id'    => $form_id
        ]);

        // Insert audit log
        $admin_id = (int)($_SESSION['user_id'] ?? 0);
        audit_log_stage_change(
            $pdo,
            $form_id,
            $admin_id,
            $old_stage,
            $new_stage,
            $old_date,
            $date_to_save,
            $note
        );

        header("Location: form_detail.php?form_id={$form_id}&msg=updated");
        exit;
    }

    header("Location: form_detail.php?form_id={$form_id}&msg=no_change");
    exit;
}

/* ================= FETCH FORM + LANGUAGE ================= */
$stmt = $pdo->prepare("
    SELECT f.*, u.name AS staff_name, u.email AS staff_email
    FROM interview_forms f
    LEFT JOIN users u ON u.user_id = f.user_id
    WHERE f.form_id = :id
    LIMIT 1
");
$stmt->execute([':id' => $form_id]);
$form = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$form) {
    http_response_code(404);
    exit('Form not found');
}

$stmt2 = $pdo->prepare("SELECT * FROM language_skills WHERE form_id = :id LIMIT 1");
$stmt2->execute([':id' => $form_id]);
$lang = $stmt2->fetch(PDO::FETCH_ASSOC) ?: [];

/* ================= FETCH AUDIT LOGS ================= */
try {
    $stmtLog = $pdo->prepare("
        SELECT
            l.log_id,
            l.changed_at,
            l.from_stage, l.to_stage,
            l.from_interview_date, l.to_interview_date,
            l.note,
            u.name AS admin_name,
            u.email AS admin_email
        FROM interview_stage_logs l
        LEFT JOIN users u ON u.user_id = l.changed_by_user_id
        WHERE l.form_id = :form_id
        ORDER BY l.changed_at DESC, l.log_id DESC
        LIMIT 20
    ");
    $stmtLog->execute([':form_id' => $form_id]);
    $logs = $stmtLog->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $logs = [];
}

include 'header.php';

/* ================= ADMIN COPY TEXT ================= */
$copyText = "Company: Wish Group Resources\n"
    . "Name: " . ($form['name'] ?? '') . "\n"
    . "Age: " . ($form['age'] ?? '') . "\n"
    . "Gender: " . ($form['gender'] ?? '') . "\n"
    . "Phone Number: " . ($form['phone'] ?? '') . "\n"
    . "Position of Interest: " . ($form['position'] ?? '') . "\n"
    . "Own Transportation (Yes/No): " . ($form['transportation'] ?? '') . "\n"
    . "Highest Education Level: " . ($form['education'] ?? '') . "\n"
    . "Residential Area: " . ($form['area'] ?? '') . "\n"
    . "Expected Salary: " . ($form['expected_salary'] ?? '') . "\n"
    . "Work Experience: " . ($form['work_experience'] ?? '') . "\n"
    . "Available Start Date: " . ($form['start_date'] ?? '') . "\n\n"
    . "Language Proficiency (1–3)\n"
    . "Chinese Writing: " . ($lang['chinese_writing'] ?? ($lang['chinese_w'] ?? '')) . "\n"
    . "Chinese Speaking: " . ($lang['chinese_speaking'] ?? ($lang['chinese_s'] ?? '')) . "\n"
    . "English Writing: " . ($lang['english_writing'] ?? ($lang['english_w'] ?? '')) . "\n"
    . "English Speaking: " . ($lang['english_speaking'] ?? ($lang['english_s'] ?? '')) . "\n"
    . "Malay Writing: " . ($lang['malay_writing'] ?? ($lang['malay_w'] ?? '')) . "\n"
    . "Malay Speaking: " . ($lang['malay_speaking'] ?? ($lang['malay_s'] ?? '')) . "\n";
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">Form Detail #<?= h($form_id) ?></h4>
  <a class="btn btn-outline-secondary btn-sm" href="form.php">Back</a>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'updated'): ?>
  <div class="alert alert-success">Updated.</div>
<?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'no_change'): ?>
  <div class="alert alert-info">No change.</div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-7">

    <div class="card mb-3">
      <div class="card-header">Candidate Info</div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6"><strong>Name:</strong> <?= h($form['name'] ?? '') ?></div>
          <div class="col-md-6"><strong>Phone:</strong> <?= h($form['phone'] ?? '') ?></div>
          <div class="col-md-6"><strong>Age:</strong> <?= h($form['age'] ?? '') ?></div>
          <div class="col-md-6"><strong>Gender:</strong> <?= h($form['gender'] ?? '') ?></div>
          <div class="col-md-6"><strong>Position:</strong> <?= h($form['position'] ?? '') ?></div>
          <div class="col-md-6"><strong>Transportation:</strong> <?= h($form['transportation'] ?? '') ?></div>
          <div class="col-md-6"><strong>Education:</strong> <?= h($form['education'] ?? '') ?></div>
          <div class="col-md-6"><strong>Area:</strong> <?= h($form['area'] ?? '') ?></div>
          <div class="col-md-6"><strong>Expected Salary:</strong> <?= h($form['expected_salary'] ?? '') ?></div>
          <div class="col-md-6"><strong>Start Date:</strong> <?= h($form['start_date'] ?? '') ?></div>
        </div>

        <div class="mt-3">
          <strong>Work Experience</strong>
          <div class="border rounded p-2 bg-light" style="white-space:pre-wrap;"><?= h($form['work_experience'] ?? '') ?></div>
        </div>

        <div class="mt-3 text-muted">
          <small>
            Submitted by: <?= h($form['staff_name'] ?? 'Unknown') ?> <?= $form['staff_email'] ? '(' . h($form['staff_email']) . ')' : '' ?><br>
            Created at: <?= h($form['created_at'] ?? '') ?>
          </small>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">Language Skills</div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6"><strong>Chinese Writing:</strong> <?= h($lang['chinese_writing'] ?? ($lang['chinese_w'] ?? '')) ?></div>
          <div class="col-md-6"><strong>Chinese Speaking:</strong> <?= h($lang['chinese_speaking'] ?? ($lang['chinese_s'] ?? '')) ?></div>
          <div class="col-md-6"><strong>English Writing:</strong> <?= h($lang['english_writing'] ?? ($lang['english_w'] ?? '')) ?></div>
          <div class="col-md-6"><strong>English Speaking:</strong> <?= h($lang['english_speaking'] ?? ($lang['english_s'] ?? '')) ?></div>
          <div class="col-md-6"><strong>Malay Writing:</strong> <?= h($lang['malay_writing'] ?? ($lang['malay_w'] ?? '')) ?></div>
          <div class="col-md-6"><strong>Malay Speaking:</strong> <?= h($lang['malay_speaking'] ?? ($lang['malay_s'] ?? '')) ?></div>
        </div>
      </div>
    </div>

  </div>

  <div class="col-lg-5">

    <div class="card mb-3">
      <div class="card-header">Admin Update</div>
      <div class="card-body">
        <form method="POST" action="form_detail.php?form_id=<?= h($form_id) ?>">
          <input type="hidden" name="action" value="update_stage">
          <input type="hidden" name="form_id" value="<?= h($form_id) ?>">
          <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

          <label class="form-label">Interview Stage</label>
          <select class="form-select" name="interview_stage">
            <?php
              $stages = ['new','scheduled','interviewed','passed','failed','no_show','withdrawn'];
              foreach ($stages as $s) {
                  $sel = (($form['interview_stage'] ?? '') === $s) ? 'selected' : '';
                  echo "<option value='".h($s)."' {$sel}>".h($s)."</option>";
              }
            ?>
          </select>

          <label class="form-label mt-2">Interview Date (optional)</label>
          <input type="date" class="form-control" name="interview_date" value="<?= h($form['interview_date'] ?? '') ?>">

          <label class="form-label mt-2">Note (optional)</label>
          <input type="text" class="form-control" name="note" maxlength="255" placeholder="e.g. rescheduled due to..." />

          <button class="btn btn-primary mt-3">Update</button>

          <div class="text-muted mt-2">
            <small>Last stage update: <?= h($form['stage_updated_at'] ?? '') ?></small>
          </div>
        </form>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Admin Copy Interview Text</span>
        <button type="button" class="btn btn-sm btn-outline-primary" id="btnCopy">Copy</button>
      </div>
      <div class="card-body">
        <textarea id="copyText" class="form-control" rows="14" style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;"><?= h($copyText) ?></textarea>
        <div id="copyMsg" class="text-success mt-2" style="display:none;">Copied!</div>
      </div>
    </div>

  </div>
</div>

<!-- AUDIT LOG -->
<div class="card mt-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span>Interview Stage Audit Log</span>
    <small class="text-muted">Latest 20</small>
  </div>

  <div class="card-body p-0">
    <?php if (empty($logs)): ?>
      <div class="p-3 text-muted">No audit log yet.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead>
            <tr>
              <th style="white-space:nowrap;">Time</th>
              <th>Changed By</th>
              <th>Stage</th>
              <th>Date</th>
              <th>Note</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($logs as $r): ?>
              <?php
                $time = $r['changed_at'] ?? '';
                $by = trim(($r['admin_name'] ?? '') . ' ' . ($r['admin_email'] ? '(' . $r['admin_email'] . ')' : ''));
                $fromStage = $r['from_stage'] ?? '';
                $toStage = $r['to_stage'] ?? '';
                $fromDate = $r['from_interview_date'] ?? '';
                $toDate = $r['to_interview_date'] ?? '';
                $note = $r['note'] ?? '';
              ?>
              <tr>
                <td style="white-space:nowrap;"><?= h($time) ?></td>
                <td><?= h($by !== '' ? $by : 'Unknown') ?></td>
                <td>
                  <span class="text-muted"><?= h($fromStage === '' ? '—' : $fromStage) ?></span>
                  →
                  <strong><?= h($toStage === '' ? '—' : $toStage) ?></strong>
                </td>
                <td>
                  <span class="text-muted"><?= h($fromDate === '' ? '—' : $fromDate) ?></span>
                  →
                  <strong><?= h($toDate === '' ? '—' : $toDate) ?></strong>
                </td>
                <td><?= h($note === '' ? '—' : $note) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
document.getElementById('btnCopy')?.addEventListener('click', function () {
  const ta = document.getElementById('copyText');
  ta.focus();
  ta.select();
  try {
    document.execCommand('copy');
    const m = document.getElementById('copyMsg');
    m.style.display = 'block';
    setTimeout(()=> m.style.display = 'none', 1200);
  } catch (e) {}
});
</script>

<?php include 'footer.php'; ?>
