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

if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        return $_SESSION['_csrf'];
    }
}
if (!function_exists('csrf_validate')) {
    function csrf_validate(?string $token): bool {
        return isset($_SESSION['_csrf']) && is_string($token) && hash_equals($_SESSION['_csrf'], $token);
    }
}

$date = $_GET['date'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    exit('Invalid date');
}

$sql = "
SELECT form_id, name, phone, position, interview_stage, interview_date, created_at, user_id
FROM interview_forms
WHERE deleted_at IS NULL
  AND interview_date IS NOT NULL
  AND DATE(interview_date) = :d
ORDER BY interview_date ASC, form_id DESC
";
$stmt = $conn->prepare($sql);
$stmt->execute([':d' => $date]);
$forms = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stageOptions = ['new','scheduled','interviewed','passed','failed','no_show','withdrawn'];

require_once __DIR__ . '/header.php';
?>
<div class="container my-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Interviews on <?= htmlspecialchars($date) ?></h3>
    <a class="btn btn-outline-secondary" href="calender.php">Back to Calendar</a>
  </div>

  <?php if (!$forms): ?>
    <div class="alert alert-info">No interviews scheduled for this day.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-striped align-middle">
        <thead class="table-light">
          <tr>
            <th>Time</th>
            <th>Candidate</th>
            <th>Phone</th>
            <th>Position</th>
            <th>Stage</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($forms as $f): ?>
          <?php
            $dt = $f['interview_date'] ? (new DateTimeImmutable($f['interview_date']))->format('H:i') : '';
          ?>
          <tr>
            <td class="fw-semibold"><?= htmlspecialchars($dt) ?></td>
            <td>
              <a href="form_detail.php?form_id=<?= (int)$f['form_id'] ?>" class="text-decoration-none">
                <?= htmlspecialchars($f['name'] ?? '') ?>
              </a>
            </td>
            <td><?= htmlspecialchars($f['phone'] ?? '') ?></td>
            <td><?= htmlspecialchars($f['position'] ?? '') ?></td>
            <td><span class="badge bg-secondary"><?= htmlspecialchars($f['interview_stage'] ?? '') ?></span></td>
            <td class="text-end">
              <button
                class="btn btn-sm btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#rescheduleModal"
                data-form-id="<?= (int)$f['form_id'] ?>"
                data-name="<?= htmlspecialchars($f['name'] ?? '', ENT_QUOTES) ?>"
                data-stage="<?= htmlspecialchars($f['interview_stage'] ?? '', ENT_QUOTES) ?>"
                data-datetime="<?= htmlspecialchars($f['interview_date'] ?? '', ENT_QUOTES) ?>"
              >
                Reschedule / Update
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Modal -->
<div class="modal fade" id="rescheduleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="post" action="update_interview.php">
      <div class="modal-header">
        <h5 class="modal-title">Reschedule / Update Stage</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="form_id" id="m_form_id" value="">
        <div class="mb-2 text-muted">Candidate: <b id="m_candidate"></b></div>

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Interview date & time</label>
            <input type="datetime-local" class="form-control" name="interview_datetime" id="m_datetime">
            <div class="form-text">留空 = 取消面试时间（会写入 audit log）</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Stage</label>
            <select class="form-select" name="stage" id="m_stage">
              <?php foreach ($stageOptions as $s): ?>
                <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Note (optional)</label>
            <input type="text" class="form-control" name="note" maxlength="255" placeholder="e.g. Candidate requested change, traffic, etc.">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="submit">Save changes</button>
      </div>
    </form>
  </div>
</div>

<script>
(function() {
  const modal = document.getElementById('rescheduleModal');
  modal.addEventListener('show.bs.modal', function (event) {
    const btn = event.relatedTarget;
    const formId = btn.getAttribute('data-form-id');
    const name = btn.getAttribute('data-name') || '';
    const stage = btn.getAttribute('data-stage') || 'new';
    const dt = btn.getAttribute('data-datetime') || '';

    document.getElementById('m_form_id').value = formId;
    document.getElementById('m_candidate').textContent = name;
    document.getElementById('m_stage').value = stage;

    // Convert "YYYY-MM-DD HH:MM:SS" -> "YYYY-MM-DDTHH:MM" for datetime-local
    if (dt) {
      const normalized = dt.replace(' ', 'T').slice(0, 16);
      document.getElementById('m_datetime').value = normalized;
    } else {
      document.getElementById('m_datetime').value = '';
    }
  });
})();
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
