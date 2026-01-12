<?php
// form_detail.php (self-contained working version)
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/header.php';

// --- Admin guard (this page is mainly for admin) ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo "<div class='container py-4'><div class='alert alert-danger'>Forbidden</div></div>";
    require_once __DIR__ . '/footer.php';
    exit;
}

// --- DB handle compatibility: $conn or $pdo ---
$db = null;
if (isset($pdo) && $pdo instanceof PDO) $db = $pdo;
if (isset($conn) && $conn instanceof PDO) $db = $conn;

if (!$db) {
    echo "<div class='container py-4'><div class='alert alert-danger'>DB connection not found ($pdo/$conn)</div></div>";
    require_once __DIR__ . '/footer.php';
    exit;
}

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// ✅ Fix: accept both form_id and id (solves "Bad form_id")
$form_id_raw = $_GET['form_id'] ?? $_GET['id'] ?? null;
$form_id = (int)$form_id_raw;

if ($form_id <= 0) {
    echo "<div class='container py-4'><div class='alert alert-danger'>Bad form_id</div></div>";
    require_once __DIR__ . '/footer.php';
    exit;
}

$stages = [
  'new' => 'New',
  'scheduled' => 'Scheduled',
  'interviewed' => 'Interviewed',
  'passed' => 'Passed',
  'failed' => 'Failed',
  'no_show' => 'No Show',
  'withdrawn' => 'Withdrawn'
];

$flash_ok = null;
$flash_err = null;

// --- Handle admin update (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_stage = trim((string)($_POST['interview_stage'] ?? ''));
    $new_date  = trim((string)($_POST['interview_date'] ?? ''));
    $note      = trim((string)($_POST['note'] ?? ''));

    if (!isset($stages[$new_stage])) {
        $flash_err = "Invalid interview stage.";
    } else {
        // interview_date: empty => NULL, else must be YYYY-MM-DD
        $new_date_db = null;
        if ($new_date !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $new_date)) {
                $flash_err = "Invalid interview date format. Use YYYY-MM-DD.";
            } else {
                $new_date_db = $new_date;
            }
        }

        if ($flash_err === null) {
            try {
                $db->beginTransaction();

                // read current values
                $stmt = $db->prepare("SELECT interview_stage, interview_date FROM interview_forms WHERE form_id = ?");
                $stmt->execute([$form_id]);
                $cur = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$cur) {
                    $db->rollBack();
                    $flash_err = "Form not found.";
                } else {
                    $from_stage = (string)$cur['interview_stage'];
                    $from_date  = $cur['interview_date']; // can be null

                    // update main row
                    $stmt = $db->prepare("
                        UPDATE interview_forms
                        SET interview_stage = ?,
                            interview_date = ?,
                            stage_updated_at = NOW()
                        WHERE form_id = ?
                    ");
                    $stmt->execute([$new_stage, $new_date_db, $form_id]);

                    // write audit log (your table exists per your spec)
                    $stmt = $db->prepare("
                        INSERT INTO interview_stage_logs
                        (form_id, changed_by_user_id, from_stage, to_stage, from_interview_date, to_interview_date, note, changed_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $form_id,
                        (int)($_SESSION['user_id'] ?? 0),
                        $from_stage,
                        $new_stage,
                        $from_date,
                        $new_date_db,
                        ($note === '' ? null : $note)
                    ]);

                    $db->commit();
                    $flash_ok = "Updated successfully.";
                }
            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                $flash_err = "Server error: " . $e->getMessage();
            }
        }
    }
}

// --- Load form + staff + language skills ---
$stmt = $db->prepare("
    SELECT f.*,
           u.name AS staff_name, u.email AS staff_email
    FROM interview_forms f
    LEFT JOIN users u ON u.user_id = f.user_id
    WHERE f.form_id = ?
");
$stmt->execute([$form_id]);
$form = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$form) {
    echo "<div class='container py-4'><div class='alert alert-danger'>Form not found.</div></div>";
    require_once __DIR__ . '/footer.php';
    exit;
}

$stmt = $db->prepare("SELECT * FROM language_skills WHERE form_id = ?");
$stmt->execute([$form_id]);
$lang = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// --- Load audit logs ---
$stmt = $db->prepare("
    SELECT l.*,
           u.name AS changed_by_name
    FROM interview_stage_logs l
    LEFT JOIN users u ON u.user_id = l.changed_by_user_id
    WHERE l.form_id = ?
    ORDER BY l.changed_at DESC
");
$stmt->execute([$form_id]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper
function fmtDate($d): string {
    if ($d === null || $d === '') return '-';
    return (string)$d;
}
?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h3 class="mb-0">Form Detail</h3>
            <div class="text-muted">Form ID: #<?= (int)$form_id ?></div>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="form.php">Back to All Forms</a>
        </div>
    </div>

    <?php if ($flash_ok): ?>
        <div class="alert alert-success"><?= h($flash_ok) ?></div>
    <?php endif; ?>
    <?php if ($flash_err): ?>
        <div class="alert alert-danger"><?= h($flash_err) ?></div>
    <?php endif; ?>

    <div class="row g-3">
        <!-- Candidate Info -->
        <div class="col-12 col-lg-7">
            <div class="card">
                <div class="card-header fw-semibold">Candidate Info</div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-12 col-md-6"><div class="text-muted small">Name</div><div class="fw-semibold"><?= h($form['name'] ?? '') ?></div></div>
                        <div class="col-6 col-md-3"><div class="text-muted small">Age</div><div><?= h($form['age'] ?? '') ?></div></div>
                        <div class="col-6 col-md-3"><div class="text-muted small">Gender</div><div><?= h($form['gender'] ?? '') ?></div></div>

                        <div class="col-12 col-md-6"><div class="text-muted small">Phone</div><div><?= h($form['phone'] ?? '') ?></div></div>
                        <div class="col-12 col-md-6"><div class="text-muted small">Position</div><div><?= h($form['position'] ?? '') ?></div></div>

                        <div class="col-12 col-md-6"><div class="text-muted small">Transportation</div><div><?= h($form['transportation'] ?? '') ?></div></div>
                        <div class="col-12 col-md-6"><div class="text-muted small">Education</div><div><?= h($form['education'] ?? '') ?></div></div>

                        <div class="col-12 col-md-6"><div class="text-muted small">Area</div><div><?= h($form['area'] ?? '') ?></div></div>
                        <div class="col-12 col-md-6"><div class="text-muted small">Expected Salary</div><div><?= h($form['expected_salary'] ?? '') ?></div></div>

                        <div class="col-12"><div class="text-muted small">Work Experience</div><div><?= nl2br(h($form['work_experience'] ?? '')) ?></div></div>

                        <div class="col-12 col-md-6"><div class="text-muted small">Start Date</div><div><?= h($form['start_date'] ?? '') ?></div></div>
                        <div class="col-12 col-md-6"><div class="text-muted small">Created At</div><div><?= h($form['created_at'] ?? '') ?></div></div>

                        <div class="col-12">
                            <div class="text-muted small">Submitted by</div>
                            <div><?= h($form['staff_name'] ?? '-') ?> <span class="text-muted small">(<?= h($form['staff_email'] ?? '-') ?>)</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Language Skills -->
            <div class="card mt-3">
                <div class="card-header fw-semibold">Language Skills (1–3)</div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6 col-md-4"><div class="text-muted small">Chinese Writing</div><div><?= h($lang['chinese_writing'] ?? '-') ?></div></div>
                        <div class="col-6 col-md-4"><div class="text-muted small">Chinese Speaking</div><div><?= h($lang['chinese_speaking'] ?? '-') ?></div></div>
                        <div class="col-6 col-md-4"><div class="text-muted small">English Writing</div><div><?= h($lang['english_writing'] ?? '-') ?></div></div>
                        <div class="col-6 col-md-4"><div class="text-muted small">English Speaking</div><div><?= h($lang['english_speaking'] ?? '-') ?></div></div>
                        <div class="col-6 col-md-4"><div class="text-muted small">Malay Writing</div><div><?= h($lang['malay_writing'] ?? '-') ?></div></div>
                        <div class="col-6 col-md-4"><div class="text-muted small">Malay Speaking</div><div><?= h($lang['malay_speaking'] ?? '-') ?></div></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Admin Update Panel -->
        <div class="col-12 col-lg-5">
            <div class="card">
                <div class="card-header fw-semibold">Admin Update</div>
                <div class="card-body">
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Interview Stage</label>
                            <select class="form-select" name="interview_stage" required>
                                <?php foreach ($stages as $k => $label): ?>
                                    <option value="<?= h($k) ?>" <?= (($form['interview_stage'] ?? '') === $k ? 'selected' : '') ?>>
                                        <?= h($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Interview Date (optional)</label>
                            <input type="date" class="form-control" name="interview_date" value="<?= h($form['interview_date'] ?? '') ?>">
                            <div class="form-text">Leave empty to clear.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Note (optional)</label>
                            <input type="text" class="form-control" name="note" maxlength="255" placeholder="e.g. Rescheduled, candidate late, etc.">
                        </div>

                        <button class="btn btn-primary w-100" type="submit">Update</button>
                    </form>

                    <hr>

                    <div class="small text-muted">
                        Current stage: <strong><?= h($form['interview_stage'] ?? '-') ?></strong><br>
                        Current interview date: <strong><?= h(fmtDate($form['interview_date'] ?? null)) ?></strong><br>
                        Stage updated at: <strong><?= h($form['stage_updated_at'] ?? '-') ?></strong>
                    </div>
                </div>
            </div>

            <!-- Audit Logs -->
            <div class="card mt-3">
                <div class="card-header fw-semibold">Interview Stage Audit Log</div>
                <div class="card-body">
                    <?php if (!$logs): ?>
                        <div class="text-muted">No logs yet.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle">
                                <thead>
                                    <tr>
                                        <th style="width:160px;">Changed At</th>
                                        <th style="width:130px;">By</th>
                                        <th>Stage</th>
                                        <th style="width:170px;">Interview Date</th>
                                        <th>Note</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($logs as $l): ?>
                                    <tr>
                                        <td><?= h($l['changed_at'] ?? '') ?></td>
                                        <td><?= h($l['changed_by_name'] ?? '-') ?></td>
                                        <td>
                                            <span class="text-muted small"><?= h($l['from_stage'] ?? '-') ?></span>
                                            &nbsp;→&nbsp;
                                            <strong><?= h($l['to_stage'] ?? '-') ?></strong>
                                        </td>
                                        <td>
                                            <span class="text-muted small"><?= h(fmtDate($l['from_interview_date'] ?? null)) ?></span>
                                            &nbsp;→&nbsp;
                                            <strong><?= h(fmtDate($l['to_interview_date'] ?? null)) ?></strong>
                                        </td>
                                        <td><?= h($l['note'] ?? '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
