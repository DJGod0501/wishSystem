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

if (!isset($conn) || !($conn instanceof PDO)) {
    http_response_code(500);
    exit('DB connection $conn not found');
}

if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
}

function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function fmt_dt(?string $raw): string {
    if (!$raw) return '-';
    try {
        $dt = new DateTimeImmutable($raw);
        return $dt->format('Y-m-d H:i');
    } catch (Throwable $e) {
        return (string)$raw;
    }
}

function to_dt_local(?string $raw): string {
    if (!$raw) return '';
    try {
        $dt = new DateTimeImmutable($raw);
        return $dt->format('Y-m-d\TH:i');
    } catch (Throwable $e) {
        return '';
    }
}

// Search
$q = trim((string)($_GET['q'] ?? ''));
$hasQ = ($q !== '');

$where = "1=1";
$params = [];

if ($hasQ) {
    // search by name / phone / position
    $where = "(name LIKE :q OR phone LIKE :q OR position LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}

// Limit (keep your original behavior: up to 500 latest)
$sql = "
SELECT
  form_id,
  user_id,
  name,
  phone,
  position,
  interview_stage,
  created_at,
  interview_in_person_at,
  interview_online_at
FROM interview_forms
WHERE {$where}
ORDER BY form_id DESC
LIMIT 500
";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'All Forms';
require_once __DIR__ . '/header.php';
?>

<div class="container my-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">All Forms</h2>
    <a class="btn btn-outline-secondary" href="/wishSystem/calendar.php">Calendar</a>
  </div>

  <form class="d-flex gap-2 mb-3" method="get" action="/wishSystem/form.php">
    <input class="form-control" type="text" name="q" value="<?= h($q) ?>" placeholder="Search by name / phone / position">
    <button class="btn btn-primary" type="submit">Search</button>
    <a class="btn btn-outline-secondary" href="/wishSystem/form.php">Reset</a>
  </form>

  <div class="table-responsive">
    <table class="table table-striped align-middle">
      <thead class="table-light">
        <tr>
          <th style="width:80px;">ID</th>
          <th>Candidate</th>
          <th style="width:140px;">Phone</th>
          <th style="width:200px;">Position</th>
          <th style="width:140px;">Stage</th>
          <th style="width:260px;">Interview</th>
          <th style="width:210px;">Created</th>
          <th class="text-end" style="width:260px;">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <?php
          $formId = (int)$r['form_id'];
          $inPersonRaw = $r['interview_in_person_at'] ?? null;
          $onlineRaw   = $r['interview_online_at'] ?? null;

          $inPersonText = ($inPersonRaw ? fmt_dt((string)$inPersonRaw) : '-');
          $onlineText   = ($onlineRaw ? fmt_dt((string)$onlineRaw) : '-');

          // Prefill modal: choose earliest available, default in_person
          $prefType = $onlineRaw && !$inPersonRaw ? 'online' : 'in_person';
          $prefDtLocal = $prefType === 'online' ? to_dt_local((string)$onlineRaw) : to_dt_local((string)$inPersonRaw);
          if (!$prefDtLocal) $prefDtLocal = date('Y-m-d\TH:i', time() + 3600); // +1 hour default
        ?>
        <tr>
          <td><?= $formId ?></td>
          <td>
            <a class="text-decoration-none" href="/wishSystem/form_detail.php?form_id=<?= $formId ?>">
              <?= h($r['name'] ?? '') ?>
            </a>
          </td>
          <td><?= h($r['phone'] ?? '') ?></td>
          <td><?= h($r['position'] ?? '') ?></td>
          <td><span class="badge bg-secondary"><?= h($r['interview_stage'] ?? '') ?></span></td>
          <td>
            <div class="small">
              <div><span class="badge text-bg-primary me-1">In</span> <?= h($inPersonText) ?></div>
              <div><span class="badge text-bg-info me-1">On</span> <?= h($onlineText) ?></div>
            </div>
          </td>
          <td><?= h($r['created_at'] ?? '') ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-primary" href="/wishSystem/form_detail.php?form_id=<?= $formId ?>">Detail</a>

            <!-- Schedule modal trigger -->
            <button class="btn btn-sm btn-primary"
                    type="button"
                    data-bs-toggle="modal"
                    data-bs-target="#scheduleModal<?= $formId ?>">
              Schedule
            </button>

            <a class="btn btn-sm btn-danger" href="/wishSystem/delete_form.php?form_id=<?= $formId ?>"
               onclick="return confirm('Delete this form?');">Delete</a>
          </td>
        </tr>

        <!-- ✅ Schedule Modal -->
        <div class="modal fade" id="scheduleModal<?= $formId ?>" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <form method="post" action="/wishSystem/update_interview.php">
                <div class="modal-header">
                  <h5 class="modal-title">Schedule Interview (ID <?= $formId ?>)</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                  <input type="hidden" name="csrf_token" value="<?= h($_SESSION['_csrf']) ?>">
                  <input type="hidden" name="form_id" value="<?= $formId ?>">
                  <!-- return to All Forms after save -->
                  <input type="hidden" name="back_date" value="">

                  <div class="mb-3">
                    <label class="form-label">Interview Type</label>
                    <select class="form-select" name="interview_type" required>
                      <option value="in_person" <?= $prefType === 'in_person' ? 'selected' : '' ?>>In-person</option>
                      <option value="online" <?= $prefType === 'online' ? 'selected' : '' ?>>Online</option>
                    </select>
                    <div class="form-text">In-person & Online have separate times.</div>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Interview Time</label>
                    <input type="datetime-local"
                           class="form-control"
                           name="interview_datetime"
                           value="<?= h($prefDtLocal) ?>"
                           required>
                    <div class="form-text">This will save into the selected type column.</div>
                  </div>

                  <div class="small text-muted">
                    Current:<br>
                    <span class="badge text-bg-primary me-1">In</span> <?= h($inPersonText) ?><br>
                    <span class="badge text-bg-info me-1">On</span> <?= h($onlineText) ?>
                  </div>
                </div>

                <div class="modal-footer">
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn btn-primary">Save</button>
                </div>
              </form>
            </div>
          </div>
        </div>

      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="text-muted small mt-2">Showing up to 500 latest results.</div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
